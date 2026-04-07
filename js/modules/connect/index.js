export const CONNECT_SECTION_ID = 'Conecta';

export function createConnectModule({
    getAccessToken,
}) {
    const state = {
        loading: false,
        setupRequired: false,
        notice: null,
        catalog: [],
        connections: [],
        connectingProviderKey: '',
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
            state.setupRequired = Boolean(error?.setupRequired);
            state.notice = {
                type: 'error',
                message: error instanceof Error ? error.message : 'No fue posible cargar Conecta.',
            };
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

        const connectButton = target.closest('[data-connect-provider]');

        if (connectButton) {
            const providerKey = connectButton.dataset.connectProvider;

            if (providerKey) {
                void startOauth(providerKey, connectButton);
            }

            return;
        }

        const disconnectButton = target.closest('[data-connect-delete]');

        if (disconnectButton) {
            const id = disconnectButton.dataset.connectDelete;

            if (id) {
                void disconnect(id);
            }
        }
    }

    async function startOauth(providerKey, button) {
        const provider = state.catalog.find((entry) => entry.key === providerKey);

        if (!provider) {
            return;
        }

        if (!provider.oauth_ready) {
            setNotice('error', 'Completa las credenciales OAuth de esta red social antes de conectarla.');
            renderModule();
            return;
        }

        state.connectingProviderKey = providerKey;
        setButtonBusy(button, true, 'Abriendo...');

        try {
            const response = await request('start_oauth', 'POST', { provider_key: providerKey });
            const authorizationUrl = String(response.data?.authorization_url ?? '').trim();

            if (!authorizationUrl) {
                throw new Error('No fue posible construir la URL de autorizacion.');
            }

            window.location.assign(authorizationUrl);
        } catch (error) {
            state.connectingProviderKey = '';
            setNotice('error', error instanceof Error ? error.message : 'No fue posible iniciar la conexion.');
            renderModule();
        }
    }

    async function disconnect(id) {
        const record = state.connections.find((connection) => connection.id === id);

        if (!record) {
            return;
        }

        const confirmed = window.confirm(`Vas a desconectar "${record.display_name}". Esta accion no se puede deshacer.`);

        if (!confirmed) {
            return;
        }

        try {
            await request('delete', 'POST', { id });
            state.connections = state.connections.filter((connection) => connection.id !== id);
            setNotice('success', 'Conexion eliminada correctamente.');
        } catch (error) {
            setNotice('error', error instanceof Error ? error.message : 'No fue posible desconectar este activo.');
        } finally {
            renderModule();
        }
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
                        Haz clic en una red social, acepta permisos y vuelve al panel. Cada usuario administra sus propios activos digitales por separado.
                    </p>
                </div>
            </div>

            ${renderNotice()}

            ${state.setupRequired ? renderSetupState() : ''}

            ${state.loading
                ? renderLoadingGrid()
                : `
                    ${renderCatalog()}
                    ${renderConnections()}
                `}
        `;
    }

    function renderNotice() {
        if (!state.notice) {
            return '';
        }

        return `
            <div class="connect-inline-notice connect-inline-notice--${escapeHtml(state.notice.type)}">
                ${escapeHtml(state.notice.message)}
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
                        <h3>Conectar una red social</h3>
                        <p>El flujo es directo: autorizas, regresas y la conexion queda registrada.</p>
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
        const isBusy = state.connectingProviderKey === provider.key;
        const buttonLabel = count > 0 ? 'Conectar otra' : 'Conectar';

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
                    <span>${provider.oauth_ready ? 'OAuth listo' : 'Falta configurar OAuth'}</span>
                </div>

                <div class="connect-feature-row">
                    ${(Array.isArray(provider.features) ? provider.features : []).map((feature) => {
                        return `<span class="connect-feature-chip">${escapeHtml(feature)}</span>`;
                    }).join('')}
                </div>

                <button
                    type="button"
                    class="workspace-primary-button connect-provider-button"
                    data-connect-provider="${escapeHtml(provider.key)}"
                    ${provider.oauth_ready ? '' : 'disabled'}
                >
                    <span class="material-symbols-rounded">${isBusy ? 'progress_activity' : 'add_link'}</span>
                    <span>${buttonLabel}</span>
                </button>

                <p class="connect-provider-helper">
                    ${provider.oauth_ready
                        ? 'Se abrira la autorizacion oficial de la plataforma.'
                        : 'Completa client ID, client secret y redirect URI para habilitar este boton.'}
                </p>
            </article>
        `;
    }

    function renderConnections() {
        return `
            <section class="connect-assets">
                <div class="connect-section-head">
                    <div>
                        <h3>Conexiones activas</h3>
                        <p>Este listado es independiente por usuario y puede crecer sin limite fijo.</p>
                    </div>
                </div>

                ${state.connections.length === 0
                    ? `
                        <div class="connect-empty-state">
                            <span class="material-symbols-rounded">share</span>
                            <p>Aun no hay redes sociales conectadas en esta cuenta.</p>
                        </div>
                    `
                    : `
                        <div class="connect-table-wrap">
                            <table class="connect-table">
                                <thead>
                                    <tr>
                                        <th>Activo</th>
                                        <th>Red</th>
                                        <th>Estado</th>
                                        <th>Fecha</th>
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
                    <small>${escapeHtml(connection.notes ?? '')}</small>
                </td>
                <td>${escapeHtml(connection.connection_label ?? connection.provider_key ?? '')}</td>
                <td>
                    <span class="connect-status-badge connect-status-badge--${escapeHtml(connection.connection_status ?? 'pending_auth')}">
                        ${escapeHtml(humanizeStatus(connection.connection_status))}
                    </span>
                </td>
                <td>${escapeHtml(formatDate(connection.created_at))}</td>
                <td>
                    <div class="connect-table-actions">
                        <button type="button" class="connect-table-button connect-table-button--danger" data-connect-delete="${escapeHtml(connection.id)}">
                            Desconectar
                        </button>
                    </div>
                </td>
            </tr>
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

    async function request(action, method = 'GET', payload = null) {
        const token = await getAccessToken();

        if (!token) {
            throw new Error('Tu sesion actual ya no es valida. Vuelve a iniciar sesion.');
        }

        const requestUrl = new URL('api/connect.php', window.location.href);
        requestUrl.searchParams.set('action', action);

        const response = await fetch(requestUrl.toString(), {
            method,
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Authorization: `Bearer ${token}`,
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

    function formatDate(value) {
        if (!value) {
            return 'Sin fecha';
        }

        const date = new Date(value);

        if (Number.isNaN(date.getTime())) {
            return 'Sin fecha';
        }

        return new Intl.DateTimeFormat('es-MX', {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(date);
    }

    function setNotice(type, message) {
        state.notice = { type, message };
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
