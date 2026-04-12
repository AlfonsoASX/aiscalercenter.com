import { describeErrorMessage, escapeHtml, normalizeEmail } from '../../shared/ui.js';
import {
    STORAGE_SCOPES,
    USER_FILES_STORAGE_BUCKET,
    buildUserStoragePath,
} from '../../shared/storage.js';

export const PROJECT_SETTINGS_SECTION_ID = 'configuracion-proyecto';

const PROJECT_IMAGE_ACCEPT = 'image/jpeg,image/png,image/webp,image/gif,image/svg+xml,image/avif';
const MEMBER_ROLE_OPTIONS = [
    { value: 'admin', label: 'Administrador', description: 'Puede editar configuracion, miembros y herramientas del proyecto.' },
    { value: 'member', label: 'Colaborador', description: 'Puede trabajar dentro del proyecto sin administrar accesos.' },
];
const MEMBER_STATUS_OPTIONS = [
    { value: 'active', label: 'Activo' },
    { value: 'disabled', label: 'Suspendido' },
];

export function createProjectSettingsModule({
    supabase,
    getCurrentUser,
    getActiveProject,
    onProjectUpdated,
    onProjectDeleted,
    onProjectChanged,
    showNotice,
    humanizeError,
}) {
    const state = {
        bound: false,
        projectId: '',
        loading: false,
        savingProject: false,
        updatingProjectStatus: false,
        deletingProject: false,
        searchingUser: false,
        addingUser: false,
        savingMemberId: '',
        removingMemberId: '',
        notice: null,
        lookupNotice: null,
        project: null,
        projectDraft: createEmptyProjectDraft(),
        members: [],
        lookupEmail: '',
        lookupRole: 'member',
        lookupResult: null,
        logoFile: null,
        coverFile: null,
        logoPreviewUrl: '',
        coverPreviewUrl: '',
        clearLogo: false,
        clearCover: false,
    };

    function renderSection(item) {
        return `
            <section id="project-settings-module" class="workspace-section-card project-settings-module" aria-label="${escapeHtml(item.section_title ?? item.label ?? 'Configuracion del proyecto')}">
                <div class="project-settings-head">
                    <div>
                        <h2>${escapeHtml(item.section_title ?? item.label ?? 'Configuracion del proyecto')}</h2>
                        <p class="workspace-section-subtitle">
                            Centraliza aqui la identidad del proyecto, su imagen principal y todos los accesos del equipo.
                        </p>
                    </div>

                    <button type="button" class="projects-icon-button" data-project-settings-refresh aria-label="Actualizar configuracion del proyecto">
                        <span class="material-symbols-rounded">refresh</span>
                    </button>
                </div>

                <div id="project-settings-notice" class="project-settings-notice hidden"></div>
                <div id="project-settings-shell" class="project-settings-shell"></div>
            </section>
        `;
    }

    function bind() {
        const root = document.getElementById('project-settings-module');

        if (!(root instanceof HTMLElement) || state.bound) {
            renderModule();
            return;
        }

        state.bound = true;
        root.addEventListener('click', handleClick);
        root.addEventListener('submit', handleSubmit);
        root.addEventListener('change', handleChange);
        root.addEventListener('input', handleInput);
        setProject(getActiveProject?.() ?? null);
    }

    function setProject(project) {
        const nextProjectId = String(project?.id ?? '').trim();
        const projectChanged = nextProjectId !== state.projectId;
        const projectMetaChanged = !projectChanged && (
            String(project?.name ?? '') !== String(state.project?.name ?? '')
            || String(project?.logo_url ?? '') !== String(state.project?.logo_url ?? '')
        );

        state.projectId = nextProjectId;

        if (!projectChanged && !projectMetaChanged && state.project) {
            return;
        }

        if (!nextProjectId) {
            resetStateForProject();
            renderModule();
            return;
        }

        if (state.bound) {
            void loadProjectSettings();
        }
    }

    async function loadProjectSettings() {
        if (!state.projectId) {
            resetStateForProject();
            renderModule();
            return;
        }

        state.loading = true;
        state.notice = null;
        renderModule();

        try {
            const [projectResult, membersResult] = await Promise.all([
                supabase
                    .from('projects')
                    .select(`
                        id,
                        owner_user_id,
                        name,
                        logo_url,
                        logo_storage_path,
                        cover_image_url,
                        cover_image_storage_path,
                        description,
                        company_type,
                        company_goal,
                        status,
                        metadata,
                        updated_at
                    `)
                    .eq('id', state.projectId)
                    .is('deleted_at', null)
                    .single(),
                supabase.rpc('get_project_member_directory', {
                    p_project_id: state.projectId,
                }),
            ]);

            if (projectResult.error) {
                throw projectResult.error;
            }

            if (membersResult.error) {
                throw membersResult.error;
            }

            state.project = normalizeProject(projectResult.data);
            state.members = normalizeMembers(membersResult.data);
            syncDraftWithProject(state.project);
            state.lookupNotice = null;
            state.lookupResult = null;
        } catch (error) {
            resetStateForProject();
            state.notice = {
                type: 'error',
                message: humanizeProjectSettingsError(error),
            };
        } finally {
            state.loading = false;
            renderModule();
        }
    }

    async function reloadMembers() {
        if (!state.projectId) {
            state.members = [];
            renderMembersPane();
            return;
        }

        const { data, error } = await supabase.rpc('get_project_member_directory', {
            p_project_id: state.projectId,
        });

        if (error) {
            throw error;
        }

        state.members = normalizeMembers(data);
        renderMembersPane();
    }

    function renderModule() {
        const shell = document.getElementById('project-settings-shell');

        if (!(shell instanceof HTMLElement)) {
            return;
        }

        renderNotice();

        if (!state.projectId) {
            shell.innerHTML = `
                <div class="project-settings-empty">
                    <span class="material-symbols-rounded">folder_managed</span>
                    <strong>Selecciona primero un proyecto.</strong>
                    <p>Despues de abrir un proyecto, aqui podras configurar sus datos, accesos e imagen principal.</p>
                </div>
            `;
            return;
        }

        if (state.loading) {
            shell.innerHTML = `
                <div class="project-settings-loading">
                    <span class="material-symbols-rounded project-settings-spin">progress_activity</span>
                    <strong>Cargando configuracion del proyecto...</strong>
                </div>
            `;
            return;
        }

        if (!state.project) {
            shell.innerHTML = `
                <div class="project-settings-empty">
                    <span class="material-symbols-rounded">warning</span>
                    <strong>No fue posible cargar este proyecto.</strong>
                    <p>Revisa que el esquema de proyectos este actualizado en Supabase.</p>
                </div>
            `;
            return;
        }

        shell.innerHTML = `
            <div class="project-settings-layout">
                <section id="project-settings-general-pane" class="workspace-form-card project-settings-card"></section>
                <aside id="project-settings-members-pane" class="workspace-form-card project-settings-card"></aside>
                <section id="project-settings-danger-pane" class="workspace-form-card project-settings-card project-settings-card--danger"></section>
            </div>
        `;

        renderGeneralPane();
        renderMembersPane();
        renderDangerPane();
    }

    function renderNotice() {
        const notice = document.getElementById('project-settings-notice');

        if (!(notice instanceof HTMLElement)) {
            return;
        }

        notice.className = `project-settings-notice ${state.notice ? `project-settings-notice--${state.notice.type}` : 'hidden'}`;
        notice.textContent = state.notice?.message ?? '';
    }

    function renderGeneralPane() {
        const pane = document.getElementById('project-settings-general-pane');

        if (!(pane instanceof HTMLElement) || !state.project) {
            return;
        }

        const logoSource = getImagePreviewSource('logo');
        const coverSource = getImagePreviewSource('cover');

        pane.innerHTML = `
            <div class="project-settings-card-head">
                <div>
                    <h3>Identidad del proyecto</h3>
                    <p class="workspace-form-copy">
                        Define la informacion base, el logo y la imagen principal que representaran este proyecto.
                    </p>
                </div>
                <span class="project-settings-updated">Actualizado ${escapeHtml(formatDate(state.project.updated_at))}</span>
            </div>

            <form id="project-settings-general-form" class="workspace-form">
                <div class="project-settings-media-grid">
                    <label class="workspace-field-block">
                        <span class="workspace-field-label">Logo</span>
                        <div class="project-settings-upload-card">
                            <div class="project-settings-logo-preview ${logoSource ? '' : 'is-empty'}" data-project-logo-preview>
                                ${logoSource
                                    ? `<img src="${escapeHtml(logoSource)}" alt="">`
                                    : '<span class="material-symbols-rounded">image</span>'}
                            </div>
                            <div class="project-settings-upload-actions">
                                <input id="project-settings-logo-input" name="logo" type="file" accept="${PROJECT_IMAGE_ACCEPT}">
                                <small class="project-settings-helper">Se guarda en el storage central <code>user-files</code>.</small>
                                <button type="button" class="project-settings-link" data-project-image-clear="logo">Quitar logo</button>
                            </div>
                        </div>
                    </label>

                    <label class="workspace-field-block">
                        <span class="workspace-field-label">Imagen principal</span>
                        <div class="project-settings-upload-card">
                            <div class="project-settings-cover-preview ${coverSource ? '' : 'is-empty'}" data-project-cover-preview>
                                ${coverSource
                                    ? `<img src="${escapeHtml(coverSource)}" alt="">`
                                    : '<span class="material-symbols-rounded">landscape</span>'}
                            </div>
                            <div class="project-settings-upload-actions">
                                <input id="project-settings-cover-input" name="cover" type="file" accept="${PROJECT_IMAGE_ACCEPT}">
                                <small class="project-settings-helper">Esta imagen sirve como portada visual del proyecto.</small>
                                <button type="button" class="project-settings-link" data-project-image-clear="cover">Quitar imagen principal</button>
                            </div>
                        </div>
                    </label>
                </div>

                <div class="project-settings-grid">
                    <label class="workspace-field-block">
                        <span class="workspace-field-label">Nombre</span>
                        <input name="name" type="text" class="workspace-field" required value="${escapeHtml(state.projectDraft.name)}" placeholder="Nombre del proyecto">
                    </label>

                    <label class="workspace-field-block">
                        <span class="workspace-field-label">Estado</span>
                        <select name="status" class="workspace-field">
                            ${renderStatusOptions(state.projectDraft.status)}
                        </select>
                    </label>

                    <label class="workspace-field-block">
                        <span class="workspace-field-label">Tipo de empresa</span>
                        <input name="company_type" type="text" class="workspace-field" value="${escapeHtml(state.projectDraft.company_type)}" placeholder="Ej. Retail, SaaS, restaurante">
                    </label>

                    <label class="workspace-field-block">
                        <span class="workspace-field-label">Objetivo de la empresa</span>
                        <input name="company_goal" type="text" class="workspace-field" value="${escapeHtml(state.projectDraft.company_goal)}" placeholder="Ej. validar mercado, vender mas, automatizar">
                    </label>

                    <label class="workspace-field-block project-settings-grid-span">
                        <span class="workspace-field-label">Sitio web</span>
                        <input name="website_url" type="url" class="workspace-field" value="${escapeHtml(state.projectDraft.website_url)}" placeholder="https://empresa.com">
                    </label>
                </div>

                <label class="workspace-field-block">
                    <span class="workspace-field-label">Descripcion</span>
                    <textarea name="description" class="workspace-field project-settings-textarea" rows="4" placeholder="Describe el proyecto y su contexto">${escapeHtml(state.projectDraft.description)}</textarea>
                </label>

                <label class="workspace-field-block">
                    <span class="workspace-field-label">Notas internas</span>
                    <textarea name="internal_notes" class="workspace-field project-settings-textarea" rows="4" placeholder="Criterios, restricciones o detalles internos del proyecto">${escapeHtml(state.projectDraft.internal_notes)}</textarea>
                </label>

                <button type="submit" class="workspace-primary-button" ${state.savingProject ? 'disabled' : ''}>
                    <span class="material-symbols-rounded">${state.savingProject ? 'progress_activity' : 'save'}</span>
                    <span>${state.savingProject ? 'Guardando...' : 'Guardar configuracion'}</span>
                </button>
            </form>
        `;
    }

    function renderMembersPane() {
        const pane = document.getElementById('project-settings-members-pane');

        if (!(pane instanceof HTMLElement) || !state.project) {
            return;
        }

        pane.innerHTML = `
            <div class="project-settings-card-head">
                <div>
                    <h3>Accesos y usuarios</h3>
                    <p class="workspace-form-copy">
                        Solo puedes agregar usuarios que ya tengan cuenta. Aqui tambien controlas el nivel de acceso de cada persona.
                    </p>
                </div>
                <span class="project-settings-counter">${state.members.length} ${state.members.length === 1 ? 'usuario' : 'usuarios'}</span>
            </div>

            <form id="project-member-search-form" class="project-settings-search-form">
                <label class="workspace-field-block">
                    <span class="workspace-field-label">Correo del usuario</span>
                    <input
                        name="email"
                        type="email"
                        class="workspace-field"
                        value="${escapeHtml(state.lookupEmail)}"
                        placeholder="usuario@empresa.com"
                        autocomplete="off"
                    >
                </label>

                <label class="workspace-field-block">
                    <span class="workspace-field-label">Nivel de acceso inicial</span>
                    <select name="role" class="workspace-field">
                        ${renderMemberRoleOptions(state.lookupRole)}
                    </select>
                </label>

                <button type="submit" class="workspace-primary-button" ${state.searchingUser ? 'disabled' : ''}>
                    <span class="material-symbols-rounded">${state.searchingUser ? 'progress_activity' : 'person_search'}</span>
                    <span>${state.searchingUser ? 'Buscando...' : 'Buscar usuario'}</span>
                </button>
            </form>

            ${renderLookupNotice()}
            ${renderLookupResult()}

            <div class="project-settings-member-list">
                ${state.members.map((member) => renderMemberCard(member)).join('')}
            </div>
        `;
    }

    function renderDangerPane() {
        const pane = document.getElementById('project-settings-danger-pane');

        if (!(pane instanceof HTMLElement) || !state.project) {
            return;
        }

        const isArchived = state.projectDraft.status === 'archived';

        pane.innerHTML = `
            <div class="project-settings-card-head">
                <div>
                    <h3>Zona de riesgo</h3>
                    <p class="workspace-form-copy">
                        Usa estas acciones solo cuando realmente quieras dejar de trabajar con este proyecto o quitarlo del panel.
                    </p>
                </div>
            </div>

            <div class="project-settings-danger-grid">
                <button
                    type="button"
                    class="projects-create-button project-settings-danger-button"
                    data-project-status-toggle
                    ${state.updatingProjectStatus ? 'disabled' : ''}
                >
                    <span class="material-symbols-rounded">${state.updatingProjectStatus ? 'progress_activity' : (isArchived ? 'unarchive' : 'inventory_2')}</span>
                    <span>${state.updatingProjectStatus ? 'Actualizando...' : (isArchived ? 'Reactivar proyecto' : 'Archivar proyecto')}</span>
                </button>

                <button
                    type="button"
                    class="projects-danger-button project-settings-danger-button"
                    data-project-delete
                    ${state.deletingProject ? 'disabled' : ''}
                >
                    <span class="material-symbols-rounded">${state.deletingProject ? 'progress_activity' : 'delete'}</span>
                    <span>${state.deletingProject ? 'Eliminando...' : 'Eliminar proyecto'}</span>
                </button>
            </div>
        `;
    }

    function renderLookupNotice() {
        if (!state.lookupNotice) {
            return '';
        }

        return `
            <div class="project-settings-inline-notice project-settings-inline-notice--${escapeHtml(state.lookupNotice.type)}">
                ${escapeHtml(state.lookupNotice.message)}
            </div>
        `;
    }

    function renderLookupResult() {
        if (!state.lookupResult) {
            return '';
        }

        const alreadyMember = state.members.some((member) => {
            return (member.user_id && member.user_id === state.lookupResult.user_id)
                || normalizeEmail(member.email) === normalizeEmail(state.lookupResult.email);
        });

        return `
            <div class="project-settings-member-card project-settings-member-card--lookup">
                <div class="project-settings-member-head">
                    ${renderMemberAvatar(state.lookupResult.full_name, state.lookupResult.avatar_url)}
                    <div class="project-settings-member-copy">
                        <strong>${escapeHtml(state.lookupResult.full_name)}</strong>
                        <span>${escapeHtml(state.lookupResult.email)}</span>
                    </div>
                </div>

                <p class="project-settings-member-detail">
                    ${escapeHtml(getRoleDescription(state.lookupRole))}
                </p>

                <button
                    type="button"
                    class="workspace-primary-button"
                    data-project-member-add
                    ${state.addingUser || alreadyMember ? 'disabled' : ''}
                >
                    <span class="material-symbols-rounded">${state.addingUser ? 'progress_activity' : 'person_add'}</span>
                    <span>${alreadyMember ? 'Ya tiene acceso' : (state.addingUser ? 'Agregando...' : 'Agregar al proyecto')}</span>
                </button>
            </div>
        `;
    }

    function renderMemberCard(member) {
        const isOwner = member.is_owner;
        const isBusy = state.savingMemberId === member.membership_id || state.removingMemberId === member.membership_id;

        return `
            <form class="project-settings-member-card" data-member-form>
                <input type="hidden" name="member_id" value="${escapeHtml(member.membership_id)}">
                <div class="project-settings-member-head">
                    ${renderMemberAvatar(member.full_name, member.avatar_url)}
                    <div class="project-settings-member-copy">
                        <strong>${escapeHtml(member.full_name)}</strong>
                        <span>${escapeHtml(member.email || 'Usuario sin correo')}</span>
                    </div>
                </div>

                <div class="project-settings-chip-row">
                    <span class="project-settings-chip ${member.role === 'owner' ? 'is-owner' : ''}">
                        ${escapeHtml(resolveRoleLabel(member.role))}
                    </span>
                    <span class="project-settings-chip">
                        ${escapeHtml(resolveStatusLabel(member.status))}
                    </span>
                </div>

                <div class="project-settings-member-grid">
                    <label class="workspace-field-block">
                        <span class="workspace-field-label">Nivel de acceso</span>
                        <select name="role" class="workspace-field" ${isOwner ? 'disabled' : ''}>
                            ${renderMemberRoleOptions(member.role)}
                        </select>
                    </label>

                    <label class="workspace-field-block">
                        <span class="workspace-field-label">Estado</span>
                        <select name="status" class="workspace-field" ${isOwner ? 'disabled' : ''}>
                            ${renderMemberStatusOptions(member.status)}
                        </select>
                    </label>
                </div>

                <p class="project-settings-member-detail">
                    ${escapeHtml(getRoleDescription(member.role))}
                </p>

                <div class="project-settings-member-actions">
                    <button type="submit" class="projects-create-button" ${isBusy || isOwner ? 'disabled' : ''}>
                        <span class="material-symbols-rounded">${state.savingMemberId === member.membership_id ? 'progress_activity' : 'save'}</span>
                        <span>${state.savingMemberId === member.membership_id ? 'Guardando...' : (isOwner ? 'Propietario' : 'Guardar')}</span>
                    </button>

                    ${isOwner
                        ? ''
                        : `
                            <button type="button" class="projects-danger-button" data-project-member-remove="${escapeHtml(member.membership_id)}" ${isBusy ? 'disabled' : ''}>
                                <span class="material-symbols-rounded">${state.removingMemberId === member.membership_id ? 'progress_activity' : 'person_remove'}</span>
                                <span>${state.removingMemberId === member.membership_id ? 'Quitando...' : 'Quitar acceso'}</span>
                            </button>
                        `}
                </div>
            </form>
        `;
    }

    function renderMemberAvatar(name, avatarUrl) {
        const normalizedAvatar = String(avatarUrl ?? '').trim();

        if (normalizedAvatar !== '') {
            return `
                <span class="project-settings-member-avatar">
                    <img src="${escapeHtml(normalizedAvatar)}" alt="">
                </span>
            `;
        }

        return `
            <span class="project-settings-member-avatar is-fallback">
                ${escapeHtml((String(name ?? 'U').trim().slice(0, 1) || 'U').toUpperCase())}
            </span>
        `;
    }

    function handleClick(event) {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (target.closest('[data-project-settings-refresh]')) {
            event.preventDefault();
            void loadProjectSettings();
            return;
        }

        if (target.closest('[data-project-member-add]')) {
            event.preventDefault();
            void addLookupUserToProject();
            return;
        }

        const removeButton = target.closest('[data-project-member-remove]');

        if (removeButton instanceof HTMLElement) {
            event.preventDefault();
            void removeMember(String(removeButton.dataset.projectMemberRemove ?? ''));
            return;
        }

        const clearButton = target.closest('[data-project-image-clear]');

        if (clearButton instanceof HTMLElement) {
            event.preventDefault();
            clearProjectImage(String(clearButton.dataset.projectImageClear ?? ''));
            return;
        }

        if (target.closest('[data-project-status-toggle]')) {
            event.preventDefault();
            void toggleProjectStatus();
            return;
        }

        if (target.closest('[data-project-delete]')) {
            event.preventDefault();
            void deleteProject();
        }
    }

    function handleSubmit(event) {
        const form = event.target;

        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        event.preventDefault();

        if (form.id === 'project-settings-general-form') {
            void saveProjectSettings();
            return;
        }

        if (form.id === 'project-member-search-form') {
            void searchRegisteredUser(form);
            return;
        }

        if (form.matches('[data-member-form]')) {
            void saveMemberAccess(form);
        }
    }

    function handleChange(event) {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (target instanceof HTMLInputElement && target.id === 'project-settings-logo-input') {
            handleImageInput('logo', target.files?.[0] ?? null);
            return;
        }

        if (target instanceof HTMLInputElement && target.id === 'project-settings-cover-input') {
            handleImageInput('cover', target.files?.[0] ?? null);
            return;
        }

        if (target instanceof HTMLSelectElement && target.name === 'role' && target.form?.id === 'project-member-search-form') {
            state.lookupRole = String(target.value ?? 'member');
            state.lookupResult = state.lookupResult
                ? {
                    ...state.lookupResult,
                }
                : state.lookupResult;
            renderMembersPane();
            return;
        }

        if (target.form?.id === 'project-settings-general-form'
            && (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement || target instanceof HTMLSelectElement)) {
            updateDraftField(target.name, target.value);
            return;
        }

        if (target.form?.id === 'project-member-search-form' && target instanceof HTMLInputElement && target.name === 'email') {
            state.lookupEmail = normalizeEmail(target.value);

            if (state.lookupEmail !== normalizeEmail(state.lookupResult?.email)) {
                state.lookupResult = null;
                state.lookupNotice = null;
                renderMembersPane();
            }
        }
    }

    function handleInput(event) {
        const target = event.target;

        if (!(target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement || target instanceof HTMLSelectElement)) {
            return;
        }

        if (target.form?.id === 'project-settings-general-form') {
            updateDraftField(target.name, target.value);
            return;
        }

        if (target.form?.id === 'project-member-search-form' && target instanceof HTMLInputElement && target.name === 'email') {
            state.lookupEmail = normalizeEmail(target.value);
        }
    }

    function updateDraftField(name, value) {
        const normalizedName = String(name ?? '').trim();

        if (!(normalizedName in state.projectDraft)) {
            return;
        }

        state.projectDraft = {
            ...state.projectDraft,
            [normalizedName]: String(value ?? ''),
        };
    }

    async function saveProjectSettings() {
        const currentUser = getCurrentUser?.();

        if (!currentUser?.id || !state.project) {
            setModuleNotice('error', 'No encontramos el proyecto activo para guardar.');
            return;
        }

        const name = String(state.projectDraft.name ?? '').trim();

        if (name === '') {
            setModuleNotice('error', 'El proyecto necesita un nombre.');
            return;
        }

        state.savingProject = true;
        renderGeneralPane();

        try {
            const logoAsset = await resolveProjectAssetUpload(currentUser.id, 'logo', state.logoFile, state.clearLogo, {
                url: state.project.logo_url,
                path: state.project.logo_storage_path,
            });
            const coverAsset = await resolveProjectAssetUpload(currentUser.id, 'cover', state.coverFile, state.clearCover, {
                url: state.project.cover_image_url,
                path: state.project.cover_image_storage_path,
            });
            const metadata = {
                ...(state.project.metadata ?? {}),
                website_url: normalizeOptionalField(state.projectDraft.website_url),
                internal_notes: normalizeOptionalField(state.projectDraft.internal_notes),
            };
            const payload = {
                name,
                logo_url: logoAsset.url,
                logo_storage_path: logoAsset.path,
                cover_image_url: coverAsset.url,
                cover_image_storage_path: coverAsset.path,
                description: normalizeOptionalField(state.projectDraft.description),
                company_type: normalizeOptionalField(state.projectDraft.company_type),
                company_goal: normalizeOptionalField(state.projectDraft.company_goal),
                status: state.projectDraft.status === 'archived' ? 'archived' : 'active',
                metadata,
            };
            const { data, error } = await supabase
                .from('projects')
                .update(payload)
                .eq('id', state.project.id)
                .select(`
                    id,
                    owner_user_id,
                    name,
                    logo_url,
                    logo_storage_path,
                    cover_image_url,
                    cover_image_storage_path,
                    description,
                    company_type,
                    company_goal,
                    status,
                    metadata,
                    updated_at
                `)
                .single();

            if (error) {
                throw error;
            }

            releasePreview('logo');
            releasePreview('cover');
            state.logoFile = null;
            state.coverFile = null;
            state.clearLogo = false;
            state.clearCover = false;
            state.project = normalizeProject(data);
            syncDraftWithProject(state.project);
            setModuleNotice('success', 'La configuracion del proyecto se guardo correctamente.');
            onProjectUpdated?.(state.project);
            onProjectChanged?.();
            renderGeneralPane();
            renderDangerPane();
        } catch (error) {
            setModuleNotice('error', humanizeProjectSettingsError(error));
            renderGeneralPane();
        } finally {
            state.savingProject = false;
            renderGeneralPane();
        }
    }

    async function searchRegisteredUser(form) {
        const email = normalizeEmail(form.email.value);

        state.lookupEmail = email;
        state.lookupRole = String(form.role.value ?? 'member');
        state.lookupResult = null;
        state.lookupNotice = null;

        if (!isValidEmail(email)) {
            state.lookupNotice = {
                type: 'error',
                message: 'Escribe un correo valido para buscar al usuario.',
            };
            renderMembersPane();
            return;
        }

        state.searchingUser = true;
        renderMembersPane();

        try {
            const { data, error } = await supabase.rpc('find_registered_user_by_email', {
                p_email: email,
            });

            if (error) {
                throw error;
            }

            const user = Array.isArray(data) ? data[0] : data;

            if (!user || String(user.user_id ?? '').trim() === '') {
                state.lookupNotice = {
                    type: 'error',
                    message: 'Ese correo todavia no tiene una cuenta registrada dentro de la plataforma.',
                };
                renderMembersPane();
                return;
            }

            state.lookupResult = normalizeLookupUser(user);

            if (state.members.some((member) => {
                return (member.user_id && member.user_id === state.lookupResult.user_id)
                    || normalizeEmail(member.email) === normalizeEmail(state.lookupResult.email);
            })) {
                state.lookupNotice = {
                    type: 'info',
                    message: 'Ese usuario ya forma parte del proyecto.',
                };
            } else {
                state.lookupNotice = {
                    type: 'success',
                    message: 'Usuario encontrado. Ya puedes agregarlo al proyecto.',
                };
            }
        } catch (error) {
            state.lookupNotice = {
                type: 'error',
                message: humanizeProjectSettingsError(error),
            };
        } finally {
            state.searchingUser = false;
            renderMembersPane();
        }
    }

    async function addLookupUserToProject() {
        const currentUser = getCurrentUser?.();

        if (!currentUser?.id || !state.projectId || !state.lookupResult) {
            return;
        }

        state.addingUser = true;
        renderMembersPane();

        try {
            const { error } = await supabase
                .from('project_members')
                .insert({
                    project_id: state.projectId,
                    user_id: state.lookupResult.user_id,
                    invited_email: state.lookupResult.email,
                    role: state.lookupRole === 'admin' ? 'admin' : 'member',
                    status: 'active',
                    invited_by: currentUser.id,
                });

            if (error) {
                throw error;
            }

            await reloadMembers();
            state.lookupNotice = {
                type: 'success',
                message: 'Usuario agregado al proyecto.',
            };
            state.lookupResult = null;
            state.lookupEmail = '';
            onProjectChanged?.();
            renderMembersPane();
        } catch (error) {
            state.lookupNotice = {
                type: 'error',
                message: humanizeProjectSettingsError(error),
            };
            renderMembersPane();
        } finally {
            state.addingUser = false;
            renderMembersPane();
        }
    }

    async function saveMemberAccess(form) {
        const memberId = String(form.member_id.value ?? '').trim();
        const member = state.members.find((item) => item.membership_id === memberId);

        if (!member || member.is_owner) {
            return;
        }

        const roleField = form.querySelector('[name="role"]');
        const statusField = form.querySelector('[name="status"]');
        const role = roleField instanceof HTMLSelectElement ? String(roleField.value ?? member.role) : member.role;
        const status = statusField instanceof HTMLSelectElement ? String(statusField.value ?? member.status) : member.status;

        if (role === member.role && status === member.status) {
            setModuleNotice('info', 'No hay cambios pendientes para ese usuario.');
            return;
        }

        state.savingMemberId = memberId;
        renderMembersPane();

        try {
            const { error } = await supabase
                .from('project_members')
                .update({
                    role,
                    status,
                })
                .eq('id', memberId);

            if (error) {
                throw error;
            }

            await reloadMembers();
            onProjectChanged?.();
            setModuleNotice('success', 'Permisos del usuario actualizados.');
        } catch (error) {
            setModuleNotice('error', humanizeProjectSettingsError(error));
        } finally {
            state.savingMemberId = '';
            renderMembersPane();
        }
    }

    async function removeMember(memberId) {
        const member = state.members.find((item) => item.membership_id === memberId);

        if (!member || member.is_owner) {
            return;
        }

        const confirmed = window.confirm(`Vas a quitar el acceso de ${member.full_name}. Esta accion no se puede deshacer.`);

        if (!confirmed) {
            return;
        }

        state.removingMemberId = memberId;
        renderMembersPane();

        try {
            const { error } = await supabase
                .from('project_members')
                .delete()
                .eq('id', memberId);

            if (error) {
                throw error;
            }

            await reloadMembers();
            onProjectChanged?.();
            setModuleNotice('success', 'Acceso eliminado correctamente.');
        } catch (error) {
            setModuleNotice('error', humanizeProjectSettingsError(error));
        } finally {
            state.removingMemberId = '';
            renderMembersPane();
        }
    }

    async function toggleProjectStatus() {
        if (!state.project) {
            return;
        }

        state.updatingProjectStatus = true;
        renderDangerPane();

        try {
            const nextStatus = state.project.status === 'archived' ? 'active' : 'archived';
            const { data, error } = await supabase
                .from('projects')
                .update({ status: nextStatus })
                .eq('id', state.project.id)
                .select(`
                    id,
                    owner_user_id,
                    name,
                    logo_url,
                    logo_storage_path,
                    cover_image_url,
                    cover_image_storage_path,
                    description,
                    company_type,
                    company_goal,
                    status,
                    metadata,
                    updated_at
                `)
                .single();

            if (error) {
                throw error;
            }

            state.project = normalizeProject(data);
            syncDraftWithProject(state.project);
            setModuleNotice('success', nextStatus === 'archived' ? 'Proyecto archivado.' : 'Proyecto reactivado.');
            onProjectUpdated?.(state.project);
            onProjectChanged?.();
            renderGeneralPane();
        } catch (error) {
            setModuleNotice('error', humanizeProjectSettingsError(error));
        } finally {
            state.updatingProjectStatus = false;
            renderDangerPane();
        }
    }

    async function deleteProject() {
        if (!state.project) {
            return;
        }

        const confirmed = window.confirm('¿Eliminar este proyecto? Se ocultara del panel para todos sus miembros.');

        if (!confirmed) {
            return;
        }

        state.deletingProject = true;
        renderDangerPane();

        try {
            const { error } = await supabase
                .from('projects')
                .update({ deleted_at: new Date().toISOString() })
                .eq('id', state.project.id);

            if (error) {
                throw error;
            }

            setModuleNotice('success', 'Proyecto eliminado.');
            resetStateForProject();
            onProjectDeleted?.();
        } catch (error) {
            setModuleNotice('error', humanizeProjectSettingsError(error));
        } finally {
            state.deletingProject = false;
            renderDangerPane();
        }
    }

    function handleImageInput(type, file) {
        const isLogo = type === 'logo';
        const input = document.getElementById(isLogo ? 'project-settings-logo-input' : 'project-settings-cover-input');

        if (!(file instanceof File)) {
            return;
        }

        if (!file.type.startsWith('image/')) {
            setModuleNotice('error', 'Solo puedes subir imagenes para la configuracion del proyecto.');

            if (input instanceof HTMLInputElement) {
                input.value = '';
            }
            return;
        }

        releasePreview(type);

        if (isLogo) {
            state.logoFile = file;
            state.clearLogo = false;
            state.logoPreviewUrl = URL.createObjectURL(file);
            updateImagePreview('logo');
            return;
        }

        state.coverFile = file;
        state.clearCover = false;
        state.coverPreviewUrl = URL.createObjectURL(file);
        updateImagePreview('cover');
    }

    function clearProjectImage(type) {
        const isLogo = type === 'logo';
        const input = document.getElementById(isLogo ? 'project-settings-logo-input' : 'project-settings-cover-input');

        if (input instanceof HTMLInputElement) {
            input.value = '';
        }

        releasePreview(type);

        if (isLogo) {
            state.logoFile = null;
            state.clearLogo = true;
        } else {
            state.coverFile = null;
            state.clearCover = true;
        }

        renderGeneralPane();
    }

    async function resolveProjectAssetUpload(userId, type, file, shouldClear, fallback) {
        if (shouldClear) {
            return { url: '', path: '' };
        }

        if (!(file instanceof File)) {
            return fallback;
        }

        const extension = getFileExtension(file.name, file.type);
        const filePath = buildUserStoragePath(
            userId,
            STORAGE_SCOPES.projects,
            'settings',
            type,
            `${cryptoRandomId()}.${extension}`
        );
        const bucket = supabase.storage.from(USER_FILES_STORAGE_BUCKET);
        const { error } = await bucket.upload(filePath, file, {
            cacheControl: '3600',
            upsert: false,
            contentType: file.type || undefined,
        });

        if (error) {
            throw error;
        }

        const { data } = bucket.getPublicUrl(filePath);

        return {
            url: data.publicUrl,
            path: filePath,
        };
    }

    function setModuleNotice(type, message) {
        state.notice = { type, message };
        renderNotice();
        showNotice?.(type, message);
    }

    function syncDraftWithProject(project) {
        state.projectDraft = {
            name: String(project?.name ?? ''),
            description: String(project?.description ?? ''),
            company_type: String(project?.company_type ?? ''),
            company_goal: String(project?.company_goal ?? ''),
            status: String(project?.status ?? 'active'),
            website_url: String(project?.metadata?.website_url ?? ''),
            internal_notes: String(project?.metadata?.internal_notes ?? ''),
        };
    }

    function resetStateForProject() {
        releasePreview('logo');
        releasePreview('cover');
        state.project = null;
        state.projectDraft = createEmptyProjectDraft();
        state.members = [];
        state.lookupEmail = '';
        state.lookupRole = 'member';
        state.lookupResult = null;
        state.lookupNotice = null;
        state.logoFile = null;
        state.coverFile = null;
        state.clearLogo = false;
        state.clearCover = false;
    }

    function releasePreview(type) {
        if (type === 'logo' && state.logoPreviewUrl.startsWith('blob:')) {
            URL.revokeObjectURL(state.logoPreviewUrl);
        }

        if (type === 'cover' && state.coverPreviewUrl.startsWith('blob:')) {
            URL.revokeObjectURL(state.coverPreviewUrl);
        }

        if (type === 'logo') {
            state.logoPreviewUrl = '';
            return;
        }

        state.coverPreviewUrl = '';
    }

    function updateImagePreview(type) {
        const preview = document.querySelector(type === 'logo' ? '[data-project-logo-preview]' : '[data-project-cover-preview]');
        const source = getImagePreviewSource(type);

        if (!(preview instanceof HTMLElement)) {
            return;
        }

        preview.classList.toggle('is-empty', source === '');
        preview.innerHTML = source === ''
            ? `<span class="material-symbols-rounded">${type === 'logo' ? 'image' : 'landscape'}</span>`
            : `<img src="${escapeHtml(source)}" alt="">`;
    }

    function getImagePreviewSource(type) {
        if (type === 'logo') {
            if (state.logoPreviewUrl !== '') {
                return state.logoPreviewUrl;
            }

            if (state.clearLogo) {
                return '';
            }

            return String(state.project?.logo_url ?? '');
        }

        if (state.coverPreviewUrl !== '') {
            return state.coverPreviewUrl;
        }

        if (state.clearCover) {
            return '';
        }

        return String(state.project?.cover_image_url ?? '');
    }

    function humanizeProjectSettingsError(error) {
        const raw = describeErrorMessage(error, 'No fue posible actualizar la configuracion del proyecto.');
        const normalized = raw.toLowerCase();

        if (normalized.includes('find_registered_user_by_email')
            || normalized.includes('get_project_member_directory')
            || normalized.includes('user_profiles')
            || normalized.includes('cover_image_url')
            || normalized.includes('cover_image_storage_path')
            || normalized.includes('could not find the table')
            || normalized.includes('pgrst205')
            || normalized.includes('pgrst202')) {
            return 'Falta actualizar la estructura de proyectos en Supabase. Ejecuta de nuevo supabase/projects_schema.sql.';
        }

        if (normalized.includes('duplicate key value') || normalized.includes('project_members_project_user_unique') || normalized.includes('project_members_project_email_unique')) {
            return 'Ese usuario ya tiene acceso a este proyecto.';
        }

        if (normalized.includes('bucket') || normalized.includes('storage') || normalized.includes('storage.objects')) {
            return 'Falta configurar el almacenamiento central user-files. Ejecuta supabase/user_files_storage_setup.sql.';
        }

        if (normalized.includes('row-level security')) {
            return 'Supabase bloqueo esta operacion por permisos. Reaplica supabase/projects_schema.sql para actualizar las politicas del proyecto.';
        }

        return typeof humanizeError === 'function' ? humanizeError(raw) : raw;
    }

    return {
        renderSection,
        bind,
        setProject,
        reload: loadProjectSettings,
    };
}

