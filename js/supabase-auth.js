import { createClient } from 'https://esm.sh/@supabase/supabase-js@2';

const authConfig = window.AISCALER_AUTH_CONFIG ?? null;
const view = document.body.dataset.view ?? '';
const state = {
    recoveryMode: window.location.hash.includes('type=recovery'),
};
const absoluteUrls = {
    landing: new URL(authConfig?.landingUrl ?? '/', window.location.origin).toString(),
    login: new URL(authConfig?.loginUrl ?? '/?view=login', window.location.origin).toString(),
    app: new URL(authConfig?.appUrl ?? '/?view=app', window.location.origin).toString(),
};

if (!authConfig || !authConfig.hasSupabaseConfig) {
    showNotice('error', 'Completa la configuracion de Supabase antes de usar el acceso.');
    throw new Error('Missing Supabase config.');
}

const supabase = createClient(authConfig.supabaseUrl, authConfig.supabaseKey, {
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

document.addEventListener('click', handleGlobalClick);

if (view === 'login') {
    bindLoginView();
}

if (view === 'app') {
    bindAppView();
}

observeAuthState();
void hydrateAuthSettings();
void boot();

function handleGlobalClick(event) {
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
    switchAuthPanel(state.recoveryMode ? 'reset' : 'signin');

    bindForm('signin-form', handleSignIn);
    bindForm('signup-form', handleSignUp);
    bindForm('magic-form', handleMagicLink);
    bindForm('forgot-form', handleForgotPassword);
    bindForm('resend-form', handleResendConfirmation);
    bindForm('reset-form', handleResetPassword);
}

function bindAppView() {
    bindForm('change-password-form', handleChangePassword);

    const logoutButton = document.getElementById('logout-button');

    if (logoutButton) {
        logoutButton.addEventListener('click', async () => {
            setButtonBusy(logoutButton, true, 'Saliendo...');

            const { error } = await supabase.auth.signOut();

            setButtonBusy(logoutButton, false);

            if (error) {
                showNotice('error', humanizeAuthError(error.message));
                return;
            }

            setFlash('Has salido correctamente.');
            window.location.href = authConfig.landingUrl;
        });
    }
}

function bindForm(formId, handler) {
    const form = document.getElementById(formId);

    if (!form) {
        return;
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        void handler(form);
    });
}

async function boot() {
    consumeFlash();

    const {
        data: { session },
    } = await supabase.auth.getSession();

    cleanupAuthHash();

    if (view === 'app') {
        if (!session) {
            setFlash('Inicia sesion para entrar al panel.');
            window.location.href = authConfig.loginUrl;
            return;
        }

        renderAppSession(session);
        return;
    }

    if (view === 'login') {
        if (state.recoveryMode) {
            showNotice('info', 'Crea tu nueva contrasena para terminar la recuperacion de acceso.');
            switchAuthPanel('reset');
            return;
        }

        if (session) {
            setFlash('Sesion iniciada correctamente.');
            window.location.href = authConfig.appUrl;
        }
    }
}

function observeAuthState() {
    supabase.auth.onAuthStateChange((event, session) => {
        if (event === 'PASSWORD_RECOVERY') {
            state.recoveryMode = true;
            cleanupAuthHash();

            if (view === 'login') {
                switchAuthPanel('reset');
                showNotice('info', 'Ahora puedes definir una nueva contrasena.');
            }

            return;
        }

        if (event === 'SIGNED_IN') {
            cleanupAuthHash();

            if (view === 'app' && session) {
                renderAppSession(session);
                return;
            }

            if (view === 'login' && !state.recoveryMode) {
                setFlash('Sesion iniciada correctamente.');
                window.location.href = authConfig.appUrl;
            }

            return;
        }

        if (event === 'USER_UPDATED') {
            cleanupAuthHash();

            if (view === 'app' && session) {
                renderAppSession(session);
            }
        }
    });
}

async function hydrateAuthSettings() {
    if (view !== 'login') {
        return;
    }

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
        showNotice('error', humanizeAuthError(error.message));
    }
}

