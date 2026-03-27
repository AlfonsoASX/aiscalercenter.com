import { createClient } from 'https://esm.sh/@supabase/supabase-js@2';

const authConfig = window.AISCALER_AUTH_CONFIG ?? null;
const panelConfig = authConfig?.panel ?? {
    bootstrap_admins: [],
    roles: {},
    menus: {},
};
const view = document.body.dataset.view ?? '';
const state = {
    recoveryMode: window.location.hash.includes('type=recovery'),
    role: 'regular',
    activeMenuId: null,
    isBootstrapAdmin: false,
    activeMenuItem: null,
    userContext: null,
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

    const menuTrigger = event.target.closest('[data-menu-id]');

    if (menuTrigger) {
        event.preventDefault();
        selectMenu(menuTrigger.dataset.menuId);
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
    const appFab = document.getElementById('app-fab');

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

    if (appFab) {
        appFab.addEventListener('click', () => {
            if (!state.activeMenuItem) {
                showNotice('info', 'Elige primero un módulo del menú para continuar.');
                return;
            }

            const fabLabel = state.activeMenuItem.fab_label ?? 'Nueva acción';
            showNotice('info', `La acción "${fabLabel}" se conectará aquí dentro de ${state.activeMenuItem.label}.`);
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
    const roleBadge = document.getElementById('app-role-badge');
    const roleCopy = document.getElementById('app-role-copy');
    const roleHelper = document.getElementById('app-role-helper');
    const adminNote = document.getElementById('app-admin-note');
    const menuScope = document.getElementById('app-menu-scope');
    const provider = document.getElementById('app-provider');
    const lastSignIn = document.getElementById('app-last-sign-in');
    const userId = document.getElementById('app-user-id');
    const appShell = document.getElementById('app-shell');
    const appLoading = document.getElementById('app-loading');

    const fullName = user.user_metadata?.full_name?.trim();
    const providers = user.app_metadata?.providers ?? ['email'];
    const isVerified = Boolean(user.email_confirmed_at);
    const role = resolveUserRole(user);
    const roleMeta = getRoleMeta(role);
    const email = user.email ?? 'Cuenta activa';

    state.role = role;
    state.userContext = {
        email,
        providers: providers.join(', '),
        isVerified,
        roleLabel: roleMeta.label ?? 'Usuario',
        roleMenuLabel: roleMeta.menu_label ?? 'Menú',
    };

    if (userEmail) {
        userEmail.textContent = email;
    }

    if (userName) {
        userName.textContent = fullName || email || 'Tu espacio';
    }

    if (verificationCopy) {
        verificationCopy.textContent = isVerified
            ? 'Tu correo esta verificado y la sesion se mantendra lista para devolverte al panel sin pasos extra.'
            : 'Todavia tienes pendiente confirmar tu correo. Puedes seguir trabajando y terminarlo desde tu bandeja de entrada.';
    }

    if (statusBadge) {
        statusBadge.textContent = isVerified ? 'Correo verificado' : 'Correo pendiente';
        statusBadge.className = isVerified
            ? 'md3-chip md3-chip--accent-green'
            : 'md3-chip md3-chip--accent-yellow';
    }

    if (roleBadge) {
        roleBadge.textContent = roleMeta.label ?? 'Usuario';
        roleBadge.className = role === 'admin'
            ? 'md3-chip md3-chip--accent-red'
            : 'md3-chip md3-chip--primary';
    }

    if (roleCopy) {
        roleCopy.textContent = roleMeta.role_copy ?? '';
    }

    if (roleHelper) {
        roleHelper.textContent = roleMeta.helper_copy ?? '';
    }

    if (adminNote) {
        adminNote.textContent = state.isBootstrapAdmin
            ? 'Este usuario coincide con el admin bootstrap inicial. Desde aqui podremos preparar el flujo para promover nuevos administradores.'
            : role === 'admin'
                ? 'Este admin ya puede ver catalogos internos. El siguiente paso es construir el flujo seguro para nombrar mas admins.'
                : 'El usuario regular solo ve el menu operativo. Los catalogos internos y contenido de administracion quedan ocultos.';
    }

    if (menuScope) {
        menuScope.textContent = roleMeta.menu_label ?? 'Menú';
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

    renderMenuForRole(role);
    updateRailFootnote(roleMeta);
}

function resolveUserRole(user) {
    const email = normalizeEmail(user.email);
    const bootstrapAdmins = (panelConfig.bootstrap_admins ?? []).map(normalizeEmail);
    const appMetadataRole = user.app_metadata?.role;
    const appMetadataRoles = user.app_metadata?.roles;

    state.isBootstrapAdmin = bootstrapAdmins.includes(email);

    if (state.isBootstrapAdmin) {
        return 'admin';
    }

    if (appMetadataRole === 'admin') {
        return 'admin';
    }

    if (Array.isArray(appMetadataRoles) && appMetadataRoles.includes('admin')) {
        return 'admin';
    }

    return 'regular';
}

function renderMenuForRole(role) {
    const railMenu = document.getElementById('app-rail-nav');
    const bottomMenu = document.getElementById('app-bottom-nav');
    const items = getMenuItems(role);

    if (!railMenu || !bottomMenu) {
        return;
    }

    railMenu.innerHTML = '';
    bottomMenu.innerHTML = '';
    bottomMenu.style.gridTemplateColumns = `repeat(${Math.max(items.length, 1)}, minmax(0, 1fr))`;

    items.forEach((item) => {
        railMenu.appendChild(createMenuButton(item, 'rail'));
        bottomMenu.appendChild(createMenuButton(item, 'bottom'));
    });

    const defaultItem = items.find((item) => item.id === state.activeMenuId) ?? items[0] ?? null;

    if (defaultItem) {
        selectMenu(defaultItem.id);
    }
}

function selectMenu(menuId) {
    const items = getMenuItems(state.role);
    const selectedItem = items.find((item) => item.id === menuId);

    if (!selectedItem) {
        return;
    }

    state.activeMenuId = menuId;
    state.activeMenuItem = selectedItem;

    document.querySelectorAll('[data-menu-id]').forEach((button) => {
        const isActive = button.dataset.menuId === menuId;
        button.classList.toggle('is-active', isActive);
        button.setAttribute('aria-current', isActive ? 'page' : 'false');
    });

    const sectionKicker = document.getElementById('app-section-kicker');
    const sectionTitle = document.getElementById('app-section-title');
    const sectionDescription = document.getElementById('app-section-description');
    const moduleEmptyTitle = document.getElementById('app-module-empty-title');
    const moduleEmptyCopy = document.getElementById('app-module-empty-copy');
    const featureGrid = document.getElementById('app-feature-grid');
    const moduleIcon = document.getElementById('app-module-icon');
    const metaChips = document.getElementById('app-meta-chips');
    const searchCopy = document.getElementById('app-search-copy');
    const searchSubcopy = document.getElementById('app-search-subcopy');
    const fabIcon = document.getElementById('app-fab-icon');
    const fabLabel = document.getElementById('app-fab-label');

    if (sectionKicker) {
        sectionKicker.textContent = selectedItem.kicker;
        sectionKicker.className = `md3-chip ${accentChipClass(selectedItem.accent)}`;
    }

    if (sectionTitle) {
        sectionTitle.textContent = selectedItem.label;
    }

    if (sectionDescription) {
        sectionDescription.textContent = selectedItem.description;
    }

    if (moduleEmptyTitle) {
        moduleEmptyTitle.textContent = selectedItem.placeholder_title;
    }

    if (moduleEmptyCopy) {
        moduleEmptyCopy.textContent = selectedItem.placeholder_copy;
    }

    if (searchCopy) {
        searchCopy.textContent = selectedItem.label;
    }

    if (searchSubcopy) {
        searchSubcopy.textContent = selectedItem.description;
    }

    if (fabIcon) {
        fabIcon.textContent = selectedItem.icon ?? 'add';
    }

    if (fabLabel) {
        fabLabel.textContent = selectedItem.fab_label ?? 'Nueva acción';
    }

    if (moduleIcon) {
        const accent = getAccentPalette(selectedItem.accent);
        moduleIcon.innerHTML = `<span class="material-symbols-rounded">${selectedItem.icon ?? 'dashboard'}</span>`;
        moduleIcon.style.background = accent.surface;
        moduleIcon.style.color = accent.text;
        moduleIcon.style.border = `1px solid ${accent.border}`;
    }

    if (featureGrid) {
        featureGrid.innerHTML = '';

        (selectedItem.feature_highlights ?? []).forEach((feature) => {
            const card = document.createElement('div');

            card.className = 'md3-feature-card';
            card.innerHTML = `
                <strong>${feature.title}</strong>
                <p>${feature.copy}</p>
            `;

            featureGrid.appendChild(card);
        });
    }

    if (metaChips) {
        metaChips.innerHTML = '';

        [
            {
                label: state.userContext?.roleLabel ?? 'Usuario',
                icon: state.role === 'admin' ? 'shield_person' : 'person',
                tone: state.role === 'admin' ? 'red' : 'primary',
            },
            {
                label: state.userContext?.isVerified ? 'Correo verificado' : 'Correo pendiente',
                icon: state.userContext?.isVerified ? 'verified' : 'mark_email_unread',
                tone: state.userContext?.isVerified ? 'green' : 'yellow',
            },
            {
                label: state.userContext?.providers ?? 'email',
                icon: 'alternate_email',
                tone: 'default',
            },
            {
                label: selectedItem.fab_label ?? 'Nueva acción',
                icon: selectedItem.icon ?? 'add',
                tone: selectedItem.accent ?? 'default',
            },
        ].forEach((chip) => {
            metaChips.appendChild(createMetaChip(chip));
        });
    }
}

function createMenuButton(item, mode) {
    const button = document.createElement('button');
    const label = item.short_label ?? item.label;

    button.type = 'button';
    button.dataset.menuId = item.id;
    button.className = mode === 'bottom'
        ? 'md3-nav-button md3-bottom-button'
        : 'md3-nav-button md3-rail-button';
    button.setAttribute('aria-label', item.label);
    button.innerHTML = `
        <span class="material-symbols-rounded">${item.icon ?? 'dashboard'}</span>
        <span class="md3-nav-label">${label}</span>
    `;

    return button;
}

function createMetaChip(chip) {
    const element = document.createElement('span');

    element.className = `md3-chip ${chipToneClass(chip.tone)}`;
    element.innerHTML = `
        <span class="material-symbols-rounded">${chip.icon}</span>
        <span>${chip.label}</span>
    `;

    return element;
}

function getRoleMeta(role) {
    return panelConfig.roles?.[role] ?? panelConfig.roles?.regular ?? {};
}

function getMenuItems(role) {
    return panelConfig.menus?.[role] ?? panelConfig.menus?.regular ?? [];
}

function updateRailFootnote(roleMeta) {
    const footnote = document.getElementById('app-rail-footnote');

    if (!footnote) {
        return;
    }

    footnote.textContent = roleMeta.helper_copy ?? 'Navegación lista para crecer.';
}

function accentChipClass(accent) {
    return chipToneClass(accent === 'blue' ? 'primary' : accent);
}

function chipToneClass(tone) {
    const map = {
        primary: 'md3-chip--primary',
        blue: 'md3-chip--primary',
        red: 'md3-chip--accent-red',
        yellow: 'md3-chip--accent-yellow',
        green: 'md3-chip--accent-green',
        default: '',
    };

    return map[tone] ?? '';
}

function getAccentPalette(accent) {
    const map = {
        blue: {
            surface: 'rgba(47, 124, 239, 0.12)',
            text: 'var(--md-primary-strong)',
            border: 'rgba(47, 124, 239, 0.16)',
        },
        red: {
            surface: 'rgba(234, 67, 53, 0.12)',
            text: '#b3261e',
            border: 'rgba(234, 67, 53, 0.16)',
        },
        yellow: {
            surface: 'rgba(251, 188, 4, 0.16)',
            text: '#835b00',
            border: 'rgba(251, 188, 4, 0.2)',
        },
        green: {
            surface: 'rgba(52, 168, 83, 0.14)',
            text: '#0c6a33',
            border: 'rgba(52, 168, 83, 0.18)',
        },
        default: {
            surface: 'var(--md-surface-container-high)',
            text: 'var(--md-primary-strong)',
            border: 'var(--md-outline)',
        },
    };

    return map[accent] ?? map.default;
}

function normalizeEmail(value) {
    return String(value ?? '').trim().toLowerCase();
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

    const appPalettes = {
        success: 'md3-notice mb-5 border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm font-semibold text-emerald-900 dark:text-emerald-100',
        error: 'md3-notice mb-5 border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm font-semibold text-red-900 dark:text-red-100',
        info: 'md3-notice mb-5 border border-sky-500/20 bg-sky-500/10 px-4 py-3 text-sm font-semibold text-sky-900 dark:text-sky-100',
    };
    const authPalettes = {
        success: 'mb-6 rounded-2xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-sm font-semibold text-emerald-100',
        error: 'mb-6 rounded-2xl border border-red-400/30 bg-red-500/10 px-4 py-3 text-sm font-semibold text-red-100',
        info: 'mb-6 rounded-2xl border border-sky-400/30 bg-sky-500/10 px-4 py-3 text-sm font-semibold text-sky-100',
    };
    const palettes = view === 'app' ? appPalettes : authPalettes;

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