function createEmptyProjectDraft() {
    return {
        name: '',
        description: '',
        company_type: '',
        company_goal: '',
        status: 'active',
        website_url: '',
        internal_notes: '',
    };
}

function normalizeProject(project) {
    const metadata = isPlainObject(project?.metadata) ? project.metadata : {};

    return {
        id: String(project?.id ?? ''),
        owner_user_id: String(project?.owner_user_id ?? ''),
        name: String(project?.name ?? 'Proyecto'),
        logo_url: String(project?.logo_url ?? ''),
        logo_storage_path: String(project?.logo_storage_path ?? ''),
        cover_image_url: String(project?.cover_image_url ?? ''),
        cover_image_storage_path: String(project?.cover_image_storage_path ?? ''),
        description: String(project?.description ?? ''),
        company_type: String(project?.company_type ?? ''),
        company_goal: String(project?.company_goal ?? ''),
        status: String(project?.status ?? 'active'),
        metadata,
        updated_at: String(project?.updated_at ?? ''),
    };
}

function normalizeMembers(rows) {
    if (!Array.isArray(rows)) {
        return [];
    }

    return rows.map((row) => {
        return {
            membership_id: String(row?.membership_id ?? row?.id ?? ''),
            user_id: String(row?.user_id ?? ''),
            email: String(row?.email ?? row?.invited_email ?? '').toLowerCase(),
            full_name: String(row?.full_name ?? '').trim() || 'Usuario',
            avatar_url: String(row?.avatar_url ?? '').trim(),
            role: String(row?.role ?? 'member'),
            status: String(row?.status ?? 'active'),
            is_owner: Boolean(row?.is_owner) || String(row?.role ?? '') === 'owner',
            updated_at: String(row?.updated_at ?? ''),
        };
    });
}

