import { BLOG_ENTRIES_SECTION_ID, createBlogEntriesModule } from './modules/blog-entries/index.js';
import { COURSES_SECTION_ID, createCoursesModule } from './modules/courses/index.js';
import {
    authConfig,
    panelConfig,
    view,
    supabase,
    getCurrentSession,
    observeSupabaseAuth,
    signOutUser,
    updateUserPassword,
    updateUserProfile,
    humanizeAuthError,
} from './supabase-auth.js';
import {
    bindForm,
    cleanupAuthHash,
    consumeFlash,
    escapeHtml,
    normalizeEmail,
    setButtonBusy,
    setFlash,
    showNotice,
} from './shared/ui.js';

const state = {
    role: 'regular',
    activeMenuId: null,
    isBootstrapAdmin: false,
    activeMenuItem: null,
    userContext: null,
    currentUser: null,
    sidebarCollapsed: false,
    sidebarOpen: false,
    userMenuOpen: false,
    intentionalLogout: false,
};

const blogEntriesModule = createBlogEntriesModule({
    supabase,
    getCurrentUser: () => state.currentUser,
    getUserContext: () => state.userContext,
    showNotice: (type, message) => notify(type, message),
    humanizeError: (message) => humanizeBlogError(message),
});

const coursesModule = createCoursesModule({
    supabase,
    getCurrentUser: () => state.currentUser,
    getUserContext: () => state.userContext,
    showNotice: (type, message) => notify(type, message),
    humanizeError: (message) => humanizeCourseError(message),
});

if (view === 'app') {
    initPanelApp();
}

function initPanelApp() {
    document.addEventListener('click', handlePanelClick);
    bindAppView();
    observePanelAuthState();
    void bootPanelApp();
}

function notify(type, message) {
    showNotice(view, type, message);
}

function observePanelAuthState() {
    observeSupabaseAuth((event, session) => {
        if ((event === 'SIGNED_IN' || event === 'USER_UPDATED' || event === 'TOKEN_REFRESHED') && session) {
            cleanupAuthHash();
            renderAppSession(session);
            return;
        }

        if (event === 'SIGNED_OUT' && !state.intentionalLogout) {
            setFlash('Inicia sesion para entrar al panel.');
            window.location.href = authConfig.loginUrl;
        }
    });
}

async function bootPanelApp() {
    consumeFlash(view);
    cleanupAuthHash();

    const session = await getCurrentSession();

    if (!session) {
        setFlash('Inicia sesion para entrar al panel.');
        window.location.href = authConfig.loginUrl;
        return;
    }

    renderAppSession(session);
}

function handlePanelClick(event) {
    const menuTrigger = event.target.closest('[data-menu-id]');

    if (menuTrigger) {
        event.preventDefault();
        selectMenu(menuTrigger.dataset.menuId);
        state.userMenuOpen = false;
        applyUserMenuState();
        return;
    }

    const userMenuTrigger = event.target.closest('#user-menu-toggle');

    if (userMenuTrigger) {
        event.preventDefault();
        state.userMenuOpen = !state.userMenuOpen;
        applyUserMenuState();
        return;
    }

    if (
        state.userMenuOpen
        && !event.target.closest('#user-menu-panel')
        && !event.target.closest('#user-menu-toggle')
    ) {
        state.userMenuOpen = false;
        applyUserMenuState();
    }
}

function bindAppView() {
    const logoutButton = document.getElementById('logout-button');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
    const sidebarBackdrop = document.getElementById('app-sidebar-backdrop');

    state.sidebarCollapsed = window.localStorage.getItem('aiscaler_sidebar_collapsed') === 'true';
    applySidebarState();
    applyUserMenuState();

    if (logoutButton) {
        logoutButton.addEventListener('click', async () => {
            setButtonBusy(logoutButton, true, 'Saliendo...');
            state.intentionalLogout = true;

            const { error } = await signOutUser();

            setButtonBusy(logoutButton, false);

            if (error) {
                state.intentionalLogout = false;
                notify('error', humanizePanelError(error.message));
                return;
            }

            setFlash('Has salido correctamente.');
            window.location.href = authConfig.landingUrl;
        });
    }

    const toggleSidebar = () => {
        if (window.innerWidth >= 1024) {
            state.sidebarCollapsed = !state.sidebarCollapsed;
            window.localStorage.setItem('aiscaler_sidebar_collapsed', String(state.sidebarCollapsed));
            applySidebarState();
            return;
        }

        state.sidebarOpen = !state.sidebarOpen;
        applySidebarState();
    };

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
    }

    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', toggleSidebar);
    }

    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', () => {
            state.sidebarOpen = false;
            applySidebarState();
        });
    }

    window.addEventListener('resize', applySidebarState);
}

