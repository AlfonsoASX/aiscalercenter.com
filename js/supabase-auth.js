import { createClient } from 'https://esm.sh/@supabase/supabase-js@2';
import {
    bindForm,
    cleanupAuthHash,
    consumeFlash,
    describeErrorMessage,
    setButtonBusy,
    setFlash,
    showNotice,
} from './shared/ui.js';

export const authConfig = window.AISCALER_AUTH_CONFIG ?? null;
export const panelConfig = authConfig?.panel ?? {
    bootstrap_admins: [],
    roles: {},
    menus: {},
};
export const view = document.body.dataset.view ?? '';
export const absoluteUrls = {
    landing: new URL(authConfig?.landingUrl ?? '/', window.location.origin).toString(),
    login: new URL(authConfig?.loginUrl ?? '/login', window.location.origin).toString(),
    app: new URL(authConfig?.appUrl ?? '/app', window.location.origin).toString(),
};

const authState = {
    recoveryMode: window.location.hash.includes('type=recovery'),
};

if (!authConfig || !authConfig.hasSupabaseConfig) {
    showNotice(view, 'error', 'Completa la configuracion de Supabase antes de usar el acceso.');
    throw new Error('Missing Supabase config.');
}

export const supabase = createClient(authConfig.supabaseUrl, authConfig.supabaseKey, {
    auth: {
        persistSession: true,
        autoRefreshToken: true,
        detectSessionInUrl: true,
        flowType: 'implicit',
    },
});

const oauthProviders = {
    apple: { label: 'Apple', icon: 'fab fa-apple' },
    azure: { label: 'Microsoft', icon: 'fab fa-microsoft' },
    bitbucket: { label: 'Bitbucket', icon: 'fab fa-bitbucket' },
    discord: { label: 'Discord', icon: 'fab fa-discord' },
    facebook: { label: 'Facebook', icon: 'fab fa-facebook' },
    figma: { label: 'Figma', icon: 'fab fa-figma' },
    github: { label: 'GitHub', icon: 'fab fa-github' },
    gitlab: { label: 'GitLab', icon: 'fab fa-gitlab' },
    google: { label: 'Google', icon: 'fab fa-google' },
    kakao: { label: 'Kakao', icon: 'fas fa-comment' },
    linkedin: { label: 'LinkedIn', icon: 'fab fa-linkedin' },
    linkedin_oidc: { label: 'LinkedIn', icon: 'fab fa-linkedin' },
    notion: { label: 'Notion', icon: 'fas fa-note-sticky' },
    slack: { label: 'Slack', icon: 'fab fa-slack' },
    slack_oidc: { label: 'Slack', icon: 'fab fa-slack' },
    spotify: { label: 'Spotify', icon: 'fab fa-spotify' },
    twitch: { label: 'Twitch', icon: 'fab fa-twitch' },
    twitter: { label: 'X / Twitter', icon: 'fab fa-twitter' },
};

if (view === 'login') {
    initLoginAuth();
}

export async function getCurrentSession() {
    const {
        data: { session },
    } = await supabase.auth.getSession();

    return session;
}

export function observeSupabaseAuth(handler) {
    return supabase.auth.onAuthStateChange(handler);
}

export async function signOutUser() {
    return await supabase.auth.signOut();
}

export async function updateUserPassword(password) {
    return await supabase.auth.updateUser({ password });
}

export async function updateUserProfile(payload) {
    return await supabase.auth.updateUser(payload);
}

export function humanizeAuthError(message) {
    const rawMessage = describeErrorMessage(message, 'Ocurrio un error al conectar con Supabase.');
    const normalized = rawMessage.toLowerCase();

    if (normalized.includes('invalid login credentials')) {
        return 'Correo o contrasena incorrectos.';
    }

    if (normalized.includes('email not confirmed')) {
        return 'Tu correo aun no esta confirmado. Reenvia el email de confirmacion y vuelve a intentarlo.';
    }

    if (normalized.includes('user already registered')) {
        return 'Ese correo ya tiene una cuenta registrada.';
    }

    if (normalized.includes('password should be at least')) {
        return 'La contrasena debe tener al menos 8 caracteres.';
    }

    if (normalized.includes('captcha')) {
        return 'Supabase esta pidiendo una validacion adicional. Revisa la configuracion anti-bots del proyecto.';
    }

    return rawMessage;
}

function initLoginAuth() {
    document.addEventListener('click', handleAuthClick);
    bindLoginView();
    observeLoginAuthState();
    void hydrateAuthSettings();
    void bootLoginView();
}

function handleAuthClick(event) {
    const panelTrigger = event.target.closest('[data-auth-target]');

    if (panelTrigger) {
        event.preventDefault();
        switchAuthPanel(panelTrigger.dataset.authTarget);
        return;
    }

    const oauthTrigger = event.target.closest('[data-oauth-provider]');

    if (oauthTrigger) {
        event.preventDefault();
        void startOAuth(oauthTrigger.dataset.oauthProvider, oauthTrigger);
    }
}