function normalizeLookupUser(user) {
    return {
        user_id: String(user?.user_id ?? ''),
        email: String(user?.email ?? '').toLowerCase(),
        full_name: String(user?.full_name ?? '').trim() || 'Usuario',
        avatar_url: String(user?.avatar_url ?? '').trim(),
        status: String(user?.status ?? 'active'),
    };
}

function renderMemberRoleOptions(selectedValue) {
    return MEMBER_ROLE_OPTIONS.map((option) => {
        return `<option value="${escapeHtml(option.value)}" ${option.value === selectedValue ? 'selected' : ''}>${escapeHtml(option.label)}</option>`;
    }).join('');
}

function renderMemberStatusOptions(selectedValue) {
    return MEMBER_STATUS_OPTIONS.map((option) => {
        return `<option value="${escapeHtml(option.value)}" ${option.value === selectedValue ? 'selected' : ''}>${escapeHtml(option.label)}</option>`;
    }).join('');
}

function renderStatusOptions(selectedValue) {
    return [
        { value: 'active', label: 'Activo' },
        { value: 'archived', label: 'Archivado' },
    ].map((option) => {
        return `<option value="${escapeHtml(option.value)}" ${option.value === selectedValue ? 'selected' : ''}>${escapeHtml(option.label)}</option>`;
    }).join('');
}