async function handleChangePassword(form) {
    const password = form.password.value;
    const passwordConfirm = form.password_confirm.value;

    if (password.length < 8) {
        notify('error', 'La contrasena nueva debe tener al menos 8 caracteres.');
        return;
    }

    if (password !== passwordConfirm) {
        notify('error', 'Las contrasenas no coinciden.');
        return;
    }

    const submitButton = form.querySelector('button[type="submit"]');
    setButtonBusy(submitButton, true, 'Actualizando...');

    const { error } = await updateUserPassword(password);

    setButtonBusy(submitButton, false);

    if (error) {
        notify('error', humanizePanelError(error.message));
        return;
    }

    form.reset();
    notify('success', 'Tu contrasena se actualizo correctamente.');
}

async function handleProfileUpdate(form) {
    if (!state.currentUser) {
        notify('error', 'No encontramos el usuario activo para actualizar el perfil.');
        return;
    }

    const fullName = form.full_name.value.trim();
    const email = form.email.value.trim().toLowerCase();
    const currentEmail = normalizeEmail(state.currentUser.email);
    const currentName = String(state.currentUser.user_metadata?.full_name ?? '').trim();
    const submitButton = form.querySelector('button[type="submit"]');
    const payload = {};

    if (!email) {
        notify('error', 'El correo electronico es obligatorio.');
        return;
    }

    if (email !== currentEmail) {
        payload.email = email;
    }

    if (fullName !== currentName) {
        payload.data = {
            ...(state.currentUser.user_metadata ?? {}),
            full_name: fullName,
        };
    }

    if (Object.keys(payload).length === 0) {
        notify('info', 'No hay cambios pendientes en tu perfil.');
        return;
    }

    setButtonBusy(submitButton, true, 'Guardando...');

    const { error } = await updateUserProfile(payload);

    setButtonBusy(submitButton, false);

    if (error) {
        notify('error', humanizePanelError(error.message));
        return;
    }

    state.currentUser = {
        ...state.currentUser,
        email: payload.email ?? state.currentUser.email,
        user_metadata: payload.data
            ? {
                ...(state.currentUser.user_metadata ?? {}),
                ...payload.data,
            }
            : state.currentUser.user_metadata,
    };

    notify(
        'success',
        email !== currentEmail
            ? 'Perfil actualizado. Revisa tu correo para confirmar el cambio de email si Supabase lo solicita.'
            : 'Perfil actualizado correctamente.',
    );
}

function renderAppSession(session) {
    const user = session.user;
    const appShell = document.getElementById('app-shell');
    const appLoading = document.getElementById('app-loading');
    const userName = document.getElementById('app-user-name');
    const userPanelName = document.getElementById('app-user-panel-name');
    const userEmail = document.getElementById('app-user-email');
    const role = resolveUserRole(user);
    const displayName = String(user.user_metadata?.full_name ?? '').trim() || (user.email ?? 'Usuario');

    state.currentUser = user;
    state.role = role;
    state.userContext = {
        email: user.email ?? '',
        roleLabel: getRoleMeta(role).label ?? 'Usuario',
        displayName,
    };

    if (userName) {
        userName.textContent = displayName;
    }

    if (userPanelName) {
        userPanelName.textContent = displayName;
    }

    if (userEmail) {
        userEmail.textContent = user.email ?? 'Cuenta sin correo';
    }

    if (appLoading) {
        appLoading.classList.add('hidden');
    }

    if (appShell) {
        appShell.classList.remove('hidden');
    }

    renderMenuForRole(role);
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
    const items = getMenuItems(role);
    const panelItems = getPanelItems(role);

    if (!railMenu) {
        return;
    }

    railMenu.innerHTML = '';
    renderSections(panelItems);
    bindForm('settings-profile-form', handleProfileUpdate);
    bindForm('settings-password-form', handleChangePassword);
    blogEntriesModule.bind();
    coursesModule.bind();

    items.forEach((item) => {
        railMenu.appendChild(createMenuButton(item));
    });

    const requestedMenuId = getRequestedMenuId();
    const defaultItem = panelItems.find((item) => item.id === requestedMenuId)
        ?? panelItems.find((item) => item.id === state.activeMenuId)
        ?? panelItems.find((item) => item.id === getDashboardItem().id)
        ?? null;

    if (defaultItem) {
        selectMenu(defaultItem.id);
    }
}

