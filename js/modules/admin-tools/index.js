export const ADMIN_TOOLS_SECTION_ID = 'herramientas';

export function createAdminToolsModule({
    getAccessToken,
    humanizeError,
}) {
    const state = {
        loading: false,
        loaded: false,
        saving: false,
        setupRequired: false,
        notice: null,
        categories: [],
        tools: [],
        editorOpen: false,
        editorTool: null,
        deletingId: '',
    };

    function renderSection(item) {
        return `
            <div id="admin-tools-module" class="workspace-section-card admin-tools-module">
                <div id="admin-tools-shell" class="admin-tools-shell">
                    ${renderLoadingState(item)}
                </div>
            </div>
        `;
    }

    function bind() {
        const root = document.getElementById('admin-tools-module');

        if (!root || root.dataset.adminToolsBound === 'true') {
            return;
        }

        root.dataset.adminToolsBound = 'true';
        root.addEventListener('click', handleRootClick);
        root.addEventListener('submit', handleRootSubmit);
        root.addEventListener('change', handleRootChange);
        root.addEventListener('input', handleRootInput);
        renderModule();
    }

    async function ensureLoaded() {
        if (state.loading) {
            return;
        }

        if (state.loaded && !state.setupRequired && !state.notice) {
            return;
        }

        state.loading = true;
        renderModule();

        try {
            const token = await getAccessToken();

            if (!token) {
                throw new Error('Tu sesion actual ya no es valida. Vuelve a iniciar sesion.');
            }

            const response = await fetch('api/tools.php?action=admin_bootstrap', {
                headers: {
                    Authorization: `Bearer ${token}`,
                },
            });

            const payload = await response.json().catch(() => {
                return {
                    success: false,
                    message: 'No fue posible interpretar la respuesta del servidor.',
                };
            });

            if (!response.ok || payload.success !== true) {
                throw decorateAdminToolsError(payload.message, payload.setup_required);
            }

            state.categories = Array.isArray(payload.data?.categories) ? payload.data.categories : [];
            state.tools = Array.isArray(payload.data?.tools) ? payload.data.tools : [];
            state.setupRequired = false;
            state.notice = null;
            state.loaded = true;
        } catch (error) {
            state.notice = {
                type: 'error',
                message: resolveErrorMessage(error),
            };
            state.setupRequired = Boolean(error?.setupRequired);
            state.loaded = true;
        } finally {
            state.loading = false;
            renderModule();
        }
    }

    function handleRootClick(event) {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (target.closest('[data-admin-tools-create]')) {
            openEditor();
            return;
        }

        if (target.closest('[data-admin-tools-close]')) {
            closeEditor();
            return;
        }

        const editButton = target.closest('[data-admin-tools-edit]');

        if (editButton) {
            const id = editButton.dataset.adminToolsEdit;
            const tool = state.tools.find((entry) => entry.id === id) ?? null;

            if (tool) {
                openEditor(tool);
            }

            return;
        }

        const deleteButton = target.closest('[data-admin-tools-delete]');

        if (deleteButton) {
            const id = deleteButton.dataset.adminToolsDelete;

            if (id) {
                void deleteTool(id);
            }
        }
    }

    function handleRootSubmit(event) {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || form.id !== 'admin-tools-form') {
            return;
        }

        event.preventDefault();
        void saveTool(form);
    }

    function handleRootChange(event) {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (target.matches('#admin-tools-launch-mode')) {
            const form = target.closest('form');

            if (form instanceof HTMLFormElement) {
                toggleLaunchModeFields(form);
            }
        }
    }

    function handleRootInput(event) {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (target.matches('#admin-tools-title')) {
            const form = target.closest('form');

            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            const slugInput = form.querySelector('#admin-tools-slug');

            if (!(slugInput instanceof HTMLInputElement)) {
                return;
            }

            if (slugInput.dataset.manual === 'true' && slugInput.value.trim() !== '') {
                return;
            }

            slugInput.value = slugify(target.value);
            slugInput.dataset.manual = 'false';
        }

        if (target.matches('#admin-tools-slug')) {
            target.dataset.manual = target.value.trim() !== '' ? 'true' : 'false';
        }
    }

    function openEditor(tool = null) {
        state.editorOpen = true;
        state.editorTool = tool ? { ...tool } : null;
        renderModule();
    }

    function closeEditor() {
        state.editorOpen = false;
        state.editorTool = null;
        renderModule();
    }

    async function saveTool(form) {
        const formData = new FormData(form);
        const payload = {
            id: String(formData.get('id') ?? '').trim(),
            category_key: String(formData.get('category_key') ?? '').trim(),
            slug: String(formData.get('slug') ?? '').trim(),
            title: String(formData.get('title') ?? '').trim(),
            description: String(formData.get('description') ?? '').trim(),
            tutorial_youtube_url: String(formData.get('tutorial_youtube_url') ?? '').trim(),
            launch_mode: String(formData.get('launch_mode') ?? 'php_folder').trim(),
            panel_module_key: String(formData.get('panel_module_key') ?? '').trim(),
            app_folder: String(formData.get('app_folder') ?? '').trim(),
            entry_file: String(formData.get('entry_file') ?? 'index.php').trim(),
            sort_order: Number(formData.get('sort_order') ?? 0),
            is_active: formData.get('is_active') === 'on',
            admin_only: formData.get('admin_only') === 'on',
        };
        const submitButton = form.querySelector('button[type="submit"]');

        state.saving = true;
        setButtonBusy(submitButton, true, payload.id ? 'Guardando...' : 'Creando...');

        try {
            const token = await getAccessToken();

            if (!token) {
                throw new Error('Tu sesion actual ya no es valida. Vuelve a iniciar sesion.');
            }

            const response = await fetch('api/tools.php?action=save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Authorization: `Bearer ${token}`,
                },
                body: JSON.stringify(payload),
            });

            const result = await response.json().catch(() => {
                return {
                    success: false,
                    message: 'No fue posible interpretar la respuesta del servidor.',
                };
            });

            if (!response.ok || result.success !== true) {
                throw decorateAdminToolsError(result.message, result.setup_required);
            }

            const savedTool = result.data?.tool ?? null;

            if (savedTool && typeof savedTool === 'object') {
                upsertTool(savedTool);
            }

            state.notice = {
                type: 'success',
                message: String(result.message ?? 'Herramienta guardada correctamente.'),
            };
            state.editorOpen = false;
            state.editorTool = null;
            renderModule();
        } catch (error) {
            state.notice = {
                type: 'error',
                message: resolveErrorMessage(error),
            };
            renderModule();
        } finally {
            state.saving = false;
            setButtonBusy(submitButton, false);
        }
    }

    async function deleteTool(id) {
        const tool = state.tools.find((entry) => entry.id === id);

        if (!tool) {
            return;
        }

        const confirmed = window.confirm(`Vas a eliminar la herramienta "${tool.title}". Esta accion no se puede deshacer.`);

        if (!confirmed) {
            return;
        }

        state.deletingId = id;
        renderModule();

        try {
            const token = await getAccessToken();

            if (!token) {
                throw new Error('Tu sesion actual ya no es valida. Vuelve a iniciar sesion.');
            }

            const response = await fetch('api/tools.php?action=delete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Authorization: `Bearer ${token}`,
                },
                body: JSON.stringify({ id }),
            });

            const result = await response.json().catch(() => {
                return {
                    success: false,
                    message: 'No fue posible interpretar la respuesta del servidor.',
                };
            });

            if (!response.ok || result.success !== true) {
                throw decorateAdminToolsError(result.message, result.setup_required);
            }

            state.tools = state.tools.filter((entry) => entry.id !== id);
            state.notice = {
                type: 'success',
                message: String(result.message ?? 'Herramienta eliminada correctamente.'),
            };
        } catch (error) {
            state.notice = {
                type: 'error',
                message: resolveErrorMessage(error),
            };
        } finally {
            state.deletingId = '';
            renderModule();
        }
    }

    function upsertTool(nextTool) {
        const index = state.tools.findIndex((entry) => entry.id === nextTool.id);

        if (index === -1) {
            state.tools = [...state.tools, nextTool];
        } else {
            state.tools[index] = nextTool;
            state.tools = [...state.tools];
        }

        state.tools.sort((left, right) => {
            const orderDiff = Number(left.sort_order ?? 0) - Number(right.sort_order ?? 0);
            return orderDiff !== 0 ? orderDiff : String(left.title ?? '').localeCompare(String(right.title ?? ''));
        });
    }

    function renderModule() {
        const shell = document.getElementById('admin-tools-shell');

        if (!shell) {
            return;
        }

        shell.innerHTML = `
            <div class="admin-tools-head">
                <div>
                    <h2>Herramientas</h2>
                    <p class="workspace-section-subtitle">
                        Da de alta las herramientas de la plataforma y decide si viven como modulo interno o como aplicacion PHP protegida en carpeta.
                    </p>
                </div>

                <button type="button" class="workspace-primary-button" data-admin-tools-create="true">
                    <span class="material-symbols-rounded">add_circle</span>
                    <span>Nueva herramienta</span>
                </button>
            </div>

            ${state.notice ? renderNotice(state.notice) : ''}
            ${state.setupRequired ? renderSetupState() : ''}
            ${state.loading ? renderSkeletonTable() : renderTable()}
            ${state.editorOpen ? renderEditor() : ''}
        `;

        const form = shell.querySelector('#admin-tools-form');

        if (form instanceof HTMLFormElement) {
            toggleLaunchModeFields(form);
        }
    }

    function renderTable() {
        if (state.tools.length === 0) {
            return `
                <div class="admin-tools-empty">
                    <span class="material-symbols-rounded">build_circle</span>
                    <h3>Aun no hay herramientas registradas</h3>
                    <p>Crea la primera herramienta para que aparezca en Investigar, Diseñar, Ejecutar o Analizar.</p>
                </div>
            `;
        }

        return `
            <div class="admin-tools-table-wrap">
                <table class="admin-tools-table">
                    <thead>
                        <tr>
                            <th>Herramienta</th>
                            <th>Categoria</th>
                            <th>Modo</th>
                            <th>Estado</th>
                            <th>Ruta protegida</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${state.tools.map((tool) => renderRow(tool)).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    function renderRow(tool) {
        const category = state.categories.find((entry) => entry.key === tool.category_key);
        const deleting = state.deletingId === tool.id;

        return `
            <tr>
                <td>
                    <strong>${escapeHtml(tool.title ?? '')}</strong>
                    <p>${escapeHtml(tool.description ?? '')}</p>
                </td>
                <td>${escapeHtml(category?.label ?? tool.category_key ?? '')}</td>
                <td>${escapeHtml(tool.launch_mode === 'panel_module' ? 'Modulo interno' : 'Aplicacion PHP')}</td>
                <td>
                    <span class="admin-tools-status ${tool.is_active ? 'is-active' : ''}">
                        ${tool.is_active ? 'Activa' : 'Inactiva'}
                    </span>
                </td>
                <td>
                    <code>${escapeHtml(tool.app_folder ?? '')}${tool.entry_file ? `/${escapeHtml(tool.entry_file)}` : ''}</code>
                </td>
                <td>
                    <div class="admin-tools-row-actions">
                        <button type="button" class="admin-tools-row-button" data-admin-tools-edit="${escapeHtml(tool.id ?? '')}">
                            <span class="material-symbols-rounded">edit</span>
                            <span>Editar</span>
                        </button>
                        <button type="button" class="admin-tools-row-button admin-tools-row-button--danger" data-admin-tools-delete="${escapeHtml(tool.id ?? '')}" ${deleting ? 'disabled' : ''}>
                            <span class="material-symbols-rounded">${deleting ? 'progress_activity' : 'delete'}</span>
                            <span>${deleting ? 'Borrando...' : 'Borrar'}</span>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }

    function renderEditor() {
        const tool = state.editorTool ?? {};

        return `
            <div class="admin-tools-modal-shell">
                <div class="admin-tools-modal-backdrop" data-admin-tools-close="true"></div>
                <div class="admin-tools-modal">
                    <div class="admin-tools-modal-head">
                        <div>
                            <h3>${tool.id ? 'Editar herramienta' : 'Nueva herramienta'}</h3>
                            <p>La carpeta real se guarda en PHP y solo se usa al lanzar la herramienta.</p>
                        </div>

                        <button type="button" class="workspace-icon-button" data-admin-tools-close="true" aria-label="Cerrar">
                            <span class="material-symbols-rounded">close</span>
                        </button>
                    </div>

                    <form id="admin-tools-form" class="admin-tools-form">
                        <input type="hidden" name="id" value="${escapeHtml(tool.id ?? '')}">

                        <div class="admin-tools-form-grid">
                            <label class="workspace-field-block">
                                <span class="workspace-field-label">Categoria</span>
                                <select id="admin-tools-category" name="category_key" class="workspace-field" required>
                                    <option value="">Selecciona una categoria</option>
                                    ${state.categories.map((category) => {
                                        const selected = category.key === tool.category_key;
                                        return `<option value="${escapeHtml(category.key ?? '')}" ${selected ? 'selected' : ''}>${escapeHtml(category.label ?? category.key ?? '')}</option>`;
                                    }).join('')}
                                </select>
                            </label>

                            <label class="workspace-field-block">
                                <span class="workspace-field-label">Titulo</span>
                                <input id="admin-tools-title" name="title" type="text" class="workspace-field" value="${escapeHtml(tool.title ?? '')}" required>
                            </label>

                            <label class="workspace-field-block">
                                <span class="workspace-field-label">Slug</span>
                                <input id="admin-tools-slug" name="slug" type="text" class="workspace-field" value="${escapeHtml(tool.slug ?? '')}" data-manual="${tool.slug ? 'true' : 'false'}" required>
                            </label>

                            <label class="workspace-field-block">
                                <span class="workspace-field-label">Orden</span>
                                <input name="sort_order" type="number" class="workspace-field" value="${escapeHtml(String(tool.sort_order ?? 0))}" min="0" step="10">
                            </label>

                            <label class="workspace-field-block admin-tools-form-grid__full">
                                <span class="workspace-field-label">Descripcion</span>
                                <textarea name="description" class="workspace-field admin-tools-textarea" rows="4" placeholder="Explica claramente para que sirve esta herramienta.">${escapeHtml(tool.description ?? '')}</textarea>
                            </label>

                            <label class="workspace-field-block admin-tools-form-grid__full">
                                <span class="workspace-field-label">Tutorial en YouTube</span>
                                <input name="tutorial_youtube_url" type="url" class="workspace-field" value="${escapeHtml(tool.tutorial_youtube_url ?? '')}" placeholder="https://www.youtube.com/watch?v=...">
                            </label>

                            <label class="workspace-field-block">
                                <span class="workspace-field-label">Modo de apertura</span>
                                <select id="admin-tools-launch-mode" name="launch_mode" class="workspace-field">
                                    <option value="php_folder" ${tool.launch_mode === 'php_folder' || !tool.launch_mode ? 'selected' : ''}>Aplicacion PHP protegida</option>
                                    <option value="panel_module" ${tool.launch_mode === 'panel_module' ? 'selected' : ''}>Modulo interno del panel</option>
                                </select>
                            </label>

                            <label class="workspace-field-block admin-tools-launch-field" data-launch-mode="panel_module">
                                <span class="workspace-field-label">Clave interna del modulo</span>
                                <input name="panel_module_key" type="text" class="workspace-field" value="${escapeHtml(tool.panel_module_key ?? '')}" placeholder="social_post_scheduler">
                            </label>

                            <label class="workspace-field-block admin-tools-form-grid__full">
                                <span class="workspace-field-label">Carpeta protegida</span>
                                <input name="app_folder" type="text" class="workspace-field" value="${escapeHtml(tool.app_folder ?? '')}" placeholder="apps/mi-herramienta">
                            </label>

                            <label class="workspace-field-block">
                                <span class="workspace-field-label">Archivo de entrada</span>
                                <input name="entry_file" type="text" class="workspace-field" value="${escapeHtml(tool.entry_file ?? 'index.php')}" placeholder="index.php">
                            </label>

                            <label class="admin-tools-switch">
                                <input type="checkbox" name="is_active" ${tool.is_active !== false ? 'checked' : ''}>
                                <span>Visible para usuarios</span>
                            </label>

                            <label class="admin-tools-switch">
                                <input type="checkbox" name="admin_only" ${tool.admin_only ? 'checked' : ''}>
                                <span>Solo administradores</span>
                            </label>
                        </div>

                        <div class="admin-tools-modal-footer">
                            <button type="button" class="admin-tools-secondary" data-admin-tools-close="true">Cancelar</button>
                            <button type="submit" class="workspace-primary-button">
                                <span class="material-symbols-rounded">save</span>
                                <span>${tool.id ? 'Guardar cambios' : 'Crear herramienta'}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        `;
    }

    function renderNotice(notice) {
        return `
            <div class="admin-tools-notice admin-tools-notice--${escapeHtml(notice.type)}">
                ${escapeHtml(notice.message)}
            </div>
        `;
    }

    function renderSetupState() {
        return `
            <div class="admin-tools-setup">
                <strong>Falta preparar la base de herramientas.</strong>
                <p>Ejecuta <code>supabase/tools_schema.sql</code> en Supabase para habilitar el CRUD.</p>
            </div>
        `;
    }

    function renderSkeletonTable() {
        return `
            <div class="admin-tools-table-wrap">
                <div class="admin-tools-skeleton-row"></div>
                <div class="admin-tools-skeleton-row"></div>
                <div class="admin-tools-skeleton-row"></div>
            </div>
        `;
    }

    function renderLoadingState(item) {
        return `
            <div class="admin-tools-empty">
                <span class="material-symbols-rounded admin-tools-spin">progress_activity</span>
                <h3>${escapeHtml(item?.section_title ?? item?.label ?? 'Herramientas')}</h3>
                <p>Preparando el catalogo administrativo de herramientas...</p>
            </div>
        `;
    }

    function toggleLaunchModeFields(form) {
        const modeField = form.querySelector('#admin-tools-launch-mode');
        const mode = modeField instanceof HTMLSelectElement ? modeField.value : 'php_folder';

        form.querySelectorAll('[data-launch-mode]').forEach((field) => {
            if (!(field instanceof HTMLElement)) {
                return;
            }

            field.classList.toggle('hidden', field.dataset.launchMode !== mode);
        });
    }

    function resolveErrorMessage(error) {
        const message = error instanceof Error ? error.message : 'No fue posible administrar las herramientas.';
        return typeof humanizeError === 'function' ? humanizeError(message) : message;
    }

    return {
        renderSection,
        bind,
        ensureLoaded,
    };
}

function decorateAdminToolsError(message, setupRequired = false) {
    const error = new Error(String(message ?? 'No fue posible completar la solicitud.'));
    error.setupRequired = Boolean(setupRequired);
    return error;
}

function slugify(value) {
    const normalized = String(value ?? '').trim().toLowerCase();
    return normalized
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function setButtonBusy(button, isBusy, loadingText) {
    if (!(button instanceof HTMLButtonElement)) {
        return;
    }

    if (!button.dataset.defaultLabel) {
        button.dataset.defaultLabel = button.innerHTML;
    }

    button.disabled = isBusy;
    button.innerHTML = isBusy ? loadingText : button.dataset.defaultLabel;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
