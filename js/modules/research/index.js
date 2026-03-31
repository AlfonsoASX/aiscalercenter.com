export const RESEARCH_SECTION_ID = 'Investigar';

export function createResearchModule({
    getAccessToken,
}) {
    const state = {
        loading: false,
        notice: null,
        lastIdea: '',
        response: null,
    };

    function renderSection(item) {
        return `
            <div id="research-module" class="workspace-section-card research-module">
                <div class="research-module-head">
                    <div>
                        <h2>${escapeHtml(item.section_title ?? item.label ?? 'Investigar')}</h2>
                        <p class="workspace-section-subtitle">
                            Escribe una idea y consulta senales relacionadas en Google, YouTube, Mercado Libre y Amazon usando integraciones aisladas en PHP.
                        </p>
                    </div>
                </div>

                <form id="research-form" class="research-form">
                    <label for="research-idea" class="research-label">Idea a investigar</label>
                    <div class="research-input-shell">
                        <textarea
                            id="research-idea"
                            name="idea"
                            class="research-textarea"
                            placeholder="Ejemplo: curso de inteligencia artificial para ventas"
                            rows="3"
                        >${escapeHtml(state.lastIdea)}</textarea>

                        <button id="research-submit" type="submit" class="workspace-primary-button research-submit-button">
                            <span class="material-symbols-rounded">travel_explore</span>
                            <span>Investigar</span>
                        </button>
                    </div>
                </form>

                <div id="research-notice" class="research-inline-notice hidden"></div>
                <div id="research-results">
                    ${renderResults()}
                </div>
            </div>
        `;
    }

    function bind() {
        const root = document.getElementById('research-module');
        const form = document.getElementById('research-form');

        if (!root || !form) {
            return;
        }

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            void handleSubmit(form);
        });

        renderNotice();
    }

    async function handleSubmit(form) {
        const idea = form.idea.value.trim();
        const submitButton = document.getElementById('research-submit');

        if (idea.length < 3) {
            state.notice = {
                type: 'error',
                message: 'Escribe una idea un poco mas especifica para investigar.',
            };
            renderNotice();
            form.idea.focus();
            return;
        }

        state.loading = true;
        state.notice = null;
        state.lastIdea = idea;
        renderNotice();
        renderResultsIntoDom();
        setButtonBusy(submitButton, true, 'Investigando...');

        try {
            const token = await getAccessToken();

            if (!token) {
                throw new Error('Tu sesion actual ya no es valida. Vuelve a iniciar sesion.');
            }

            const response = await fetch(resolveEndpointUrl(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`,
                },
                body: JSON.stringify({
                    idea,
                    limit: 10,
                }),
            });

            const payload = await response.json().catch(() => {
                return {
                    success: false,
                    message: 'No fue posible interpretar la respuesta del servidor.',
                };
            });

            if (!response.ok || payload.success !== true) {
                throw new Error(String(payload.message ?? 'La investigacion no pudo completarse.'));
            }

            state.response = payload.data ?? null;
            state.notice = {
                type: 'success',
                message: 'La investigacion se actualizo correctamente.',
            };
        } catch (error) {
            state.response = null;
            state.notice = {
                type: 'error',
                message: error instanceof Error ? error.message : 'La investigacion no pudo completarse.',
            };
        } finally {
            state.loading = false;
            renderNotice();
            renderResultsIntoDom();
            setButtonBusy(submitButton, false);
        }
    }

    function renderResultsIntoDom() {
        const results = document.getElementById('research-results');

        if (!results) {
            return;
        }

        results.innerHTML = renderResults();
    }

    function renderResults() {
        if (state.loading) {
            return `
                <div class="research-results-grid">
                    ${Array.from({ length: 4 }).map(() => renderLoadingCard()).join('')}
                </div>
            `;
        }

        if (!state.response) {
            return `
                <div class="research-empty-state">
                    <span class="material-symbols-rounded">manage_search</span>
                    <h3>Empieza con una idea</h3>
                    <p>Cuando envias una idea, esta se consulta desde PHP y cada proveedor responde por separado para que las integraciones queden aisladas y sean faciles de mantener.</p>
                </div>
            `;
        }

        const providers = Array.isArray(state.response.providers) ? state.response.providers : [];

        return `
            <div class="research-results-head">
                <div>
                    <span class="research-eyebrow">Idea investigada</span>
                    <h3>${escapeHtml(state.response.query ?? state.lastIdea)}</h3>
                </div>
            </div>

            <div class="research-results-grid">
                ${providers.map((provider) => renderProviderCard(provider)).join('')}
            </div>
        `;
    }

    function renderProviderCard(provider) {
        const summary = provider.summary ?? {};
        const entries = Array.isArray(provider.entries) ? provider.entries : [];
        const status = String(provider.status ?? 'ready');
        const toneClass = `research-provider-card--${status}`;

        return `
            <article class="research-provider-card ${toneClass}">
                <header class="research-provider-head">
                    <div>
                        <h4>${escapeHtml(provider.label ?? 'Proveedor')}</h4>
                        <p>${escapeHtml(provider.message ?? '')}</p>
                    </div>

                    <span class="research-provider-badge research-provider-badge--${status}">
                        ${escapeHtml(provider.status_label ?? 'Listo')}
                    </span>
                </header>

                <div class="research-provider-summary">
                    ${renderSummaryChip('Resultados', summary.total_results)}
                    ${renderSummaryChip('Analizados', summary.analyzed_items)}
                    ${renderSummaryChip('Terminos', summary.related_terms)}
                </div>

                ${entries.length > 0
                    ? `
                        <div class="research-provider-table-wrap">
                            <table class="research-provider-table">
                                <thead>
                                    <tr>
                                        <th>Termino</th>
                                        <th>Menciones</th>
                                        <th>Ejemplo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${entries.map((entry) => {
                                        return `
                                            <tr>
                                                <td>${escapeHtml(entry.term ?? '')}</td>
                                                <td>${escapeHtml(String(entry.mentions ?? 0))}</td>
                                                <td>${escapeHtml(entry.sample ?? '')}</td>
                                            </tr>
                                        `;
                                    }).join('')}
                                </tbody>
                            </table>
                        </div>
                    `
                    : `
                        <div class="research-provider-empty">
                            <span class="material-symbols-rounded">${status === 'ready' ? 'data_info_alert' : 'settings_suggest'}</span>
                            <p>${escapeHtml(resolveProviderEmptyCopy(status, provider.message ?? ''))}</p>
                        </div>
                    `}
            </article>
        `;
    }

    function renderSummaryChip(label, value) {
        return `
            <div class="research-summary-chip">
                <small>${escapeHtml(label)}</small>
                <strong>${escapeHtml(value == null ? 'N/D' : String(value))}</strong>
            </div>
        `;
    }

    function resolveProviderEmptyCopy(status, message) {
        if (status === 'needs_configuration') {
            return message || 'Esta integracion aun necesita configuracion.';
        }

        if (status === 'error') {
            return message || 'No fue posible consultar este proveedor.';
        }

        if (status === 'disabled') {
            return message || 'Esta integracion esta deshabilitada por ahora.';
        }

        return 'No se encontraron terminos relacionados con informacion suficiente.';
    }

    function renderLoadingCard() {
        return `
            <div class="research-provider-card research-provider-card--loading">
                <div class="research-loading-line research-loading-line--title"></div>
                <div class="research-loading-line"></div>
                <div class="research-loading-chip-row">
                    <div class="research-loading-chip"></div>
                    <div class="research-loading-chip"></div>
                    <div class="research-loading-chip"></div>
                </div>
                <div class="research-loading-table">
                    <div class="research-loading-line"></div>
                    <div class="research-loading-line"></div>
                    <div class="research-loading-line"></div>
                </div>
            </div>
        `;
    }

    function renderNotice() {
        const notice = document.getElementById('research-notice');

        if (!notice) {
            return;
        }

        if (!state.notice) {
            notice.className = 'research-inline-notice hidden';
            notice.textContent = '';
            return;
        }

        notice.className = `research-inline-notice research-inline-notice--${state.notice.type}`;
        notice.textContent = state.notice.message;
    }

    function resolveEndpointUrl() {
        return new URL('api/research.php', window.location.href).toString();
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
        sectionId: RESEARCH_SECTION_ID,
        renderSection,
        bind,
    };
}