function resolveRoleLabel(role) {
    return MEMBER_ROLE_OPTIONS.find((option) => option.value === role)?.label
        ?? (role === 'owner' ? 'Propietario' : 'Colaborador');
}

function resolveStatusLabel(status) {
    return MEMBER_STATUS_OPTIONS.find((option) => option.value === status)?.label ?? 'Activo';
}

function getRoleDescription(role) {
    if (role === 'owner') {
        return 'Control total del proyecto y de sus accesos.';
    }

    return MEMBER_ROLE_OPTIONS.find((option) => option.value === role)?.description
        ?? 'Puede colaborar dentro del proyecto.';
}

function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value ?? '').trim());
}

function normalizeOptionalField(value) {
    return String(value ?? '').trim();
}

function isPlainObject(value) {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function getFileExtension(name, mimeType) {
    const extensionFromName = String(name ?? '').split('.').pop()?.toLowerCase();

    if (extensionFromName && /^[a-z0-9]{2,5}$/.test(extensionFromName)) {
        return extensionFromName;
    }

    const fromType = {
        'image/jpeg': 'jpg',
        'image/png': 'png',
        'image/webp': 'webp',
        'image/gif': 'gif',
        'image/svg+xml': 'svg',
        'image/avif': 'avif',
    };

    return fromType[mimeType] ?? 'png';
}

function cryptoRandomId() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `project_settings_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`;
}

function formatDate(value) {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'sin fecha';
    }

    return new Intl.DateTimeFormat('es-MX', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(date);
}