function selectMenu(menuId) {
    const items = getPanelItems(state.role);
    const selectedItem = items.find((item) => item.id === menuId);

    if (!selectedItem) {
        return;
    }

    state.activeMenuId = menuId;
    state.activeMenuItem = selectedItem;
    applyWorkspaceAccent(selectedItem);

    document.querySelectorAll('[data-menu-id]').forEach((button) => {
        const isActive = button.dataset.menuId === menuId;
        button.classList.toggle('is-active', isActive);
        button.setAttribute('aria-current', isActive ? 'page' : 'false');
    });

    document.querySelectorAll('[data-panel-section]').forEach((section) => {
        section.classList.toggle('is-active', section.dataset.panelSection === menuId);
    });

    const currentTitle = document.getElementById('app-current-title');

    if (currentTitle) {
        currentTitle.textContent = selectedItem.label;
    }

    window.history.replaceState({}, document.title, `${window.location.pathname}${window.location.search}#${menuId}`);

    if (window.innerWidth < 1024) {
        state.sidebarOpen = false;
        applySidebarState();
    }
}

function createMenuButton(item) {
    const button = document.createElement('button');
    const hoverLabel = item.hover_label ?? item.label;

    button.type = 'button';
    button.dataset.menuId = item.id;
    button.className = 'workspace-nav-button';
    button.setAttribute('aria-label', item.label);
    button.setAttribute('title', item.label);
    button.innerHTML = `
        <span class="workspace-nav-icon" aria-hidden="true">
            <img src="${item.icon_path}" alt="">
        </span>
        <span class="workspace-nav-copy">
            <span class="workspace-nav-text workspace-nav-text--default">${item.label}</span>
            <span class="workspace-nav-text workspace-nav-text--hover">${hoverLabel}</span>
        </span>
    `;

    return button;
}

function getRoleMeta(role) {
    return panelConfig.roles?.[role] ?? panelConfig.roles?.regular ?? {};
}

function getMenuItems(role) {
    return panelConfig.menus?.[role] ?? panelConfig.menus?.regular ?? [];
}

function getDashboardItem() {
    return panelConfig.dashboard ?? {
        id: 'inicio',
        label: 'Inicio',
        section_title: 'Inicio',
    };
}

function getAccountSectionItem() {
    return panelConfig.account_section ?? {
        id: 'configuracion',
        label: 'Configuracion',
        section_title: 'Configuracion',
    };
}

function getPanelItems(role) {
    return [
        getDashboardItem(),
        ...getMenuItems(role),
        getAccountSectionItem(),
    ];
}

function renderSections(items) {
    const sections = document.getElementById('app-sections');

    if (!sections) {
        return;
    }

    sections.innerHTML = '';

    items.forEach((item) => {
        const section = document.createElement('section');

        section.id = `section-${item.id}`;
        section.dataset.panelSection = item.id;
        section.className = 'workspace-section';
        section.innerHTML = item.id === BLOG_ENTRIES_SECTION_ID
            ? blogEntriesModule.renderSection(item)
            : item.id === COURSES_SECTION_ID
            ? coursesModule.renderSection(item)
            : item.id === getDashboardItem().id
            ? renderDashboardSection(item)
            : item.id === getAccountSectionItem().id
            ? renderSettingsSection(item)
            : `
                <div class="workspace-section-card">
                    <h2>${item.section_title ?? item.label}</h2>
                </div>
            `;

        sections.appendChild(section);
    });
}