async function handleSignIn(form) {
    const email = form.email.value.trim();
    const password = form.password.value;

    if (!email || !password) {
        showNotice('error', 'Completa tu correo y tu contrasena para entrar.');
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
        showNotice('error', humanizeAuthError(error.message));
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
        showNotice('error', 'Completa correo y contrasena para crear la cuenta.');
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
        showNotice('error', humanizeAuthError(error.message));
        return;
    }

    if (data.session) {
        setFlash('Cuenta creada y sesion iniciada.');
        window.location.href = authConfig.appUrl;
        return;
    }

    switchAuthPanel('resend');
    showNotice('success', 'Cuenta creada. Revisa tu correo y confirma tu email para poder entrar.');
}

async function handleMagicLink(form) {
    const email = form.email.value.trim();

    if (!email) {
        showNotice('error', 'Escribe el correo al que enviaremos el magic link.');
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
        showNotice('error', humanizeAuthError(error.message));
        return;
    }

    showNotice('success', 'Magic link enviado. Revisa tu correo y abre el enlace para entrar.');
}

async function handleForgotPassword(form) {
    const email = form.email.value.trim();

    if (!email) {
        showNotice('error', 'Escribe el correo que quieres recuperar.');
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
        showNotice('error', humanizeAuthError(error.message));
        return;
    }

    showNotice('success', 'Te enviamos el enlace para cambiar tu contrasena.');
}

async function handleResendConfirmation(form) {
    const email = form.email.value.trim();

    if (!email) {
        showNotice('error', 'Escribe el correo al que quieres reenviar la confirmacion.');
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
        showNotice('error', humanizeAuthError(error.message));
        return;
    }

    showNotice('success', 'Correo de confirmacion reenviado. Revisa tu bandeja y spam.');
}

async function handleResetPassword(form) {
    const password = form.password.value;
    const passwordConfirm = form.password_confirm.value;

    if (password.length < 8) {
        showNotice('error', 'La nueva contrasena debe tener al menos 8 caracteres.');
        return;
    }

    if (password !== passwordConfirm) {
        showNotice('error', 'Las contrasenas no coinciden.');
        return;
    }

    const submitButton = form.querySelector('button[type="submit"]');
    setButtonBusy(submitButton, true, 'Guardando...');

    const { error } = await supabase.auth.updateUser({
        password,
    });

    setButtonBusy(submitButton, false);

    if (error) {
        showNotice('error', humanizeAuthError(error.message));
        return;
    }

    state.recoveryMode = false;
    setFlash('Contrasena actualizada. Ya puedes continuar al panel.');
    window.location.href = authConfig.appUrl;
}

async function handleChangePassword(form) {
    const password = form.password.value;
    const passwordConfirm = form.password_confirm.value;

    if (password.length < 8) {
        showNotice('error', 'La contrasena nueva debe tener al menos 8 caracteres.');
        return;
    }

    if (password !== passwordConfirm) {
        showNotice('error', 'Las contrasenas no coinciden.');
        return;
    }

    const submitButton = form.querySelector('button[type="submit"]');
    setButtonBusy(submitButton, true, 'Actualizando...');

    const { error } = await supabase.auth.updateUser({
        password,
    });

    setButtonBusy(submitButton, false);

    if (error) {
        showNotice('error', humanizeAuthError(error.message));
        return;
    }

    form.reset();
    showNotice('success', 'Tu contrasena se actualizo correctamente.');
}

