(() => {
    const root = document.querySelector('[data-customer-pipeline="true"]');

    if (!(root instanceof HTMLElement) || root.dataset.customerPipelineReady === 'true') {
        return;
    }

    root.dataset.customerPipelineReady = 'true';

    const apiUrl = String(root.dataset.apiUrl || '').trim();
    const board = root.querySelector('[data-pipeline-board]');
    const panel = root.querySelector('[data-pipeline-panel]');
    const overlay = root.querySelector('[data-pipeline-overlay]');
    const form = root.querySelector('[data-pipeline-form]');
    const searchInput = root.querySelector('[data-pipeline-search]');
    const createButton = root.querySelector('[data-pipeline-create]');
    const notice = root.querySelector('[data-pipeline-notice]');
    const stageSelect = root.querySelector('[data-pipeline-stage-select]');
    const panelTitle = root.querySelector('[data-pipeline-panel-title]');
    const lostReasonWrap = root.querySelector('[data-pipeline-lost-reason-wrap]');
    const whatsAppLink = root.querySelector('[data-pipeline-whatsapp-link]');
    const emailLink = root.querySelector('[data-pipeline-email-link]');
    const stateNode = document.getElementById('customer-pipeline-state');

    if (!(board instanceof HTMLElement)
        || !(panel instanceof HTMLElement)
        || !(overlay instanceof HTMLElement)
        || !(form instanceof HTMLFormElement)
        || !(stateNode instanceof HTMLScriptElement)
        || apiUrl === '') {
        return;
    }

    const emptyStateMarkup = '<div class="customer-pipeline-empty-column">Arrastra un lead aqui</div>';
    const stageOptionsMarkup = () => state.stages.map((stage) => {
        return `<option value="${escapeHtml(stage.id)}">${escapeHtml(stage.title)}</option>`;
    }).join('');

    let draggedLeadId = '';
    let state = normalizeState(parseJson(stateNode.textContent || '{}'), '');
    let panelState = {
        open: false,
        leadId: '',
        draft: createEmptyLead(),
    };

    if (stageSelect instanceof HTMLSelectElement) {
        stageSelect.innerHTML = stageOptionsMarkup();
    }

    renderBoard();
    syncPanel();

    searchInput?.addEventListener('input', () => {
        state.query = String(searchInput.value || '').trim().toLowerCase();
        renderBoard();
    });

    createButton?.addEventListener('click', () => {
        openPanel('');
    });

    overlay.addEventListener('click', closePanel);

    root.addEventListener('click', (event) => {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        const copyTrigger = target.closest('[data-copy-value]');

        if (copyTrigger instanceof HTMLElement) {
            event.preventDefault();
            void copyValue(copyTrigger.dataset.copyValue || '');
            return;
        }

        const closeTrigger = target.closest('[data-pipeline-close]');

        if (closeTrigger) {
            event.preventDefault();
            closePanel();
            return;
        }

        const openTrigger = target.closest('[data-lead-open]');

        if (openTrigger instanceof HTMLElement) {
            event.preventDefault();
            openPanel(String(openTrigger.dataset.leadOpen || ''));
            return;
        }

        const disabledAction = target.closest('[data-pipeline-disabled="true"]');

        if (disabledAction instanceof HTMLElement) {
            event.preventDefault();
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        await saveLead();
    });

    if (stageSelect instanceof HTMLSelectElement) {
        stageSelect.addEventListener('change', () => {
            panelState.draft.stage_id = stageSelect.value;
            syncPanel();
        });
    }

    board.addEventListener('dragstart', (event) => {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        const card = target.closest('.customer-pipeline-card');

        if (!(card instanceof HTMLElement)) {
            return;
        }

        draggedLeadId = String(card.dataset.leadId || '').trim();

        if (draggedLeadId === '') {
            return;
        }

        card.classList.add('is-dragging');
        event.dataTransfer?.setData('text/plain', draggedLeadId);
        event.dataTransfer?.setDragImage(card, 24, 24);
    });

    board.addEventListener('dragend', () => {
        clearDragState();
    });

    board.addEventListener('dragover', (event) => {
        if (draggedLeadId === '') {
            return;
        }

        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        const stageList = target.closest('[data-stage-list]');

        if (!(stageList instanceof HTMLElement)) {
            return;
        }

        event.preventDefault();
        highlightStage(stageList.dataset.stageList || '');

        const draggedCard = board.querySelector(`.customer-pipeline-card[data-lead-id="${escapeAttribute(draggedLeadId)}"]`);

        if (!(draggedCard instanceof HTMLElement)) {
            return;
        }

        const afterElement = getDragAfterElement(stageList, event.clientY);

        if (afterElement) {
            stageList.insertBefore(draggedCard, afterElement);
        } else {
            stageList.appendChild(draggedCard);
        }
    });

    board.addEventListener('drop', async (event) => {
        if (draggedLeadId === '') {
            return;
        }

        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            clearDragState();
            return;
        }

        const stageList = target.closest('[data-stage-list]');

        if (!(stageList instanceof HTMLElement)) {
            clearDragState();
            return;
        }

        event.preventDefault();

        const stageId = String(stageList.dataset.stageList || '').trim();
        const draggedCard = stageList.querySelector(`.customer-pipeline-card[data-lead-id="${escapeAttribute(draggedLeadId)}"]`);

        if (!(draggedCard instanceof HTMLElement) || stageId === '') {
            clearDragState();
            return;
        }

        const newSortOrder = resolveCardSortOrder(stageList, draggedLeadId);
        const lead = state.leads.find((item) => item.id === draggedLeadId);

        if (!lead) {
            clearDragState();
            return;
        }

        const previousStageId = lead.stage_id;
        const previousSortOrder = lead.sort_order;
        const shouldRequestLostReason = isLostStage(stageId) && String(lead.lost_reason || '').trim() === '';

        lead.stage_id = stageId;
        lead.sort_order = newSortOrder;
        renderBoard();

        try {
            await sendJson(`${apiUrl}&action=move-lead`, {
                lead_id: draggedLeadId,
                stage_id: stageId,
                sort_order: newSortOrder,
            });
            await loadBoard({ preserveLeadId: shouldRequestLostReason ? draggedLeadId : (panelState.open ? panelState.leadId : '') });

            if (shouldRequestLostReason) {
                openPanel(draggedLeadId);
                showNotice('info', 'Agrega un motivo de perdida para cerrar mejor tus metricas.');
            }
        } catch (error) {
            lead.stage_id = previousStageId;
            lead.sort_order = previousSortOrder;
            renderBoard();
            showNotice('error', humanizeError(error));
        } finally {
            clearDragState();
        }
    });

    async function saveLead() {
        const payload = readLeadForm();

        if (payload.full_name === '') {
            showNotice('error', 'El lead necesita un nombre.');
            form.querySelector('[name="full_name"]')?.focus();
            return;
        }

        const submitButton = form.querySelector('[data-pipeline-save]');
        const originalLabel = submitButton?.innerHTML || '';

        if (submitButton instanceof HTMLButtonElement) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="material-symbols-rounded">progress_activity</span><span>Guardando...</span>';
        }

        try {
            const response = await sendJson(`${apiUrl}&action=save-lead`, payload);
            const savedLeadId = String(response.data?.id || payload.id || '');
            await loadBoard({ preserveLeadId: savedLeadId });
            openPanel(savedLeadId);
            showNotice('success', payload.id ? 'Lead actualizado correctamente.' : 'Lead creado correctamente.');
        } catch (error) {
            showNotice('error', humanizeError(error));
        } finally {
            if (submitButton instanceof HTMLButtonElement) {
                submitButton.disabled = false;
                submitButton.innerHTML = originalLabel;
            }
        }
    }

    async function loadBoard(options = {}) {
        const response = await fetch(`${apiUrl}&action=board`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok || payload.success !== true) {
            throw new Error(String(payload.message || 'No fue posible refrescar el tablero.'));
        }

        const nextState = normalizeState(payload.data || {}, state.query);
        state = {
            ...state,
            ...nextState,
            query: state.query,
        };

        if (stageSelect instanceof HTMLSelectElement) {
            stageSelect.innerHTML = stageOptionsMarkup();
        }

        renderBoard();

        if (panelState.open) {
            const preserveLeadId = String(options.preserveLeadId || panelState.leadId || '').trim();
            const refreshedLead = state.leads.find((lead) => lead.id === preserveLeadId) || null;

            if (refreshedLead) {
                panelState.leadId = refreshedLead.id;
                panelState.draft = cloneLead(refreshedLead);
            } else if (preserveLeadId === '') {
                panelState.draft = createEmptyLead();
                panelState.leadId = '';
            } else {
                panelState.open = false;
            }

            syncPanel();
        }
    }

    function renderBoard() {
        board.innerHTML = state.stages.map((stage) => {
            const stageLeads = getVisibleLeadsByStage(stage.id);

            return `
                <section class="customer-pipeline-column${stage.id === highlightedStageId() ? ' is-drop-target' : ''}" data-stage-id="${escapeHtml(stage.id)}">
                    <header class="customer-pipeline-column-head" style="--pipeline-stage-accent: ${escapeHtml(stage.accent_color)};">
                        <div>
                            <h3>${escapeHtml(stage.title)}</h3>
                            <p>${stageLeads.length} lead${stageLeads.length === 1 ? '' : 's'}</p>
                        </div>
                        <span class="customer-pipeline-column-count">${stageLeads.length}</span>
                    </header>

                    <div class="customer-pipeline-column-body" data-stage-list="${escapeHtml(stage.id)}">
                        ${stageLeads.length > 0 ? stageLeads.map(renderLeadCard).join('') : emptyStateMarkup}
                    </div>
                </section>
            `;
        }).join('');
    }

    function renderLeadCard(lead) {
        const phone = sanitizePhone(lead.phone);
        const whatsAppUrl = phone !== '' ? `https://wa.me/${encodeURIComponent(phone)}` : '#';
        const value = formatMoney(lead.estimated_value, lead.currency_code);

        return `
            <article
                class="customer-pipeline-card"
                draggable="true"
                data-lead-id="${escapeHtml(lead.id)}"
                data-stage-id="${escapeHtml(lead.stage_id)}"
                data-sort-order="${escapeHtml(String(lead.sort_order))}"
            >
                <button type="button" class="customer-pipeline-card-body" data-lead-open="${escapeHtml(lead.id)}">
                    <span class="customer-pipeline-card-name">${escapeHtml(lead.full_name || 'Lead sin nombre')}</span>
                    <span class="customer-pipeline-card-tag">${escapeHtml(lead.source_label || 'Sin origen')}</span>
                    <span class="customer-pipeline-card-value">${escapeHtml(value)}</span>
                </button>

                <a
                    class="customer-pipeline-card-wa${phone === '' ? ' is-disabled' : ''}"
                    href="${escapeHtml(whatsAppUrl)}"
                    target="_blank"
                    rel="noreferrer noopener"
                    aria-label="Abrir WhatsApp"
                    data-lead-whatsapp
                >
                    <span class="material-symbols-rounded">forum</span>
                </a>
            </article>
        `;
    }

    function openPanel(leadId) {
        const lead = state.leads.find((item) => item.id === leadId) || null;
        panelState.open = true;
        panelState.leadId = lead?.id || '';
        panelState.draft = lead ? cloneLead(lead) : createEmptyLead();
        syncPanel();
    }

    function closePanel() {
        panelState.open = false;
        panelState.leadId = '';
        panelState.draft = createEmptyLead();
        syncPanel();
    }

    function syncPanel() {
        panel.classList.toggle('hidden', !panelState.open);
        overlay.classList.toggle('hidden', !panelState.open);
        panel.setAttribute('aria-hidden', panelState.open ? 'false' : 'true');

        if (!panelState.open) {
            return;
        }

        const draft = panelState.draft;
        const stage = state.stages.find((item) => item.id === draft.stage_id) || state.stages[0] || { title: 'Nuevo lead' };

        if (panelTitle instanceof HTMLElement) {
            panelTitle.textContent = draft.id ? (draft.full_name || stage.title) : 'Nuevo lead';
        }

        setFieldValue('id', draft.id);
        setFieldValue('project_id', state.project.id);
        setFieldValue('full_name', draft.full_name);
        setFieldValue('stage_id', draft.stage_id);
        setFieldValue('source_label', draft.source_label);
        setFieldValue('company_name', draft.company_name);
        setFieldValue('email', draft.email);
        setFieldValue('phone', draft.phone);
        setFieldValue('estimated_value', draft.estimated_value > 0 ? String(draft.estimated_value) : '');
        setFieldValue('lost_reason', draft.lost_reason);
        setFieldValue('notes', draft.notes);

        const isLost = isLostStage(draft.stage_id);
        lostReasonWrap?.toggleAttribute('hidden', !isLost);

        const sanitizedPhone = sanitizePhone(draft.phone);
        const hasPhone = sanitizedPhone !== '';
        const hasEmail = draft.email.trim() !== '';

        if (whatsAppLink instanceof HTMLAnchorElement) {
            whatsAppLink.href = hasPhone ? `https://wa.me/${encodeURIComponent(sanitizedPhone)}` : '#';
            whatsAppLink.dataset.pipelineDisabled = hasPhone ? 'false' : 'true';
            whatsAppLink.classList.toggle('is-disabled', !hasPhone);
        }

        if (emailLink instanceof HTMLAnchorElement) {
            emailLink.href = hasEmail ? `mailto:${draft.email.trim()}` : '#';
            emailLink.dataset.pipelineDisabled = hasEmail ? 'false' : 'true';
            emailLink.classList.toggle('is-disabled', !hasEmail);
        }
    }

    function readLeadForm() {
        const formData = new FormData(form);

        return {
            id: String(formData.get('id') || '').trim(),
            project_id: state.project.id,
            full_name: String(formData.get('full_name') || '').trim(),
            stage_id: String(formData.get('stage_id') || '').trim(),
            source_label: String(formData.get('source_label') || '').trim(),
            company_name: String(formData.get('company_name') || '').trim(),
            email: String(formData.get('email') || '').trim(),
            phone: String(formData.get('phone') || '').trim(),
            estimated_value: String(formData.get('estimated_value') || '').trim(),
            lost_reason: String(formData.get('lost_reason') || '').trim(),
            notes: String(formData.get('notes') || '').trim(),
            sort_order: panelState.draft.sort_order,
        };
    }

    async function sendJson(url, payload) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok || data.success !== true) {
            throw new Error(String(data.message || 'No fue posible completar la operacion.'));
        }

        return data;
    }

    function getVisibleLeadsByStage(stageId) {
        const query = state.query;

        return state.leads
            .filter((lead) => lead.stage_id === stageId)
            .filter((lead) => query === '' || buildSearchBlob(lead).includes(query))
            .sort((left, right) => left.sort_order - right.sort_order);
    }

    function buildSearchBlob(lead) {
        return [
            lead.full_name,
            lead.email,
            lead.phone,
            lead.company_name,
            lead.source_label,
            ...(Array.isArray(lead.tags) ? lead.tags : []),
        ].join(' ').toLowerCase();
    }

    function highlightedStageId() {
        return board.dataset.dropStageId || '';
    }

    function highlightStage(stageId) {
        board.dataset.dropStageId = stageId;
        board.querySelectorAll('.customer-pipeline-column').forEach((column) => {
            column.classList.toggle('is-drop-target', column.dataset.stageId === stageId);
        });
    }

    function clearDragState() {
        draggedLeadId = '';
        board.dataset.dropStageId = '';
        board.querySelectorAll('.customer-pipeline-column').forEach((column) => {
            column.classList.remove('is-drop-target');
        });
        board.querySelectorAll('.customer-pipeline-card.is-dragging').forEach((card) => {
            card.classList.remove('is-dragging');
        });
    }

    function getDragAfterElement(container, pointerY) {
        const draggableElements = Array.from(container.querySelectorAll('.customer-pipeline-card:not(.is-dragging)'));
        let closest = {
            offset: Number.NEGATIVE_INFINITY,
            element: null,
        };

        draggableElements.forEach((child) => {
            const box = child.getBoundingClientRect();
            const offset = pointerY - box.top - box.height / 2;

            if (offset < 0 && offset > closest.offset) {
                closest = {
                    offset,
                    element: child,
                };
            }
        });

        return closest.element;
    }

    function resolveCardSortOrder(stageList, leadId) {
        const cards = Array.from(stageList.querySelectorAll('.customer-pipeline-card'));
        const currentIndex = cards.findIndex((card) => card.dataset.leadId === leadId);
        const previousCard = currentIndex > 0 ? cards[currentIndex - 1] : null;
        const nextCard = currentIndex >= 0 && currentIndex < cards.length - 1 ? cards[currentIndex + 1] : null;
        const previousSort = previousCard ? Number(previousCard.dataset.sortOrder || '0') : null;
        const nextSort = nextCard ? Number(nextCard.dataset.sortOrder || '0') : null;

        if (previousSort === null && nextSort === null) {
            return 0;
        }

        if (previousSort === null) {
            return nextSort - 1024;
        }

        if (nextSort === null) {
            return previousSort + 1024;
        }

        return (previousSort + nextSort) / 2;
    }

    async function copyValue(value) {
        const text = String(value || '').trim();

        if (text === '') {
            showNotice('error', 'No hay nada para copiar todavia.');
            return;
        }

        try {
            await navigator.clipboard.writeText(text);
            showNotice('success', 'Dato copiado al portapapeles.');
        } catch (error) {
            showNotice('error', 'No fue posible copiar el contenido.');
        }
    }

    function showNotice(type, message) {
        if (!(notice instanceof HTMLElement)) {
            return;
        }

        notice.className = `customer-pipeline-notice customer-pipeline-notice--${escapeToken(type)}`;
        notice.textContent = String(message || '');
        notice.classList.remove('hidden');

        window.clearTimeout(showNotice.timerId);
        showNotice.timerId = window.setTimeout(() => {
            notice.classList.add('hidden');
        }, 3200);
    }

    function isLostStage(stageId) {
        const stage = state.stages.find((item) => item.id === stageId);
        return stage?.key === 'perdido';
    }

    function setFieldValue(name, value) {
        const field = form.elements.namedItem(name);

        if (!(field instanceof HTMLElement)) {
            return;
        }

        if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement) {
            field.value = String(value || '');
        }
    }

    function createEmptyLead() {
        return {
            id: '',
            project_id: state.project?.id || '',
            stage_id: state.stages[0]?.id || '',
            full_name: '',
            email: '',
            phone: '',
            company_name: '',
            source_label: 'Manual',
            source_type: 'manual',
            source_reference: '',
            currency_code: 'MXN',
            estimated_value: 0,
            notes: '',
            lost_reason: '',
            tags: [],
            metadata: {},
            sort_order: 0,
        };
    }

    function cloneLead(lead) {
        return {
            ...lead,
            tags: Array.isArray(lead.tags) ? [...lead.tags] : [],
            metadata: lead.metadata && typeof lead.metadata === 'object' ? { ...lead.metadata } : {},
        };
    }

    function normalizeState(payload, previousQuery = '') {
        const stages = Array.isArray(payload.stages) ? payload.stages.map(normalizeStage).filter(Boolean) : [];
        const leads = Array.isArray(payload.leads) ? payload.leads.map(normalizeLead).filter(Boolean) : [];

        return {
            project: {
                id: String(payload.project?.id || ''),
                name: String(payload.project?.name || 'Proyecto'),
                logo_url: String(payload.project?.logo_url || ''),
            },
            webhook: {
                url: String(payload.webhook?.url || ''),
                public_key: String(payload.webhook?.public_key || ''),
            },
            stages,
            leads,
            query: String(previousQuery || ''),
        };
    }

    function normalizeStage(stage) {
        if (!stage || typeof stage !== 'object') {
            return null;
        }

        return {
            id: String(stage.id || ''),
            key: String(stage.key || ''),
            title: String(stage.title || ''),
            accent_color: String(stage.accent_color || '#1a73e8'),
            sort_order: Number(stage.sort_order || 0),
        };
    }

    function normalizeLead(lead) {
        if (!lead || typeof lead !== 'object') {
            return null;
        }

        return {
            id: String(lead.id || ''),
            project_id: String(lead.project_id || ''),
            stage_id: String(lead.stage_id || ''),
            full_name: String(lead.full_name || ''),
            email: String(lead.email || ''),
            phone: String(lead.phone || ''),
            company_name: String(lead.company_name || ''),
            source_label: String(lead.source_label || ''),
            source_type: String(lead.source_type || ''),
            source_reference: String(lead.source_reference || ''),
            currency_code: String(lead.currency_code || 'MXN'),
            estimated_value: Number(lead.estimated_value || 0),
            notes: String(lead.notes || ''),
            lost_reason: String(lead.lost_reason || ''),
            tags: Array.isArray(lead.tags) ? lead.tags.map((tag) => String(tag)) : [],
            metadata: lead.metadata && typeof lead.metadata === 'object' ? lead.metadata : {},
            assigned_user_id: String(lead.assigned_user_id || ''),
            follow_up_at: String(lead.follow_up_at || ''),
            sort_order: Number(lead.sort_order || 0),
            created_at: String(lead.created_at || ''),
        };
    }

    function formatMoney(value, currencyCode) {
        try {
            return new Intl.NumberFormat('es-MX', {
                style: 'currency',
                currency: currencyCode || 'MXN',
                maximumFractionDigits: 0,
            }).format(Number(value || 0));
        } catch (error) {
            return `$${Number(value || 0).toFixed(0)}`;
        }
    }

    function sanitizePhone(value) {
        return String(value || '').replace(/\D+/g, '');
    }

    function parseJson(value) {
        try {
            const parsed = JSON.parse(String(value || '').trim());
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (error) {
            return {};
        }
    }

    function humanizeError(error) {
        return error instanceof Error && error.message ? error.message : 'No fue posible completar la operacion.';
    }

    function escapeHtml(value) {
        return String(value || '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function escapeAttribute(value) {
        return String(value || '').replaceAll('"', '\\"');
    }

    function escapeToken(value) {
        return String(value || '').replace(/[^a-z0-9_-]/gi, '') || 'info';
    }
})();