function bindLoginView() {
    switchAuthPanel(authState.recoveryMode ? 'reset' : 'signin');

    bindForm('signin-form', handleSignIn);
    bindForm('signup-form', handleSignUp);
    bindForm('magic-form', handleMagicLink);
    bindForm('forgot-form', handleForgotPassword);
    bindForm('resend-form', handleResendConfirmation);
    bindForm('reset-form', handleResetPassword);
}

async function bootLoginView() {
    consumeFlash(view);
    cleanupAuthHash();

    const session = await getCurrentSession();

    if (authState.recoveryMode) {
        showNotice(view, 'info', 'Crea tu nueva contrasena para terminar la recuperacion de acceso.');
        switchAuthPanel('reset');
        return;
    }

    if (session) {
        setFlash('Sesion iniciada correctamente.');
        window.location.href = authConfig.appUrl;
    }
}

function observeLoginAuthState() {
    observeSupabaseAuth((event) => {
        if (event === 'PASSWORD_RECOVERY') {
            authState.recoveryMode = true;
            cleanupAuthHash();
            switchAuthPanel('reset');
            showNotice(view, 'info', 'Ahora puedes definir una nueva contrasena.');
            return;
        }

        if (event === 'SIGNED_IN' && !authState.recoveryMode) {
            cleanupAuthHash();
            setFlash('Sesion iniciada correctamente.');
            window.location.href = authConfig.appUrl;
        }
    });
}

async function hydrateAuthSettings() {
    try {
        const response = await fetch(`${authConfig.supabaseUrl}/auth/v1/settings`, {
            headers: {
                apikey: authConfig.supabaseKey,
                Authorization: `Bearer ${authConfig.supabaseKey}`,
            },
        });

        if (!response.ok) {
            return;
        }

        const settings = await response.json();
        const hint = document.getElementById('auth-settings-hint');

        if (hint) {
            hint.textContent = settings.mailer_autoconfirm
                ? 'El acceso puede quedar listo inmediatamente despues del registro.'
                : 'Al crear la cuenta, el usuario tendra que confirmar su correo antes de entrar.';
        }

        if (settings.disable_signup) {
            const signupTab = document.querySelector('[data-auth-target="signup"][data-auth-tab]');

            if (signupTab) {
                signupTab.classList.add('hidden');
            }
        }

        renderOAuthProviders(settings.external ?? {});
    } catch (error) {
        console.error(error);
    }
}

function renderOAuthProviders(externalSettings) {
    const providerList = document.getElementById('oauth-provider-list');
    const providerSection = document.getElementById('oauth-section');

    if (!providerList || !providerSection) {
        return;
    }

    providerList.innerHTML = '';

    const enabledProviders = Object.entries(externalSettings).filter(([provider, isEnabled]) => {
        return Boolean(isEnabled) && !['email', 'phone', 'anonymous_users'].includes(provider);
    });

    if (enabledProviders.length === 0) {
        providerSection.classList.add('hidden');
        return;
    }

    enabledProviders.forEach(([provider]) => {
        const button = document.createElement('button');
        const meta = oauthProviders[provider] ?? { label: provider, icon: 'fas fa-user' };

        button.type = 'button';
        button.dataset.oauthProvider = provider;
        button.className =
            'btn-font flex w-full items-center justify-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-bold text-white transition duration-300 hover:bg-white/10';
        button.innerHTML = `<i class="${meta.icon}"></i><span>Continuar con ${meta.label}</span>`;

        providerList.appendChild(button);
    });

    providerSection.classList.remove('hidden');
}

function switchAuthPanel(panelName) {
    const panels = document.querySelectorAll('[data-auth-panel]');
    const tabs = document.querySelectorAll('[data-auth-tab]');

    panels.forEach((panel) => {
        panel.classList.toggle('hidden', panel.dataset.authPanel !== panelName);
    });

    tabs.forEach((tab) => {
        const isActive = tab.dataset.authTarget === panelName;
        tab.classList.toggle('bg-white/10', isActive);
        tab.classList.toggle('text-sky-200', isActive);
    });
}

async function startOAuth(provider, button) {
    setButtonBusy(button, true, 'Redirigiendo...');

    const { error } = await supabase.auth.signInWithOAuth({
        provider,
        options: {
            redirectTo: absoluteUrls.login,
        },
    });

    setButtonBusy(button, false);

    if (error) {
        showNotice(view, 'error', humanizeAuthError(error.message));
    }
}

async function handleSignIn(form) {
    const email = form.email.value.trim();
    const password = form.password.value;

    if (!email || !password) {
        showNotice(view, 'error', 'Completa tu correo y tu contrasena para entrar.');
        return;
    }

    syncEmailAcrossForms(email);

    const submitButton = form.querySelector('button[type="submit"]');
    setButtonBusy(submitButton, true, 'Entrando...');

    const { error } = await supabase.auth.signInWithPassword({
        email,
        password,
    });

    setButtonBusy(submitButton, false);

    if (error) {
        showNotice(view, 'error', humanizeAuthError(error.message));
        return;
    }

    setFlash('Sesion iniciada correctamente.');
    window.location.href = authConfig.appUrl;
}