function renderDashboardSection(item) {
    const cards = getMenuItems(state.role).map((menuItem) => {
        return `
            <button type="button" class="workspace-dashboard-card" data-menu-id="${menuItem.id}">
                <img src="${menuItem.icon_path}" alt="">
                <span>
                    <strong>${menuItem.label}</strong>
                    <p>Ir a ${menuItem.label.toLowerCase()}.</p>
                </span>
            </button>
        `;
    }).join('');

    return `
        <div class="workspace-section-card">
            <h2>${item.section_title ?? item.label}</h2>
            <p class="workspace-section-subtitle">
                Esta es tu seccion principal. Desde aqui puedes saltar rapidamente a cualquiera de las secciones disponibles segun tu rol.
            </p>
            <div class="workspace-dashboard-grid">
                ${cards}
            </div>
        </div>
    `;
}

function renderSettingsSection(item) {
    const fullName = escapeHtml(String(state.currentUser?.user_metadata?.full_name ?? ''));
    const email = escapeHtml(String(state.currentUser?.email ?? ''));

    return `
        <div class="workspace-section-card">
            <h2>${item.section_title ?? item.label}</h2>
            <p class="workspace-section-subtitle">
                Desde aqui el usuario puede mantener su perfil al dia y actualizar su contrasena sin salir del panel.
            </p>

            <div class="workspace-form-grid">
                <section class="workspace-form-card">
                    <h3>Perfil</h3>
                    <p class="workspace-form-copy">
                        Cambia tu nombre y tu correo. Si modificas el email, Supabase puede pedirte confirmacion por correo.
                    </p>

                    <form id="settings-profile-form" class="workspace-form">
                        <div class="workspace-field-block">
                            <label for="settings-full-name" class="workspace-field-label">Nombre</label>
                            <input
                                id="settings-full-name"
                                name="full_name"
                                type="text"
                                class="workspace-field"
                                placeholder="Tu nombre"
                                value="${fullName}"
                            >
                        </div>

                        <div class="workspace-field-block">
                            <label for="settings-email" class="workspace-field-label">Correo electronico</label>
                            <input
                                id="settings-email"
                                name="email"
                                type="email"
                                required
                                class="workspace-field"
                                placeholder="tu@empresa.com"
                                value="${email}"
                            >
                        </div>

                        <button type="submit" class="workspace-primary-button">
                            <span class="material-symbols-rounded">save</span>
                            <span>Guardar perfil</span>
                        </button>
                    </form>
                </section>

                <section class="workspace-form-card">
                    <h3>Contrasena</h3>
                    <p class="workspace-form-copy">
                        Cambia tu contrasena aqui mismo. Debe tener al menos 8 caracteres.
                    </p>

                    <form id="settings-password-form" class="workspace-form">
                        <div class="workspace-field-block">
                            <label for="settings-password" class="workspace-field-label">Nueva contrasena</label>
                            <input
                                id="settings-password"
                                name="password"
                                type="password"
                                minlength="8"
                                required
                                class="workspace-field"
                                placeholder="Minimo 8 caracteres"
                            >
                        </div>

                        <div class="workspace-field-block">
                            <label for="settings-password-confirm" class="workspace-field-label">Confirmar contrasena</label>
                            <input
                                id="settings-password-confirm"
                                name="password_confirm"
                                type="password"
                                minlength="8"
                                required
                                class="workspace-field"
                                placeholder="Repite la contraseña"
                            >
                        </div>

                        <button type="submit" class="workspace-primary-button">
                            <span class="material-symbols-rounded">lock_reset</span>
                            <span>Actualizar contrasena</span>
                        </button>
                    </form>

                    <p class="workspace-helper-text">
                        El cambio de contrasena usa Supabase Auth directamente, asi que aplica a tu proximo inicio de sesion.
                    </p>
                </section>
            </div>
        </div>
    `;
}

function getRequestedMenuId() {
    return window.location.hash.replace('#', '').trim();
}

function applyWorkspaceAccent(item) {
    const layout = document.getElementById('app-layout');

    if (!layout) {
        return;
    }

    const accent = resolveMenuAccent(item?.color);

    layout.style.setProperty('--workspace-accent', accent.hex);
    layout.style.setProperty('--workspace-accent-rgb', accent.rgb);
}

