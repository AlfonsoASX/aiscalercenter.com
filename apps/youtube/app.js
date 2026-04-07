const config = window.AISCALER_RESEARCH_APP ?? {};

const form = document.getElementById('research-tool-form');
const results = document.getElementById('research-tool-results');
const notice = document.getElementById('research-tool-notice');
const submitButton = document.getElementById('research-tool-submit');

if (form instanceof HTMLFormElement) {
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        void handleSubmit(form);
    });
}

async function handleSubmit(currentForm) {
    const ideaField = currentForm.idea;
    const idea = String(ideaField?.value ?? '').trim();

    if (idea.length < 3) {
        renderNotice('error', 'Escribe una idea un poco mas especifica para investigar.');
        ideaField?.focus();
        return;
    }

    renderNotice('', '');
    renderLoadingState();
    setButtonBusy(true, 'Investigando...');

    try {
        const response = await fetch(String(config.actionUrl ?? 'tool-action.php'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
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

        renderNotice('success', 'La investigacion se actualizo correctamente.');
        renderResults(payload.data ?? null, idea);
    } catch (error) {
        renderNotice('error', error instanceof Error ? error.message : 'La investigacion no pudo completarse.');
        renderEmptyState();
    } finally {
        setButtonBusy(false);
    }
}

function renderResults(payload, fallbackIdea) {
    if (!(results instanceof HTMLElement)) {
        return;
    }

    const provider = Array.isArray(payload?.providers) ? payload.providers[0] ?? null : null;

    if (!provider) {
        renderEmptyState();
        return;
    }

    const entries = Array.isArray(provider.entries) ? provider.entries : [];
    const summary = provider.summary ?? {};

    results.innerHTML = `
        <div class="research-tool-results-head">
            <span class="research-tool-eyebrow">Idea investigada</span>
            <h3>${escapeHtml(String(payload?.query ?? fallbackIdea ?? ''))}</h3>
        </div>
        <article class="research-tool-provider-card">
            <header class="research-tool-provider-head">
                <div>
                    <h4>${escapeHtml(String(provider.label ?? config.providerLabel ?? 'Proveedor'))}</h4>
                    <p>${escapeHtml(String(provider.message ?? ''))}</p>
                </div>
                <span class="research-tool-badge">${escapeHtml(String(provider.status_label ?? 'Listo'))}</span>
            </header>
            <div class="research-tool-summary">
                ${renderSummaryChip('Resultados', summary.total_results)}
                ${renderSummaryChip('Analizados', summary.analyzed_items)}
                ${renderSummaryChip('Terminos', summary.related_terms)}
            </div>
            ${entries.length > 0 ? renderEntriesTable(entries) : renderProviderEmpty(provider)}
        </article>
    `;
}

function renderEntriesTable(entries) {
    return `
        <div class="research-tool-table-wrap">
            <table class="research-tool-table">
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
                                <td>${escapeHtml(String(entry.term ?? ''))}</td>
                                <td>${escapeHtml(String(entry.mentions ?? 0))}</td>
                                <td>${escapeHtml(String(entry.sample ?? ''))}</td>
                            </tr>
                        `;
                    }).join('')}
                </tbody>
            </table>
        </div>
    `;
}

function renderProviderEmpty(provider) {
    return `
        <div class="research-tool-empty">
            <span class="material-symbols-rounded">data_info_alert</span>
            <h3>Sin resultados suficientes</h3>
            <p>${escapeHtml(String(provider?.message ?? config.emptyCopy ?? ''))}</p>
        </div>
    `;
}

function renderSummaryChip(label, value) {
    return `
        <div class="research-tool-summary-chip">
            <small>${escapeHtml(label)}</small>
            <strong>${escapeHtml(value == null ? 'N/D' : String(value))}</strong>
        </div>
    `;
}

function renderLoadingState() {
    if (!(results instanceof HTMLElement)) {
        return;
    }

    results.innerHTML = `
        <div class="research-tool-empty">
            <span class="material-symbols-rounded research-tool-spin">progress_activity</span>
            <h3>Consultando ${escapeHtml(String(config.providerLabel ?? 'la fuente'))}</h3>
            <p>Estamos reuniendo senales relacionadas para tu idea.</p>
        </div>
    `;
}

function renderEmptyState() {
    if (!(results instanceof HTMLElement)) {
        return;
    }

    results.innerHTML = `
        <div class="research-tool-empty">
            <span class="material-symbols-rounded">manage_search</span>
            <h3>Empieza con una idea</h3>
            <p>${escapeHtml(String(config.emptyCopy ?? ''))}</p>
        </div>
    `;
}

function renderNotice(type, message) {
    if (!(notice instanceof HTMLElement)) {
        return;
    }

    if (!type || !message) {
        notice.className = 'research-tool-notice hidden';
        notice.textContent = '';
        return;
    }

    notice.className = `research-tool-notice research-tool-notice--${escapeToken(type)}`;
    notice.textContent = message;
}

function setButtonBusy(isBusy, label = 'Procesando...') {
    if (!(submitButton instanceof HTMLButtonElement)) {
        return;
    }

    if (!submitButton.dataset.defaultLabel) {
        submitButton.dataset.defaultLabel = submitButton.innerHTML;
    }

    submitButton.disabled = isBusy;
    submitButton.innerHTML = isBusy ? label : submitButton.dataset.defaultLabel;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function escapeToken(value) {
    return String(value ?? '').replace(/[^a-z0-9_-]/gi, '') || 'info';
}