async function handleSignUp(form) {
    const fullName = form.full_name.value.trim();
    const email = form.email.value.trim();
    const password = form.password.value;

    if (!email || !password) {
        showNotice(view, 'error', 'Completa correo y contrasena para crear la cuenta.');
        return;
    }

    syncEmailAcrossForms(email);

    const submitButton = form.querySelector('button[type="submit"]');
    setButtonBusy(submitButton, true, 'Creando...');

    const { data, error } = await supabase.auth.signUp({
        email,
        password,
        options: {
            emailRedirectTo: absoluteUrls.login,
            data: fullName ? { full_name: fullName } : undefined,
        },
    });

    setButtonBusy(submitButton, false);

    if (error) {
        showNotice(view, 'error', humanizeAuthError(error.message));
        return;
    }

    if (data.session) {
        setFlash('Cuenta creada y sesion iniciada.');
        window.location.href = authConfig.appUrl;
        return;
    }

    switchAuthPanel('resend');
    showNotice(view, 'success', 'Cuenta creada. Revisa tu correo y confirma tu email para poder entrar.');
}

async function handleMagicLink(form) {
    const email = form.email.value.trim();

    if (!email) {
        showNotice(view, 'error', 'Escribe el correo al que enviaremos el magic link.');
        return;
    }

    syncEmailAcrossForms(email);

    const submitButton = form.querySelector('button[type="submit"]');
    setButtonBusy(submitButton, true, 'Enviando...');

    const { error } = await supabase.auth.signInWithOtp({
        email,
        options: {
            shouldCreateUser: false,
            emailRedirectTo: absoluteUrls.login,
        },
    });

    setButtonBusy(submitButton, false);

    if (error) {
        showNotice(view, 'error', humanizeAuthError(error.message));
        return;
    }

    showNotice(view, 'success', 'Magic link enviado. Revisa tu correo y abre el enlace para entrar.');
}

async function handleForgotPassword(form) {
    const email = form.email.value.trim();

    if (!email) {
        showNotice(view, 'error', 'Escribe el correo que quieres recuperar.');
        return;
    }

    syncEmailAcrossForms(email);

    const submitButton = form.querySelector('button[type="submit"]');
    setButtonBusy(submitButton, true, 'Enviando...');

    const { error } = await supabase.auth.resetPasswordForEmail(email, {
        redirectTo: absoluteUrls.login,
    });

    setButtonBusy(submitButton, false);

    if (error) {
        showNotice(view, 'error', humanizeAuthError(error.message));
        return;
    }

    showNotice(view, 'success', 'Te enviamos el enlace para cambiar tu contrasena.');
}

async function handleResendConfirmation(form) {
    const email = form.email.value.trim();

    if (!email) {
        showNotice(view, 'error', 'Escribe el correo al que quieres reenviar la confirmacion.');
        return;
    }

    syncEmailAcrossForms(email);

    const submitButton = form.querySelector('button[type="submit"]');
    setButtonBusy(submitButton, true, 'Reenviando...');

    const { error } = await supabase.auth.resend({
        type: 'signup',
        email,
        options: {
            emailRedirectTo: absoluteUrls.login,
        },
    });

    setButtonBusy(submitButton, false);

    if (error) {
        showNotice(view, 'error', humanizeAuthError(error.message));
        return;
    }

    showNotice(view, 'success', 'Correo de confirmacion reenviado. Revisa tu bandeja y spam.');
}

async function handleResetPassword(form) {
    const password = form.password.value;
    const passwordConfirm = form.password_confirm.value;

    if (password.length < 8) {
        showNotice(view, 'error', 'La nueva contrasena debe tener al menos 8 caracteres.');
        return;
    }

    if (password !== passwordConfirm) {
        showNotice(view, 'error', 'Las contrasenas no coinciden.');
        return;
    }

    const submitButton = form.querySelector('button[type="submit"]');
    setButtonBusy(submitButton, true, 'Guardando...');

    const { error } = await updateUserPassword(password);

    setButtonBusy(submitButton, false);

    if (error) {
        showNotice(view, 'error', humanizeAuthError(error.message));
        return;
    }

    authState.recoveryMode = false;
    setFlash('Contrasena actualizada. Ya puedes continuar al panel.');
    window.location.href = authConfig.appUrl;
}

function syncEmailAcrossForms(email) {
    ['signin-email', 'signup-email', 'magic-email', 'forgot-email', 'resend-email'].forEach((id) => {
        const input = document.getElementById(id);

        if (input && !input.value) {
            input.value = email;
        }
    });
}
