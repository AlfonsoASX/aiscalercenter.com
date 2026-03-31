export const CONNECT_SECTION_ID = 'Conecta';

export function createConnectModule({
    getAccessToken,
}) {
    const state = {
        loading: false,
        saving: false,
        setupRequired: false,
        notice: null,
        catalog: [],
        connections: [],
        editorOpen: false,
        editingId: '',
        activeProviderKey: '',
    };

    function renderSection(item) {
        return `
            <div id="connect-module" class="workspace-section-card connect-module">
                <div id="connect-module-shell" class="connect-module-shell">
                    ${renderLoadingState(item)}
                </div>
            </div>
        `;
    }

    function bind() {
        const root = document.getElementById('connect-module');

        if (!root) {
            return;
        }

        root.addEventListener('click', handleRootClick);
        root.addEventListener('submit', handleRootSubmit);
        renderModule();
        void loadBootstrap();
    }

    async function loadBootstrap() {
        state.loading = true;
        renderModule();

        try {
            const payload = await request('bootstrap');
            state.catalog = Array.isArray(payload.data?.catalog) ? payload.data.catalog : [];
            state.connections = Array.isArray(payload.data?.connections) ? payload.data.connections : [];
            state.setupRequired = false;
            state.notice = null;
        } catch (error) {
            const message = error instanceof Error ? error.message : 'No fue posible cargar Conecta.';
            state.setupRequired = Boolean(error?.setupRequired);
            state.notice = {
                type: 'error',
                message,
            };
        } finally {
            state.loading = false;
            renderModule();
        }
    }

    async function handleRootSubmit(event) {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || form.id !== 'connect-form') {
            return;
        }

        event.preventDefault();

        const payload = {
            id: form.id_value.value.trim(),
            provider_key: form.provider_key.value.trim(),
            display_name: form.display_name.value.trim(),
            handle: form.handle.value.trim(),
            external_id: form.external_id.value.trim(),
            asset_url: form.asset_url.value.trim(),
            notes: form.notes.value.trim(),
        };

        if (!payload.provider_key) {
            setNotice('error', 'Selecciona una red social antes de guardar.');
            syncNotice();
            return;
        }

        if (!payload.display_name) {
            setNotice('error', 'Escribe un nombre claro para este activo digital.');
            syncNotice();
            form.display_name.focus();
            return;
        }

        if (!payload.handle && !payload.external_id && !payload.asset_url) {
            setNotice('error', 'Agrega al menos un handle, identificador o URL.');
            syncNotice();
            form.handle.focus();
            return;
        }

        const submitButton = form.querySelector('button[type="submit"]');
        state.saving = true;
        setButtonBusy(submitButton, true, 'Guardando...');

        try {
            const response = await request('save', 'POST', payload);
            upsertConnection(response.data);
            closeEditor();
            setNotice('success', 'Activo digital guardado correctamente.');
        } catch (error) {
            setNotice('error', error instanceof Error ? error.message : 'No fue posible guardar la conexion.');
        } finally {
            state.saving = false;
            renderModule();
        }
    }

    function handleRootClick(event) {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        const openButton = target.closest('[data-connect-open-provider]');

        if (openButton) {
            const providerKey = openButton.dataset.connectOpenProvider;

            if (providerKey) {
                openEditor(providerKey);
            }

            return;
        }

        const editButton = target.closest('[data-connect-edit]');

        if (editButton) {
            const id = editButton.dataset.connectEdit;
            const record = state.connections.find((connection) => connection.id === id);

            if (record) {
                openEditor(record.provider_key, record);
            }

            return;
        }

        const deleteButton = target.closest('[data-connect-delete]');

        if (deleteButton) {
            const id = deleteButton.dataset.connectDelete;

            if (id) {
                void handleDelete(id);
            }

            return;
        }

        if (target.closest('[data-connect-close-editor]')) {
            closeEditor();
        }
    }

    async function handleDelete(id) {
        const record = state.connections.find((connection) => connection.id === id);

        if (!record) {
            return;
        }

        const confirmed = window.confirm(`Vas a eliminar "${record.display_name}". Esta accion no se puede deshacer.`);

        if (!confirmed) {
            return;
        }

        try {
            await request('delete', 'POST', { id });
            state.connections = state.connections.filter((connection) => connection.id !== id);
            setNotice('success', 'Activo digital eliminado correctamente.');
            renderModule();
        } catch (error) {
            setNotice('error', error instanceof Error ? error.message : 'No fue posible eliminar la conexion.');
            renderModule();
        }
    }

    function openEditor(providerKey, record = null) {
        state.editorOpen = true;
        state.activeProviderKey = providerKey;
        state.editingId = record?.id ?? '';
        renderModule();
    }

    function closeEditor() {
        state.editorOpen = false;
        state.activeProviderKey = '';
        state.editingId = '';
        renderModule();
    }

    function upsertConnection(record) {
        const nextRecord = record ?? null;

        if (!nextRecord || !nextRecord.id) {
            return;
        }

        const index = state.connections.findIndex((connection) => connection.id === nextRecord.id);

        if (index === -1) {
            state.connections = [nextRecord, ...state.connections];
            return;
        }

        state.connections = state.connections.map((connection) => {
            return connection.id === nextRecord.id ? nextRecord : connection;
        });
    }

    function renderModule() {
        const shell = document.getElementById('connect-module-shell');

        if (!shell) {
            return;
        }

        shell.innerHTML = `
            <div class="connect-module-head">
                <div>
                    <h2>Conecta</h2>
                    <p class="workspace-section-subtitle">
                        Registra y organiza perfiles, paginas, canales y fichas de negocio por usuario para que cada persona concentre sus activos digitales en un solo lugar.
                    </p>
                </div>
            </div>

            ${renderNotice()}

            ${state.setupRequired
                ? renderSetupState()
                : ''}

            ${state.loading
                ? renderLoadingGrid()
                : `
                    ${renderCatalog()}
                    ${renderConnections()}
                `}

            ${renderEditor()}
        `;
    }

    function renderNotice() {
        return `
            <div id="connect-inline-notice" class="connect-inline-notice ${state.notice ? `connect-inline-notice--${state.notice.type}` : 'hidden'}">
                ${escapeHtml(state.notice?.message ?? '')}
            </div>
        `;
    }

    function renderSetupState() {
        return `
            <div class="connect-setup">
                <strong>Falta preparar la tabla social_connections.</strong>
                <p>Ejecuta <code>supabase/social_connections_schema.sql</code> en Supabase para guardar conexiones sociales por usuario.</p>
            </div>
        `;
    }

    function renderCatalog() {
        if (state.catalog.length === 0) {
            return '';
        }

        return `
            <section class="connect-catalog">
                <div class="connect-section-head">
                    <div>
                        <h3>Agregar nuevo activo</h3>
                        <p>Elige el tipo de red social que quieres registrar.</p>
                    </div>
                </div>

                <div class="connect-catalog-grid">
                    ${state.catalog.map((provider) => renderProviderCard(provider)).join('')}
                </div>
            </section>
        `;
    }

    function renderProviderCard(provider) {
        const count = state.connections.filter((connection) => connection.provider_key === provider.key).length;

        return `
            <article class="connect-provider-card">
                <div class="connect-provider-card-head">
                    <span class="material-symbols-rounded connect-provider-icon">${escapeHtml(provider.icon ?? 'link')}</span>
                    <div>
                        <strong>${escapeHtml(provider.label)}</strong>
                        <small>${escapeHtml(provider.platform)}</small>
                    </div>
                </div>

                <p>${escapeHtml(provider.description ?? '')}</p>

                <div class="connect-provider-meta">
                    <span>${count} activos</span>
                    <span>${provider.oauth_ready ? 'OAuth listo' : 'OAuth pendiente'}</span>
                </div>

                <div class="connect-feature-row">
                    ${(Array.isArray(provider.features) ? provider.features : []).map((feature) => {
                        return `<span class="connect-feature-chip">${escapeHtml(feature)}</span>`;
                    }).join('')}
                </div>

                <button type="button" class="workspace-primary-button connect-provider-button" data-connect-open-provider="${provider.key}">
                    <span class="material-symbols-rounded">add_link</span>
                    <span>Conectar</span>
                </button>
            </article>
        `;
    }

    function renderConnections() {
        return `
            <section class="connect-assets">
                <div class="connect-section-head">
                    <div>
                        <h3>Activos digitales registrados</h3>
                        <p>Cada usuario ve y administra solo sus propias conexiones.</p>
                    </div>
                </div>

                ${state.connections.length === 0
                    ? `
                        <div class="connect-empty-state">
                            <span class="material-symbols-rounded">share</span>
                            <p>Aun no has registrado perfiles, paginas o canales.</p>
                        </div>
                    `
                    : `
                        <div class="connect-table-wrap">
                            <table class="connect-table">
                                <thead>
                                    <tr>
                                        <th>Activo</th>
                                        <th>Red</th>
                                        <th>Identificador</th>
                                        <th>Estado</th>
                                        <th>Herramientas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${state.connections.map((connection) => renderConnectionRow(connection)).join('')}
                                </tbody>
                            </table>
                        </div>
                    `}
            </section>
        `;
    }

    function renderConnectionRow(connection) {
        return `
            <tr>
                <td>
                    <strong>${escapeHtml(connection.display_name ?? '')}</strong>
                    <small>${escapeHtml(connection.asset_url ?? connection.notes ?? '')}</small>
                </td>
                <td>${escapeHtml(connection.connection_label ?? connection.provider_key ?? '')}</td>
                <td>${escapeHtml(connection.external_id || connection.handle || 'Sin dato')}</td>
                <td>
                    <span class="connect-status-badge connect-status-badge--${escapeHtml(connection.connection_status ?? 'pending_auth')}">
                        ${escapeHtml(humanizeStatus(connection.connection_status))}
                    </span>
                </td>
                <td>
                    <div class="connect-table-actions">
                        <button type="button" class="connect-table-button" data-connect-edit="${connection.id}">
                            Editar
                        </button>
                        <button type="button" class="connect-table-button connect-table-button--danger" data-connect-delete="${connection.id}">
                            Borrar
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }

    function renderEditor() {
        if (!state.editorOpen) {
            return '';
        }

        const provider = getActiveProvider();
        const record = state.connections.find((connection) => connection.id === state.editingId) ?? null;

        if (!provider) {
            return '';
        }

        return `
            <div class="connect-editor-shell">
                <div class="connect-editor-backdrop" data-connect-close-editor="true"></div>
                <div class="connect-editor-panel">
                    <div class="connect-editor-head">
                        <div>
                            <h3>${record ? 'Editar activo digital' : `Conectar ${escapeHtml(provider.label)}`}</h3>
                            <p>${escapeHtml(provider.helper ?? provider.description ?? '')}</p>
                        </div>

                        <button type="button" class="workspace-icon-button" data-connect-close-editor="true" aria-label="Cerrar">
                            <span class="material-symbols-rounded">close</span>
                        </button>
                    </div>

                    <form id="connect-form" class="connect-editor-form">
                        <input type="hidden" name="id_value" value="${escapeHtml(record?.id ?? '')}">
                        <input type="hidden" name="provider_key" value="${escapeHtml(provider.key)}">

                        <div class="connect-editor-grid">
                            <div class="connect-editor-field connect-editor-field--full">
                                <label class="connect-editor-label" for="connect-display-name">Nombre del activo</label>
                                <input
                                    id="connect-display-name"
                                    name="display_name"
                                    type="text"
                                    class="connect-editor-input"
                                    placeholder="Ejemplo: Canal principal de ventas"
                                    value="${escapeHtml(record?.display_name ?? '')}"
                                    required
                                >
                            </div>

                            <div class="connect-editor-field">
                                <label class="connect-editor-label" for="connect-handle">${escapeHtml(provider.handle_label)}</label>
                                <input
                                    id="connect-handle"
                                    name="handle"
                                    type="text"
                                    class="connect-editor-input"
                                    placeholder="@usuario o etiqueta interna"
                                    value="${escapeHtml(record?.handle ?? '')}"
                                >
                            </div>

                            <div class="connect-editor-field">
                                <label class="connect-editor-label" for="connect-external-id">${escapeHtml(provider.external_id_label)}</label>
                                <input
                                    id="connect-external-id"
                                    name="external_id"
                                    type="text"
                                    class="connect-editor-input"
                                    placeholder="ID, identificador o referencia"
                                    value="${escapeHtml(record?.external_id ?? '')}"
                                >
                            </div>

                            <div class="connect-editor-field connect-editor-field--full">
                                <label class="connect-editor-label" for="connect-asset-url">${escapeHtml(provider.url_label)}</label>
                                <input
                                    id="connect-asset-url"
                                    name="asset_url"
                                    type="url"
                                    class="connect-editor-input"
                                    placeholder="https://..."
                                    value="${escapeHtml(record?.asset_url ?? '')}"
                                >
                            </div>

                            <div class="connect-editor-field connect-editor-field--full">
                                <label class="connect-editor-label" for="connect-notes">Notas</label>
                                <textarea
                                    id="connect-notes"
                                    name="notes"
                                    class="connect-editor-textarea"
                                    placeholder="Notas internas para recordar como se usa este activo"
                                >${escapeHtml(record?.notes ?? '')}</textarea>
                            </div>
                        </div>

                        <div class="connect-editor-footer">
                            <button type="button" class="connect-secondary-button" data-connect-close-editor="true">Cancelar</button>
                            <button type="submit" class="workspace-primary-button">
                                <span class="material-symbols-rounded">save</span>
                                <span>${record ? 'Guardar cambios' : 'Guardar activo'}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        `;
    }

    function renderLoadingGrid() {
        return `
            <div class="connect-loading-grid">
                ${Array.from({ length: 4 }).map(() => {
                    return `
                        <div class="connect-loading-card">
                            <div class="connect-loading-line connect-loading-line--title"></div>
                            <div class="connect-loading-line"></div>
                            <div class="connect-loading-line"></div>
                            <div class="connect-loading-chip-row">
                                <div class="connect-loading-chip"></div>
                                <div class="connect-loading-chip"></div>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    function renderLoadingState(item = null) {
        return `
            <div class="connect-loading-state">
                <span class="material-symbols-rounded connect-spin">progress_activity</span>
                <h3>${escapeHtml(item?.section_title ?? item?.label ?? 'Conecta')}</h3>
                <p>Preparando tus activos digitales...</p>
            </div>
        `;
    }

    function getActiveProvider() {
        return state.catalog.find((provider) => provider.key === state.activeProviderKey) ?? null;
    }

    async function request(action, method = 'GET', payload = null) {
        const token = await getAccessToken();

        if (!token) {
            throw new Error('Tu sesion actual ya no es valida. Vuelve a iniciar sesion.');
        }

        const requestUrl = new URL('api/connect.php', window.location.href);
        requestUrl.searchParams.set('action', action);

        const response = await fetch(requestUrl.toString(), {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`,
            },
            body: method === 'GET' ? null : JSON.stringify(payload ?? {}),
        });

        const json = await response.json().catch(() => {
            return {
                success: false,
                message: 'No fue posible interpretar la respuesta del servidor.',
            };
        });

        if (!response.ok || json.success !== true) {
            const error = new Error(String(json.message ?? 'La solicitud no pudo completarse.'));
            error.setupRequired = Boolean(json.setup_required);
            throw error;
        }

        return json;
    }

    function humanizeStatus(status) {
        switch (String(status ?? 'pending_auth')) {
        case 'connected':
            return 'Conectado';
        case 'paused':
            return 'Pausado';
        case 'error':
            return 'Con error';
        default:
            return 'Pendiente';
        }
    }

    function setNotice(type, message) {
        state.notice = { type, message };
    }

    function syncNotice() {
        const notice = document.getElementById('connect-inline-notice');

        if (!notice) {
            return;
        }

        if (!state.notice) {
            notice.className = 'connect-inline-notice hidden';
            notice.textContent = '';
            return;
        }

        notice.className = `connect-inline-notice connect-inline-notice--${state.notice.type}`;
        notice.textContent = state.notice.message;
    }

    function setButtonBusy(button, isBusy, busyText = 'Procesando...') {
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        if (!button.dataset.defaultLabel) {
            button.dataset.defaultLabel = button.innerHTML;
        }

        button.disabled = isBusy;
        button.innerHTML = isBusy ? busyText : button.dataset.defaultLabel;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    return {
        sectionId: CONNECT_SECTION_ID,
        renderSection,
        bind,
    };
}