function resolveMenuAccent(color) {
    const fallbackHex = '#2F7CEF';
    const normalizedHex = normalizeHexColor(color) ?? fallbackHex;

    return {
        hex: normalizedHex,
        rgb: hexToRgbTriplet(normalizedHex),
    };
}

function normalizeHexColor(value) {
    const raw = String(value ?? '').trim();

    if (!raw) {
        return null;
    }

    const normalized = raw.startsWith('#') ? raw.slice(1) : raw;

    if (!/^[0-9a-f]{3}([0-9a-f]{3})?$/i.test(normalized)) {
        return null;
    }

    if (normalized.length === 3) {
        return `#${normalized.split('').map((character) => `${character}${character}`).join('').toUpperCase()}`;
    }

    return `#${normalized.toUpperCase()}`;
}

function hexToRgbTriplet(hex) {
    const sanitized = hex.replace('#', '');
    const red = Number.parseInt(sanitized.slice(0, 2), 16);
    const green = Number.parseInt(sanitized.slice(2, 4), 16);
    const blue = Number.parseInt(sanitized.slice(4, 6), 16);

    return `${red}, ${green}, ${blue}`;
}

function applySidebarState() {
    const layout = document.getElementById('app-layout');
    const sidebar = document.getElementById('app-sidebar');
    const backdrop = document.getElementById('app-sidebar-backdrop');
    const isDesktop = window.innerWidth >= 1024;

    if (layout) {
        layout.classList.toggle('is-sidebar-collapsed', isDesktop && state.sidebarCollapsed);
    }

    if (sidebar) {
        sidebar.classList.toggle('is-open', !isDesktop && state.sidebarOpen);
    }

    if (backdrop) {
        backdrop.classList.toggle('hidden', isDesktop || !state.sidebarOpen);
    }
}

function applyUserMenuState() {
    const userMenuButton = document.getElementById('user-menu-toggle');
    const userMenuPanel = document.getElementById('user-menu-panel');

    if (userMenuButton) {
        userMenuButton.setAttribute('aria-expanded', state.userMenuOpen ? 'true' : 'false');
    }

    if (userMenuPanel) {
        userMenuPanel.classList.toggle('hidden', !state.userMenuOpen);
    }
}

function humanizePanelError(message) {
    return humanizeAuthError(message);
}

function humanizeBlogError(message) {
    const normalized = String(message ?? '').toLowerCase();

    if (normalized.includes('duplicate key value') || normalized.includes('blog_entries_slug_key')) {
        return 'Ya existe un articulo con ese slug. Usa uno diferente.';
    }

    if (normalized.includes('pgrst205') || normalized.includes('could not find the table')) {
        return 'La tabla blog_entries aun no existe en Supabase. Ejecuta el archivo supabase/blog_entries_schema.sql.';
    }

    if (normalized.includes('bucket not found') || normalized.includes('blog-images')) {
        return 'Falta configurar el bucket blog-images en Supabase Storage. Ejecuta el archivo supabase/blog_storage_setup.sql.';
    }

    if (normalized.includes('row-level security') || normalized.includes('storage.objects')) {
        return 'Supabase Storage aun no tiene las politicas correctas para el blog. Ejecuta el archivo supabase/blog_storage_setup.sql.';
    }

    return humanizeAuthError(message);
}

function humanizeCourseError(message) {
    const normalized = String(message ?? '').toLowerCase();

    if (normalized.includes('duplicate key value') || normalized.includes('courses_slug_key')) {
        return 'Ya existe un curso con ese slug. Usa uno diferente.';
    }

    if ((normalized.includes('pgrst205') || normalized.includes('could not find the table')) && normalized.includes('courses')) {
        return 'La tabla courses aun no existe en Supabase. Ejecuta el archivo supabase/courses_schema.sql.';
    }

    if (normalized.includes('bucket not found') || normalized.includes('course-assets')) {
        return 'Falta configurar el bucket course-assets en Supabase Storage. Ejecuta el archivo supabase/course_storage_setup.sql.';
    }

    if (normalized.includes('row-level security') || normalized.includes('storage.objects')) {
        return 'Supabase Storage aun no tiene las politicas correctas para cursos. Ejecuta el archivo supabase/course_storage_setup.sql.';
    }

    return humanizeAuthError(message);
}
