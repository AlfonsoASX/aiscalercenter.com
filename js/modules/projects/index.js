import {
    STORAGE_SCOPES,
    USER_FILES_STORAGE_BUCKET,
    buildUserStoragePath,
} from '../../shared/storage.js';
import { describeErrorMessage } from '../../shared/ui.js';

export const PROJECTS_SECTION_ID = 'proyectos';

const PROJECT_LOGO_ACCEPT = 'image/jpeg,image/png,image/webp,image/gif,image/svg+xml,image/avif';

export function createProjectsModule({
    supabase,
    getCurrentUser,
    getActiveProject,
    onOpenProject,
    onProjectsLoaded,
    showNotice,
    humanizeError,
}) {
    const state = {
        projects: [],
        loading: false,
        saving: false,
        modalOpen: false,
        editingProjectId: '',
        logoFile: null,
        logoPreviewUrl: '',
        notice: null,
    };

    function renderSection() {
        return `
            <section id="projects-module" class="projects-module" aria-label="Proyectos">
                <div class="projects-module-head">
                    <div>
                        <p class="projects-eyebrow">Proyectos</p>
                        <h2>Elige el proyecto de trabajo</h2>
                        <p>Primero selecciona el proyecto. Despues usa Investigar, Diseñar, Ejecutar o Analizar con ese contexto.</p>
                    </div>

                    <div class="projects-actions">
                        <button type="button" class="projects-icon-button" data-projects-refresh aria-label="Actualizar proyectos">
                            <span class="material-symbols-rounded">refresh</span>
                        </button>
                        <button type="button" class="projects-create-button" data-project-create>
                            <span class="material-symbols-rounded">add</span>
                            <span>Crear nuevo</span>
                        </button>
                    </div>
                </div>

                <div id="projects-module-notice" class="projects-notice hidden"></div>
                <div id="projects-module-shell" class="projects-module-shell">
                    ${renderLoadingState()}
                </div>
                ${renderProjectModal()}
            </section>
        `;
    }

    function bind() {
        const root = document.getElementById('projects-module');

        if (!root || root.dataset.projectsBound === 'true') {
            return;
        }

        root.dataset.projectsBound = 'true';
        root.addEventListener('click', handleClick);
        root.addEventListener('submit', handleSubmit);
        root.addEventListener('change', handleChange);
        void loadProjects();
    }

    async function loadProjects() {
        const root = document.getElementById('projects-module');
        const shell = document.getElementById('projects-module-shell');

        if (!root || !shell) {
            return;
        }

        state.loading = true;
        state.notice = null;
        renderShell();

        try {
            const { data, error } = await supabase
                .from('projects')
                .select('id, owner_user_id, name, logo_url, logo_storage_path, description, company_type, company_goal, status, updated_at')
                .is('deleted_at', null)
                .order('updated_at', { ascending: false });

            if (error) {
                throw error;
            }

            const rows = Array.isArray(data) ? data : [];
            let memberCounts = new Map();

            try {
                memberCounts = await loadMemberCounts(rows.map((project) => String(project?.id ?? '')).filter(Boolean));
            } catch (memberError) {
                console.error(memberError);
            }

            state.projects = rows.map((project) => normalizeProject(project, memberCounts));
            onProjectsLoaded?.(state.projects);
        } catch (error) {
            state.projects = [];
            state.notice = {
                type: 'error',
                message: humanizeProjectError(error),
            };
        } finally {
            state.loading = false;
            renderShell();
        }
    }

    async function loadMemberCounts(projectIds) {
        if (!Array.isArray(projectIds) || projectIds.length === 0) {
            return new Map();
        }

        const { data, error } = await supabase
            .from('project_members')
            .select('project_id, id')
            .in('project_id', projectIds)
            .eq('status', 'active');

        if (error) {
            throw error;
        }

        const counts = new Map();

        (Array.isArray(data) ? data : []).forEach((row) => {
            const projectId = String(row?.project_id ?? '').trim();

            if (projectId === '') {
                return;
            }

            counts.set(projectId, (counts.get(projectId) ?? 0) + 1);
        });

        return counts;
    }

    function renderShell() {
        const shell = document.getElementById('projects-module-shell');
        const notice = document.getElementById('projects-module-notice');

        if (!shell) {
            return;
        }

        if (notice) {
            notice.className = `projects-notice ${state.notice ? `projects-notice--${state.notice.type}` : 'hidden'}`;
            notice.innerHTML = state.notice ? renderModuleNotice(state.notice.message) : '';
        }

        shell.innerHTML = state.loading ? renderLoadingState() : renderProjectsGrid();
        renderModalIntoDom();
    }

    function renderProjectsGrid() {
        const activeProject = getActiveProject?.();
        const cards = state.projects.map((project) => renderProjectCard(project, activeProject?.id === project.id)).join('');

        return `
            <div class="projects-grid">
                <button type="button" class="project-card project-card--create" data-project-create>
                    <span class="project-create-mark">
                        <span class="material-symbols-rounded">add</span>
                    </span>
                    <strong>Crear proyecto</strong>
                </button>
                ${cards}
            </div>
        `;
    }

    function renderProjectCard(project, isActive) {
        const logo = String(project.logo_url ?? '').trim();
        const updatedAt = project.updated_at ? formatProjectDate(project.updated_at) : 'Sin fecha';
        const memberCount = project.members.length;

        return `
            <article class="project-card ${isActive ? 'is-active' : ''}" data-project-card="${escapeHtml(project.id)}">
                <button type="button" class="project-card-body" data-project-open="${escapeHtml(project.id)}">
                    <div class="project-logo ${logo ? '' : 'is-empty'}">
                        ${logo
                            ? `<img src="${escapeHtml(logo)}" alt="${escapeHtml(project.name)}">`
                            : `<span>${escapeHtml(project.name.slice(0, 1).toUpperCase() || 'P')}</span>`}
                    </div>
                    <div class="project-card-copy">
                        <h3>${escapeHtml(project.name)}</h3>
                        <p>${escapeHtml(project.description || 'Proyecto listo para organizar herramientas y resultados.')}</p>
                    </div>
                    <div class="project-card-meta">
                        <span>${escapeHtml(updatedAt)}</span>
                        <span>${memberCount} ${memberCount === 1 ? 'usuario' : 'usuarios'}</span>
                    </div>
                </button>

                <button type="button" class="project-card-menu" data-project-edit="${escapeHtml(project.id)}" aria-label="Editar proyecto">
                    <span class="material-symbols-rounded">more_vert</span>
                </button>
            </article>
        `;
    }

    function renderLoadingState() {
        return `
            <div class="projects-loading">
                <span class="material-symbols-rounded projects-spin">progress_activity</span>
                <strong>Cargando proyectos...</strong>
            </div>
        `;
    }

    function renderProjectModal() {
        const project = getEditingProject();
        const isEditing = Boolean(project?.id);
        const logoPreview = state.logoPreviewUrl || project?.logo_url || '';

        return `
            <div id="project-modal" class="project-modal ${state.modalOpen ? '' : 'hidden'}" role="dialog" aria-modal="true" aria-label="${isEditing ? 'Editar proyecto' : 'Crear proyecto'}">
                <div class="project-modal-backdrop" data-project-modal-close></div>
                <form id="project-form" class="project-modal-card">
                    <div class="project-modal-head">
                        <div>
                            <p class="projects-eyebrow">${isEditing ? 'Editar' : 'Nuevo'}</p>
                            <h3>${isEditing ? 'Editar proyecto' : 'Crear proyecto'}</h3>
                        </div>
                        <button type="button" class="projects-icon-button" data-project-modal-close aria-label="Cerrar">
                            <span class="material-symbols-rounded">close</span>
                        </button>
                    </div>

                    <input type="hidden" name="project_id" value="${escapeHtml(project?.id ?? '')}">

                    <label class="workspace-field-block">
                        <span class="workspace-field-label">Nombre</span>
                        <input name="name" type="text" class="workspace-field" value="${escapeHtml(project?.name ?? '')}" required placeholder="Nombre del proyecto">
                    </label>

                    <label class="workspace-field-block">
                        <span class="workspace-field-label">Logo</span>
                        <div class="project-logo-uploader">
                            <div class="project-logo-preview ${logoPreview ? '' : 'is-empty'}">
                                ${logoPreview
                                    ? `<img src="${escapeHtml(logoPreview)}" alt="">`
                                    : '<span class="material-symbols-rounded">image</span>'}
                            </div>
                            <div>
                                <input id="project-logo-input" name="logo" type="file" accept="${PROJECT_LOGO_ACCEPT}">
                                <p>Se guarda en el storage central <code>user-files</code>.</p>
                            </div>
                        </div>
                    </label>

                    <label class="workspace-field-block">
                        <span class="workspace-field-label">Descripcion</span>
                        <textarea name="description" class="workspace-field project-textarea" rows="3" placeholder="Que representa este proyecto">${escapeHtml(project?.description ?? '')}</textarea>
                    </label>

                    <div class="project-form-grid">
                        <label class="workspace-field-block">
                            <span class="workspace-field-label">Tipo de empresa</span>
                            <input name="company_type" type="text" class="workspace-field" value="${escapeHtml(project?.company_type ?? '')}" placeholder="Ej. Restaurante, SaaS, consultoria">
                        </label>
                        <label class="workspace-field-block">
                            <span class="workspace-field-label">Objetivo de la empresa</span>
                            <input name="company_goal" type="text" class="workspace-field" value="${escapeHtml(project?.company_goal ?? '')}" placeholder="Ej. vender mas, validar mercado">
                        </label>
                    </div>

                    <p class="project-helper">Los accesos del equipo y la configuracion avanzada se administran dentro del proyecto, en la seccion Configuracion.</p>

                    <div class="project-modal-footer">
                        ${isEditing ? `
                            <button type="button" class="projects-danger-button" data-project-delete="${escapeHtml(project.id)}">
                                <span class="material-symbols-rounded">delete</span>
                                <span>Eliminar</span>
                            </button>
                        ` : '<span></span>'}
                        <button type="submit" class="projects-create-button" ${state.saving ? 'disabled' : ''}>
                            <span class="material-symbols-rounded">save</span>
                            <span>${state.saving ? 'Guardando...' : 'Guardar proyecto'}</span>
                        </button>
                    </div>
                </form>
            </div>
        `;
    }

    function renderModalIntoDom() {
        const modal = document.getElementById('project-modal');

        if (!modal) {
            return;
        }

        modal.outerHTML = renderProjectModal();
    }

    function handleClick(event) {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (target.closest('[data-project-notice-dismiss]')) {
            event.preventDefault();
            clearNotice();
            return;
        }

        if (state.notice && !target.closest('#projects-module-notice')) {
            clearNotice();
        }

        if (target.closest('[data-project-create]')) {
            event.preventDefault();
            openModal();
            return;
        }

        const editButton = target.closest('[data-project-edit]');

        if (editButton instanceof HTMLElement) {
            event.preventDefault();
            openModal(String(editButton.dataset.projectEdit ?? ''));
            return;
        }

        const openButton = target.closest('[data-project-open]');

        if (openButton instanceof HTMLElement) {
            event.preventDefault();
            const project = state.projects.find((item) => item.id === String(openButton.dataset.projectOpen ?? ''));

            if (project) {
                onOpenProject?.(project);
                renderShell();
            }
            return;
        }

        if (target.closest('[data-project-modal-close]')) {
            event.preventDefault();
            closeModal();
            return;
        }

        const deleteButton = target.closest('[data-project-delete]');

        if (deleteButton instanceof HTMLElement) {
            event.preventDefault();
            void deleteProject(String(deleteButton.dataset.projectDelete ?? ''));
        }

        if (target.closest('[data-projects-refresh]')) {
            event.preventDefault();
            void loadProjects();
        }
    }

    function handleChange(event) {
        const target = event.target;

        if (!(target instanceof HTMLInputElement) || target.id !== 'project-logo-input') {
            return;
        }

        const file = target.files?.[0] ?? null;

        if (!(file instanceof File)) {
            return;
        }

        if (!file.type.startsWith('image/')) {
            showModuleNotice('error', 'El logo debe ser una imagen.');
            target.value = '';
            return;
        }

        releaseLogoPreview();
        state.logoFile = file;
        state.logoPreviewUrl = URL.createObjectURL(file);
        updateLogoPreview(state.logoPreviewUrl);
    }

    async function handleSubmit(event) {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || form.id !== 'project-form') {
            return;
        }

        event.preventDefault();
        await saveProject(form);
    }

    function openModal(projectId = '') {
        const project = state.projects.find((item) => item.id === projectId) ?? null;
        state.modalOpen = true;
        state.editingProjectId = project?.id ?? '';
        releaseLogoPreview();
        renderShell();
    }

    function closeModal() {
        state.modalOpen = false;
        state.editingProjectId = '';
        state.logoFile = null;
        releaseLogoPreview();
        renderShell();
    }

    async function saveProject(form) {
        const currentUser = getCurrentUser();
        const fallbackUserId = String(currentUser?.id ?? '').trim();
        const requiresLogoUpload = state.logoFile instanceof File;

        if (!fallbackUserId) {
            showModuleNotice('error', 'No encontramos tu usuario activo.');
            return;
        }

        const projectId = String(form.project_id.value ?? '').trim();
        const currentProject = state.projects.find((item) => item.id === projectId) ?? null;
        const name = String(form.name.value ?? '').trim();
        const description = String(form.description.value ?? '').trim();
        const companyType = String(form.company_type.value ?? '').trim();
        const companyGoal = String(form.company_goal.value ?? '').trim();

        if (!name) {
            showModuleNotice('error', 'El proyecto necesita un nombre.');
            return;
        }

        if (currentProject && !hasPendingProjectChanges(currentProject, {
            name,
            description,
            companyType,
            companyGoal,
        })) {
            showModuleNotice('info', 'No hay cambios pendientes en este proyecto.');
            return;
        }

        state.saving = true;
        renderModalIntoDom();

        try {
            const currentUserId = requiresLogoUpload
                ? await resolveCurrentSessionUserId(fallbackUserId)
                : fallbackUserId;

            const logo = state.logoFile instanceof File
                ? await uploadProjectLogo(currentUserId, state.logoFile)
                : {
                    url: currentProject?.logo_url ?? '',
                    path: currentProject?.logo_storage_path ?? '',
                };
            const payload = {
                owner_user_id: currentProject?.owner_user_id || currentUserId,
                name,
                logo_url: logo.url,
                logo_storage_path: logo.path,
                description,
                company_type: companyType,
                company_goal: companyGoal,
                status: 'active',
            };

            await upsertProject(projectId, payload);

            showModuleNotice('success', 'Proyecto guardado correctamente.');
            closeModal();
            await loadProjects();

            if (!getActiveProject?.()) {
                onProjectsLoaded?.(state.projects);
            }
        } catch (error) {
            showModuleNotice('error', humanizeProjectError(error));
        } finally {
            state.saving = false;
            renderModalIntoDom();
        }
    }

    async function resolveCurrentSessionUserId(fallbackUserId) {
        const fallback = String(fallbackUserId ?? '').trim();
        const {
            data: { session },
            error,
        } = await supabase.auth.getSession();

        if (error) {
            throw error;
        }

        const userId = String(session?.user?.id ?? fallback).trim();

        if (userId === '') {
            throw new Error('auth session missing');
        }

        return userId;
    }

    function hasPendingProjectChanges(currentProject, draft) {
        if (state.logoFile instanceof File) {
            return true;
        }

        return normalizeComparableField(draft.name) !== normalizeComparableField(currentProject?.name)
            || normalizeComparableField(draft.description) !== normalizeComparableField(currentProject?.description)
            || normalizeComparableField(draft.companyType) !== normalizeComparableField(currentProject?.company_type)
            || normalizeComparableField(draft.companyGoal) !== normalizeComparableField(currentProject?.company_goal);
    }

    async function upsertProject(projectId, payload) {
        if (projectId) {
            const { data, error } = await supabase
                .from('projects')
                .update(payload)
                .eq('id', projectId)
                .select('id, owner_user_id, name, logo_url, logo_storage_path, description, company_type, company_goal, status, updated_at')
                .single();

            if (error) {
                throw error;
            }

            return data;
        }

        const rpcProject = await createProjectWithRpc(payload);

        if (rpcProject?.project) {
            return rpcProject.project;
        }

        const { data, error } = await supabase
            .from('projects')
            .insert(payload)
            .select('id, owner_user_id, name, logo_url, logo_storage_path, description, company_type, company_goal, status, updated_at')
            .single();

        if (error) {
            if (rpcProject?.missingFunction && isProjectCreatePolicyError(error)) {
                throw new Error('create_project missing and direct project insert blocked by row-level security');
            }

            throw error;
        }

        return data;
    }

    async function createProjectWithRpc(payload) {
        const rpcPayload = {
            p_name: payload.name,
            p_logo_url: payload.logo_url,
            p_logo_storage_path: payload.logo_storage_path,
            p_description: payload.description,
            p_company_type: payload.company_type,
            p_company_goal: payload.company_goal,
            p_status: payload.status,
        };
        const { data, error } = await supabase.rpc('create_project', rpcPayload).single();

        if (!error) {
            return {
                project: data,
                missingFunction: false,
            };
        }

        if (canFallbackToDirectProjectInsert(error)) {
            return {
                project: null,
                missingFunction: true,
            };
        }

        throw error;
    }

    function canFallbackToDirectProjectInsert(error) {
        const normalized = describeErrorMessage(error, '').toLowerCase();

        return (
            normalized.includes('pgrst202')
            || normalized.includes('could not find the function')
            || normalized.includes('schema cache')
            || normalized.includes('create_project')
        );
    }

    function isProjectCreatePolicyError(error) {
        const normalized = describeErrorMessage(error, '').toLowerCase();

        return normalized.includes('row-level security') || normalized.includes('42501');
    }

    async function deleteProject(projectId) {
        if (!projectId || !window.confirm('¿Eliminar este proyecto?')) {
            return;
        }

        const { error } = await supabase
            .from('projects')
            .update({ deleted_at: new Date().toISOString() })
            .eq('id', projectId);

        if (error) {
            showModuleNotice('error', humanizeProjectError(error));
            return;
        }

        showModuleNotice('success', 'Proyecto eliminado.');
        closeModal();
        await loadProjects();
    }

    async function uploadProjectLogo(userId, file) {
        if (!file.type.startsWith('image/')) {
            throw new Error('El logo debe ser una imagen.');
        }

        const extension = getFileExtension(file.name, file.type);
        const filePath = buildUserStoragePath(
            userId,
            STORAGE_SCOPES.projects,
            'logos',
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
            path: filePath,
            url: data.publicUrl,
        };
    }

    function normalizeProject(project, memberCounts = new Map()) {
        const projectId = String(project?.id ?? '');

        return {
            id: projectId,
            owner_user_id: String(project?.owner_user_id ?? ''),
            name: String(project?.name ?? 'Proyecto sin nombre'),
            logo_url: String(project?.logo_url ?? ''),
            logo_storage_path: String(project?.logo_storage_path ?? ''),
            description: String(project?.description ?? ''),
            company_type: String(project?.company_type ?? ''),
            company_goal: String(project?.company_goal ?? ''),
            status: String(project?.status ?? 'active'),
            updated_at: String(project?.updated_at ?? ''),
            members: Array.from({ length: memberCounts.get(projectId) ?? 0 }, () => ({})),
        };
    }

    function getEditingProject() {
        return state.projects.find((item) => item.id === state.editingProjectId) ?? null;
    }

    function showModuleNotice(type, message) {
        state.notice = { type, message };
        renderShell();
    }

    function clearNotice() {
        if (!state.notice) {
            return;
        }

        state.notice = null;
        renderShell();
    }

    function renderModuleNotice(message) {
        return `
            <div class="projects-notice__content">
                <button type="button" class="projects-notice__dismiss" data-project-notice-dismiss aria-label="Cerrar notificacion">
                    <span class="material-symbols-rounded">close</span>
                </button>
                <span class="projects-notice__message">${escapeHtml(message)}</span>
            </div>
        `;
    }

    function humanizeProjectError(error) {
        const raw = describeErrorMessage(error, 'No fue posible cargar proyectos.');
        const normalized = raw.toLowerCase();

        if (normalized.includes('pgrst205') || normalized.includes('could not find the table') || normalized.includes('projects')) {
            return 'Falta crear la estructura de proyectos. Ejecuta supabase/projects_schema.sql en Supabase.';
        }

        if (normalized.includes('project_members') && (normalized.includes('relationship') || normalized.includes('schema cache'))) {
            return 'Supabase aun no reconoce correctamente la relacion de proyectos. Actualiza el esquema y recarga el panel.';
        }

        if (
            normalized.includes('pgrst202')
            || normalized.includes('could not find the function')
            || (normalized.includes('schema cache') && normalized.includes('create_project'))
            || normalized.includes('create_project')
        ) {
            return 'La base de datos aun no tiene la funcion create_project actualizada. Ejecuta de nuevo supabase/projects_schema.sql y recarga el panel.';
        }

        if (
            normalized.includes('new row violates row-level security policy')
            || (normalized.includes('42501') && normalized.includes('projects'))
        ) {
            return 'La base de datos bloqueo la creacion del proyecto por politicas internas. Ejecuta de nuevo supabase/projects_schema.sql y recarga el panel.';
        }

        if (
            normalized.includes('auth session missing')
            || normalized.includes('refresh token')
            || normalized.includes('jwt expired')
            || (normalized.includes('session') && normalized.includes('expired'))
        ) {
            return 'Tu sesion expiro mientras intentabamos guardar. Inicia sesion de nuevo y vuelve a guardar el proyecto.';
        }

        if (normalized.includes('bucket') || normalized.includes('storage')) {
            return 'Falta configurar el almacenamiento central. Ejecuta supabase/user_files_storage_setup.sql en Supabase.';
        }

        if (normalized.includes('row-level security')) {
            return 'Supabase bloqueo esta operacion por permisos. Reejecuta supabase/projects_schema.sql en Supabase.';
        }

        return typeof humanizeError === 'function' ? humanizeError(raw) : raw;
    }

    function releaseLogoPreview() {
        if (state.logoPreviewUrl.startsWith('blob:')) {
            URL.revokeObjectURL(state.logoPreviewUrl);
        }

        state.logoPreviewUrl = '';
    }

    function updateLogoPreview(source) {
        const preview = document.querySelector('#project-modal .project-logo-preview');
        const normalizedSource = String(source ?? '').trim();

        if (!(preview instanceof HTMLElement)) {
            return;
        }

        preview.classList.toggle('is-empty', normalizedSource === '');
        preview.innerHTML = normalizedSource
            ? `<img src="${escapeHtml(normalizedSource)}" alt="">`
            : '<span class="material-symbols-rounded">image</span>';
    }

    return {
        renderSection,
        bind,
        clearNotice,
        loadProjects,
    };
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

    return `project_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`;
}

function normalizeComparableField(value) {
    return String(value ?? '').trim();
}

function formatProjectDate(value) {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'Sin fecha';
    }

    return new Intl.DateTimeFormat('es-MX', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(date);
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