function renderAppSession(session) {
    const user = session.user;
    const userEmail = document.getElementById('app-user-email');
    const userName = document.getElementById('app-user-name');
    const verificationCopy = document.getElementById('app-verification-copy');
    const statusBadge = document.getElementById('app-status-badge');
    const provider = document.getElementById('app-provider');
    const lastSignIn = document.getElementById('app-last-sign-in');
    const userId = document.getElementById('app-user-id');
    const appShell = document.getElementById('app-shell');
    const appLoading = document.getElementById('app-loading');

    const fullName = user.user_metadata?.full_name?.trim();
    const providers = user.app_metadata?.providers ?? ['email'];
    const isVerified = Boolean(user.email_confirmed_at);

    if (userEmail) {
        userEmail.textContent = user.email ?? 'Cuenta activa';
    }

    if (userName) {
        userName.textContent = fullName || user.email || 'Tu espacio';
    }

    if (verificationCopy) {
        verificationCopy.textContent = isVerified
            ? 'Tu correo esta verificado y la sesion se mantendra lista para devolverte al panel sin pasos extra.'
            : 'Todavia tienes pendiente confirmar tu correo. Puedes seguir trabajando y terminarlo desde tu bandeja de entrada.';
    }

    if (statusBadge) {
        statusBadge.textContent = isVerified ? 'Correo verificado' : 'Correo pendiente';
    }

    if (provider) {
        provider.textContent = providers.join(', ');
    }

    if (lastSignIn) {
        lastSignIn.textContent = formatDate(user.last_sign_in_at);
    }

    if (userId) {
        userId.textContent = user.id;
    }

    if (appLoading) {
        appLoading.classList.add('hidden');
    }

    if (appShell) {
        appShell.classList.remove('hidden');
    }
}

function formatDate(dateString) {
    if (!dateString) {
        return 'Sin registro';
    }

    const date = new Date(dateString);

    return new Intl.DateTimeFormat('es-MX', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

function syncEmailAcrossForms(email) {
    ['signin-email', 'signup-email', 'magic-email', 'forgot-email', 'resend-email'].forEach((id) => {
        const input = document.getElementById(id);

        if (input && !input.value) {
            input.value = email;
        }
    });
}

function setButtonBusy(button, isBusy, loadingText = 'Procesando...') {
    if (!button) {
        return;
    }

    if (!button.dataset.defaultLabel) {
        button.dataset.defaultLabel = button.innerHTML;
    }

    button.disabled = isBusy;
    button.classList.toggle('opacity-70', isBusy);
    button.classList.toggle('cursor-not-allowed', isBusy);
    button.innerHTML = isBusy ? loadingText : button.dataset.defaultLabel;
}

function showNotice(type, message) {
    const notice = view === 'app'
        ? document.getElementById('app-notice')
        : document.getElementById('auth-notice');

    if (!notice) {
        return;
    }

    notice.className = 'mb-6 rounded-2xl px-4 py-3 text-sm font-semibold';

    const palettes = {
        success: 'mb-6 rounded-2xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-sm font-semibold text-emerald-100',
        error: 'mb-6 rounded-2xl border border-red-400/30 bg-red-500/10 px-4 py-3 text-sm font-semibold text-red-100',
        info: 'mb-6 rounded-2xl border border-sky-400/30 bg-sky-500/10 px-4 py-3 text-sm font-semibold text-sky-100',
    };

    notice.className = palettes[type] ?? palettes.info;
    notice.textContent = message;
    notice.classList.remove('hidden');
}

function setFlash(message) {
    sessionStorage.setItem('aiscaler_flash', message);
}

function consumeFlash() {
    const flash = sessionStorage.getItem('aiscaler_flash');

    if (!flash) {
        return;
    }

    sessionStorage.removeItem('aiscaler_flash');
    showNotice('success', flash);
}

function cleanupAuthHash() {
    if (!window.location.hash) {
        return;
    }

    if (
        window.location.hash.includes('access_token')
        || window.location.hash.includes('refresh_token')
        || window.location.hash.includes('type=')
    ) {
        window.history.replaceState({}, document.title, window.location.pathname + window.location.search);
    }
}

function humanizeAuthError(message) {
    const normalized = message.toLowerCase();

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

    return message;
}
