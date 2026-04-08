(() => {
    const root = document.querySelector('[data-wa-bot-app="true"]');

    if (!(root instanceof HTMLElement) || root.dataset.ready === 'true') {
        return;
    }

    root.dataset.ready = 'true';

    const apiUrl = String(root.dataset.apiUrl || '').trim();
    const stateNode = document.getElementById('wa-bot-state');
    const notice = root.querySelector('[data-wa-bot-notice]');
    const botSelect = root.querySelector('[data-wa-bot-select]');
    const createBotButton = root.querySelector('[data-wa-bot-create]');
    const tabTriggers = Array.from(root.querySelectorAll('[data-wa-tab-trigger]'));
    const panels = Array.from(root.querySelectorAll('[data-wa-tab-panel]'));

    if (!(stateNode instanceof HTMLScriptElement) || !(botSelect instanceof HTMLSelectElement) || apiUrl === '') {
        return;
    }

    let state = normalizeState(parseJson(stateNode.textContent || '{}'));
    let currentTab = 'setup';
    let inboxFilter = 'all';
    let selectedTemplateId = state.templates[0]?.id || '__new__';
    let humanAlertCount = Number(state.inbox_counts?.human || 0);
    let hasInteracted = false;
    let pollTimer = 0;

    document.addEventListener('click', () => {
        hasInteracted = true;
    }, { once: true });

    renderApp();
    startPolling();

    createBotButton.addEventListener('click', async () => {
        await postJson('create-bot', {});
    });

    botSelect.addEventListener('change', async () => {
        await loadState({
            bot_id: botSelect.value,
            conversation_id: '',
        });
    });

    root.addEventListener('click', async (event) => {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        const tabTrigger = target.closest('[data-wa-tab-trigger]');

        if (tabTrigger instanceof HTMLElement) {
            event.preventDefault();
            currentTab = String(tabTrigger.dataset.waTabTrigger || 'setup');
            renderApp();
            startPolling();
            return;
        }

        const addMainButton = target.closest('[data-add-main-button]');

        if (addMainButton) {
            event.preventDefault();
            addFlowRow('main');
            return;
        }

        const addListOption = target.closest('[data-add-list-option]');

        if (addListOption) {
            event.preventDefault();
            addFlowRow('list');
            return;
        }

        const addCampaignTrigger = target.closest('[data-add-campaign-trigger]');

        if (addCampaignTrigger) {
            event.preventDefault();
            addCampaignTriggerRow();
            return;
        }

        const removeRow = target.closest('[data-remove-row]');

        if (removeRow instanceof HTMLElement) {
            event.preventDefault();
            removeRow.closest('[data-repeater-row]')?.remove();
            refreshRepeaterState();
            return;
        }

        const uploadTrigger = target.closest('[data-upload-trigger]');

        if (uploadTrigger instanceof HTMLElement) {
            event.preventDefault();
            const fileInput = uploadTrigger.parentElement?.querySelector('input[type="file"]');

            if (fileInput instanceof HTMLInputElement) {
                fileInput.click();
            }
            return;
        }

        const saveSetupButton = target.closest('[data-save-setup]');

        if (saveSetupButton) {
            event.preventDefault();
            await saveSetup();
            return;
        }

        const filterTrigger = target.closest('[data-inbox-filter]');

        if (filterTrigger instanceof HTMLElement) {
            event.preventDefault();
            inboxFilter = String(filterTrigger.dataset.inboxFilter || 'all');
            renderInboxPanel();
            return;
        }

        const chatTrigger = target.closest('[data-conversation-open]');

        if (chatTrigger instanceof HTMLElement) {
            event.preventDefault();
            await loadState({
                bot_id: activeBot()?.id || '',
                conversation_id: String(chatTrigger.dataset.conversationOpen || ''),
            });
            currentTab = 'inbox';
            renderApp();
            return;
        }

        const toggleConversationButton = target.closest('[data-toggle-conversation]');

        if (toggleConversationButton instanceof HTMLElement) {
            event.preventDefault();
            await postJson('toggle-conversation', {
                bot_id: activeBot()?.id || '',
                conversation_id: String(toggleConversationButton.dataset.conversationId || ''),
                mode: String(toggleConversationButton.dataset.toggleConversation || ''),
            });
            return;
        }

        const newTemplateButton = target.closest('[data-template-new]');

        if (newTemplateButton) {
            event.preventDefault();
            selectedTemplateId = '__new__';
            renderTemplatesPanel();
            return;
        }

        const templateSelectButton = target.closest('[data-template-select]');

        if (templateSelectButton instanceof HTMLElement) {
            event.preventDefault();
            selectedTemplateId = String(templateSelectButton.dataset.templateSelect || '__new__');
            renderTemplatesPanel();
            return;
        }

        const saveTemplateButton = target.closest('[data-save-template]');

        if (saveTemplateButton) {
            event.preventDefault();
            await saveTemplate();
            return;
        }

        const variableInsertButton = target.closest('[data-insert-variable]');

        if (variableInsertButton instanceof HTMLElement) {
            event.preventDefault();
            insertTemplateVariable(String(variableInsertButton.dataset.insertVariable || ''));
            return;
        }

        const copyButton = target.closest('[data-copy-text]');

        if (copyButton instanceof HTMLElement) {
            event.preventDefault();
            await copyText(String(copyButton.dataset.copyText || ''));
        }
    });

    root.addEventListener('change', async (event) => {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        const fileInput = target.closest('[data-upload-input]');

        if (!(fileInput instanceof HTMLInputElement) || !fileInput.files || fileInput.files.length === 0) {
            return;
        }

        const file = fileInput.files[0];
        const targetKey = String(fileInput.dataset.uploadTarget || 'generic');

        try {
            const uploaded = await uploadFile(file, targetKey);
            applyUploadedFile(fileInput, uploaded);
            showNotice('success', 'Archivo subido correctamente.');
        } catch (error) {
            showNotice('error', humanizeError(error));
        } finally {
            fileInput.value = '';
        }
    });

    root.addEventListener('submit', async (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (form.matches('[data-message-form]')) {
            event.preventDefault();
            await sendMessage(form);
        }
    });

    function renderApp() {
        renderToolbar();
        updateTabState();
        renderSetupPanel();
        renderInboxPanel();
        renderTemplatesPanel();
    }

    function renderToolbar() {
        const bots = state.bots || [];
        botSelect.innerHTML = bots.map((bot) => {
            const id = String(bot.id || '');
            const selected = id === String(state.active_bot_id || '') ? ' selected' : '';
            return `<option value="${escapeHtml(id)}"${selected}>${escapeHtml(bot.name || 'Bot')}</option>`;
        }).join('');
    }

    function updateTabState() {
        tabTriggers.forEach((button) => {
            const tabKey = String(button.getAttribute('data-wa-tab-trigger') || '');
            button.classList.toggle('is-active', tabKey === currentTab);
        });

        panels.forEach((panel) => {
            const tabKey = String(panel.getAttribute('data-wa-tab-panel') || '');
            panel.classList.toggle('hidden', tabKey !== currentTab);
        });
    }

    function renderSetupPanel() {
        const panel = root.querySelector('[data-wa-tab-panel="setup"]');
        const bot = activeBot();

        if (!(panel instanceof HTMLElement)) {
            return;
        }

        if (!bot) {
            panel.innerHTML = renderEmptyState('Aun no hay bots disponibles para este proyecto.');
            return;
        }

        const flow = normalizeFlowDefinition(bot.flow_definition);
        const schedule = normalizeScheduleDefinition(bot.schedule_definition);
        const routing = normalizeRoutingDefinition(bot.routing_definition);
        const webhook = state.webhook || { url: '', public_key: '', verify_token: '' };
        const templateOptions = renderTemplateOptions(state.templates || [], routing.follow_up_template_id || '');

        panel.innerHTML = `
            <div class="wa-bot-grid wa-bot-grid--setup">
                <form class="wa-card-stack" data-setup-form>
                    <article class="wa-card">
                        <div class="wa-card-head">
                            <div>
                                <p class="wa-card-eyebrow">Identidad</p>
                                <h2>Personalidad del bot</h2>
                            </div>
                            <span class="wa-status-pill wa-status-pill--${escapeHtml(bot.status || 'draft')}">${escapeHtml(labelForBotStatus(bot.status || 'draft'))}</span>
                        </div>

                        <div class="wa-field-grid">
                            <label class="wa-field wa-field--wide">
                                <span>Nombre del bot</span>
                                <input type="text" name="name" value="${escapeHtml(bot.name || '')}" placeholder="Bot de ventas">
                            </label>

                            <label class="wa-field">
                                <span>Tono de voz</span>
                                <select name="tone">
                                    ${renderSelectOptions([
                                        ['formal', 'Formal'],
                                        ['amigable', 'Amigable'],
                                        ['directo', 'Directo'],
                                    ], bot.tone || 'amigable')}
                                </select>
                            </label>

                            <label class="wa-field">
                                <span>Estado del bot</span>
                                <select name="status">
                                    ${renderSelectOptions([
                                        ['draft', 'Borrador'],
                                        ['active', 'Activo'],
                                        ['paused', 'Pausado'],
                                    ], bot.status || 'draft')}
                                </select>
                            </label>

                            <label class="wa-field wa-field--wide">
                                <span>Mensaje de bienvenida</span>
                                <textarea name="welcome_message" rows="3" placeholder="Hola, gracias por escribirnos...">${escapeHtml(bot.welcome_message || '')}</textarea>
                            </label>

                            <label class="wa-field wa-field--wide">
                                <span>Mensaje al transferir a humano</span>
                                <textarea name="handoff_message" rows="2">${escapeHtml(bot.handoff_message || '')}</textarea>
                            </label>

                            <label class="wa-field wa-field--wide">
                                <span>Mensaje fuera de horario</span>
                                <textarea name="off_hours_message" rows="2">${escapeHtml(bot.off_hours_message || '')}</textarea>
                            </label>

                            <label class="wa-field wa-field--wide">
                                <span>Mensaje cuando no entiende</span>
                                <textarea name="fallback_message" rows="2">${escapeHtml(bot.fallback_message || '')}</textarea>
                            </label>

                            <label class="wa-field">
                                <span>Intentos antes de pasar a humano</span>
                                <input type="number" name="unknown_attempt_limit" min="1" max="5" value="${escapeHtml(String(bot.unknown_attempt_limit || 3))}">
                            </label>

                            <label class="wa-field">
                                <span>Zona horaria</span>
                                <input type="text" name="timezone" value="${escapeHtml(bot.timezone || 'America/Mexico_City')}">
                            </label>
                        </div>
                    </article>

                    <article class="wa-card">
                        <div class="wa-card-head">
                            <div>
                                <p class="wa-card-eyebrow">Flujo principal</p>
                                <h2>Botones nativos</h2>
                            </div>
                            <button type="button" class="wa-link-button" data-add-main-button ${flow.main_buttons.length >= 3 ? 'disabled' : ''}>Agregar boton</button>
                        </div>
                        <p class="wa-helper">Usa hasta 3 botones visibles. Cada uno puede responder, pedir datos, crear leads y escalar a un humano.</p>
                        <div class="wa-repeater" data-main-buttons>
                            ${flow.main_buttons.map((item, index) => renderFlowRow(item, index, 'main')).join('')}
                        </div>
                    </article>

                    <article class="wa-card">
                        <div class="wa-card-head">
                            <div>
                                <p class="wa-card-eyebrow">Lista desplegable</p>
                                <h2>Opciones secundarias</h2>
                            </div>
                            <button type="button" class="wa-link-button" data-add-list-option ${flow.list_options.length >= 10 ? 'disabled' : ''}>Agregar opcion</button>
                        </div>
                        <p class="wa-helper">Sirve para menus mas profundos sin obligar al cliente a escribir palabras exactas.</p>
                        <div class="wa-repeater" data-list-options>
                            ${flow.list_options.map((item, index) => renderFlowRow(item, index, 'list')).join('')}
                        </div>
                    </article>

                    <article class="wa-card">
                        <div class="wa-card-head">
                            <div>
                                <p class="wa-card-eyebrow">Campanas</p>
                                <h2>Triggers de Click-to-WhatsApp</h2>
                            </div>
                            <button type="button" class="wa-link-button" data-add-campaign-trigger>Agregar trigger</button>
                        </div>
                        <div class="wa-repeater" data-campaign-triggers>
                            ${routing.campaign_triggers.map((trigger) => renderCampaignRow(trigger, allOptionChoices(flow))).join('')}
                        </div>
                    </article>

                    <article class="wa-card">
                        <div class="wa-card-head">
                            <div>
                                <p class="wa-card-eyebrow">Horarios</p>
                                <h2>Atencion humana</h2>
                            </div>
                        </div>
                        <div class="wa-schedule-grid">
                            ${Object.entries(schedule.days).map(([dayKey, dayConfig]) => renderScheduleRow(dayKey, dayConfig)).join('')}
                        </div>
                    </article>

                    <article class="wa-card">
                        <div class="wa-card-head">
                            <div>
                                <p class="wa-card-eyebrow">Seguimiento</p>
                                <h2>Acciones por etapa del Kanban</h2>
                            </div>
                        </div>
                        <div class="wa-field-grid">
                            <label class="wa-field">
                                <span>Stage key a escuchar</span>
                                <input type="text" name="follow_up_stage_key" value="${escapeHtml(routing.follow_up_stage_key || 'contactar-de-nuevo')}" placeholder="contactar-de-nuevo">
                            </label>
                            <label class="wa-field">
                                <span>Plantilla recomendada</span>
                                <select name="follow_up_template_id">
                                    <option value="">Elegir mas tarde</option>
                                    ${templateOptions}
                                </select>
                            </label>
                        </div>
                    </article>
                </form>

                <aside class="wa-side-stack">
                    <article class="wa-card">
                        <div class="wa-card-head">
                            <div>
                                <p class="wa-card-eyebrow">Conexion</p>
                                <h2>Meta webhook</h2>
                            </div>
                        </div>
                        <div class="wa-connection-grid">
                            <div class="wa-connection-field">
                                <span>Callback URL</span>
                                <code>${escapeHtml(webhook.url || '')}</code>
                                <button type="button" class="wa-link-button" data-copy-text="${escapeHtml(webhook.url || '')}">Copiar</button>
                            </div>
                            <div class="wa-connection-field">
                                <span>Verify token</span>
                                <code>${escapeHtml(webhook.verify_token || '')}</code>
                                <button type="button" class="wa-link-button" data-copy-text="${escapeHtml(webhook.verify_token || '')}">Copiar</button>
                            </div>
                        </div>
                        <div class="wa-field-grid wa-field-grid--compact">
                            <label class="wa-field">
                                <span>Phone Number ID</span>
                                <input type="text" name="provider_phone_number_id" value="${escapeHtml(bot.provider_phone_number_id || '')}" placeholder="Opcional por ahora">
                            </label>
                            <label class="wa-field">
                                <span>WABA ID</span>
                                <input type="text" name="provider_waba_id" value="${escapeHtml(bot.provider_waba_id || '')}" placeholder="Opcional por ahora">
                            </label>
                        </div>
                    </article>

                    <article class="wa-card">
                        <div class="wa-card-head">
                            <div>
                                <p class="wa-card-eyebrow">Resumen</p>
                                <h2>Lo que hace este bot</h2>
                            </div>
                        </div>
                        <ul class="wa-summary-list">
                            <li>Saluda y ofrece botones nativos o lista desplegable.</li>
                            <li>Valida correos antes de aceptarlos en una captura.</li>
                            <li>Crea leads dentro de Seguimiento de Clientes cuando termina una captura comercial.</li>
                            <li>Pasa conversaciones a humano si el usuario lo pide o si el bot falla varias veces.</li>
                            <li>Bloquea texto libre cuando ya cerro la ventana de 24 horas y fuerza plantillas.</li>
                        </ul>
                    </article>

                    <button type="button" class="wa-primary-button wa-primary-button--block" data-save-setup>
                        <span class="material-symbols-rounded">save</span>
                        <span>Guardar configuracion</span>
                    </button>
                </aside>
            </div>
        `;
    }

    function renderInboxPanel() {
        const panel = root.querySelector('[data-wa-tab-panel="inbox"]');
        const conversation = activeConversation();

        if (!(panel instanceof HTMLElement)) {
            return;
        }

        const activeTemplates = state.templates || [];
        const filterButtons = [
            ['all', 'Todos', (state.conversations || []).length],
            ['bot', 'Atendidos por el Bot', Number(state.inbox_counts?.bot || 0)],
            ['human', 'Esperando a un Humano', Number(state.inbox_counts?.human || 0)],
        ];

        panel.innerHTML = `
            <div class="wa-inbox-layout">
                <aside class="wa-inbox-sidebar">
                    <div class="wa-inbox-filters">
                        ${filterButtons.map(([key, label, count]) => `
                            <button type="button" class="wa-filter-pill${inboxFilter === key ? ' is-active' : ''}${key === 'human' && Number(count) > 0 ? ' is-alert' : ''}" data-inbox-filter="${escapeHtml(String(key))}">
                                <span>${escapeHtml(String(label))}</span>
                                <strong>${escapeHtml(String(count))}</strong>
                            </button>
                        `).join('')}
                    </div>
                    <div class="wa-chat-list">
                        ${renderConversationList()}
                    </div>
                </aside>

                <section class="wa-chat-panel">
                    ${conversation ? renderConversationView(conversation, activeTemplates) : renderEmptyChat()}
                </section>
            </div>
        `;
    }

    function renderTemplatesPanel() {
        const panel = root.querySelector('[data-wa-tab-panel="templates"]');
        const template = selectedTemplate();

        if (!(panel instanceof HTMLElement)) {
            return;
        }

        panel.innerHTML = `
            <div class="wa-template-layout">
                <aside class="wa-template-sidebar">
                    <div class="wa-template-sidebar-head">
                        <div>
                            <p class="wa-card-eyebrow">Gestor de plantillas</p>
                            <h2>Mensajes aprobables</h2>
                        </div>
                        <button type="button" class="wa-link-button" data-template-new>Nueva</button>
                    </div>
                    <div class="wa-template-list">
                        ${(state.templates || []).map((item) => `
                            <button type="button" class="wa-template-list-item${selectedTemplateId === item.id ? ' is-active' : ''}" data-template-select="${escapeHtml(item.id)}">
                                <strong>${escapeHtml(item.name || 'Plantilla')}</strong>
                                <span>${escapeHtml(labelForTemplateStatus(item.approval_status || 'pendiente'))}</span>
                            </button>
                        `).join('')}
                    </div>
                </aside>

                <section class="wa-template-editor">
                    <form class="wa-card-stack" data-template-form>
                        <article class="wa-card">
                            <div class="wa-card-head">
                                <div>
                                    <p class="wa-card-eyebrow">Editor</p>
                                    <h2>${escapeHtml(template.id ? 'Editar plantilla' : 'Nueva plantilla')}</h2>
                                </div>
                                <span class="wa-status-pill wa-status-pill--${escapeHtml(template.approval_status || 'pendiente')}">${escapeHtml(labelForTemplateStatus(template.approval_status || 'pendiente'))}</span>
                            </div>
                            <div class="wa-field-grid">
                                <input type="hidden" name="id" value="${escapeHtml(template.id || '')}">
                                <input type="hidden" name="bot_id" value="${escapeHtml(activeBot()?.id || '')}">
                                <label class="wa-field">
                                    <span>Nombre</span>
                                    <input type="text" name="name" value="${escapeHtml(template.name || '')}" placeholder="Seguimiento 30 dias">
                                </label>
                                <label class="wa-field">
                                    <span>Categoria</span>
                                    <select name="category">
                                        ${renderSelectOptions([
                                            ['utility', 'Utility'],
                                            ['marketing', 'Marketing'],
                                            ['follow_up', 'Follow up'],
                                        ], template.category || 'utility')}
                                    </select>
                                </label>
                                <label class="wa-field">
                                    <span>Estado Meta</span>
                                    <select name="approval_status">
                                        ${renderSelectOptions([
                                            ['pendiente', 'Pendiente'],
                                            ['aprobado', 'Aprobado'],
                                            ['rechazado', 'Rechazado'],
                                        ], template.approval_status || 'pendiente')}
                                    </select>
                                </label>
                                <label class="wa-field">
                                    <span>Meta template id</span>
                                    <input type="text" name="meta_template_id" value="${escapeHtml(template.meta_template_id || '')}" placeholder="Opcional">
                                </label>
                                <label class="wa-field wa-field--wide">
                                    <span>Header</span>
                                    <input type="text" name="header_text" value="${escapeHtml(template.header_text || '')}" placeholder="Promocion de verano">
                                </label>
                                <label class="wa-field wa-field--wide">
                                    <span>Cuerpo del mensaje</span>
                                    <textarea name="body_text" rows="6" placeholder="Hola {Nombre}, seguimos atentos...">${escapeHtml(template.body_text || '')}</textarea>
                                </label>
                                <label class="wa-field wa-field--wide">
                                    <span>Footer</span>
                                    <input type="text" name="footer_text" value="${escapeHtml(template.footer_text || '')}" placeholder="Responde este mensaje si necesitas ayuda">
                                </label>
                            </div>
                        </article>

                        <article class="wa-card">
                            <div class="wa-card-head">
                                <div>
                                    <p class="wa-card-eyebrow">Variables</p>
                                    <h2>Insercion rapida</h2>
                                </div>
                            </div>
                            <div class="wa-variable-chips">
                                ${['{Nombre}', '{Telefono}', '{Email}', '{Empresa}'].map((variable) => `
                                    <button type="button" class="wa-chip-button" data-insert-variable="${escapeHtml(variable)}">${escapeHtml(variable)}</button>
                                `).join('')}
                            </div>
                        </article>

                        <article class="wa-card">
                            <div class="wa-card-head">
                                <div>
                                    <p class="wa-card-eyebrow">Adjunto</p>
                                    <h2>Media enriquecida</h2>
                                </div>
                            </div>
                            <div class="wa-attachment-box" data-attachment-preview>
                                ${renderAttachmentPreview(template.media || {
                                    kind: template.media_kind || 'none',
                                    url: template.media_url || '',
                                    path: template.media_storage_path || '',
                                    file_name: '',
                                })}
                            </div>
                            <div class="wa-upload-inline">
                                <button type="button" class="wa-link-button" data-upload-trigger>Subir archivo</button>
                                <input type="file" class="hidden" data-upload-input data-upload-target="template-media" accept=".jpg,.jpeg,.png,.webp,.gif,.mp3,.ogg,.wav,.pdf">
                                <input type="hidden" name="media_kind" value="${escapeHtml(template.media?.kind || template.media_kind || 'none')}">
                                <input type="hidden" name="media_url" value="${escapeHtml(template.media?.url || template.media_url || '')}">
                                <input type="hidden" name="media_storage_path" value="${escapeHtml(template.media?.path || template.media_storage_path || '')}">
                                <input type="hidden" name="media_mime_type" value="${escapeHtml(template.media?.mime_type || '')}">
                                <input type="hidden" name="media_file_name" value="${escapeHtml(template.media?.file_name || '')}">
                            </div>
                        </article>

                        <button type="button" class="wa-primary-button" data-save-template>
                            <span class="material-symbols-rounded">save</span>
                            <span>Guardar plantilla</span>
                        </button>
                    </form>
                </section>
            </div>
        `;
    }

    function renderConversationList() {
        const conversations = filteredConversations();

        if (conversations.length === 0) {
            return renderEmptyState('Aun no hay conversaciones en esta bandeja.');
        }

        return conversations.map((conversation) => {
            const isActive = String(conversation.id || '') === String(state.active_conversation_id || '');
            const sessionOpen = hasOpenSession(conversation);

            return `
                <button type="button" class="wa-chat-list-item${isActive ? ' is-active' : ''}" data-conversation-open="${escapeHtml(conversation.id || '')}">
                    <div class="wa-chat-list-row">
                        <strong>${escapeHtml(conversation.customer_name || conversation.customer_phone || 'Contacto')}</strong>
                        <span class="wa-chat-time">${escapeHtml(formatShortDate(conversation.updated_at || conversation.created_at || ''))}</span>
                    </div>
                    <div class="wa-chat-list-row">
                        <span class="wa-chat-source">${escapeHtml(conversation.source_label || 'WhatsApp')}</span>
                        <span class="wa-session-pill${sessionOpen ? '' : ' is-closed'}">${sessionOpen ? '24h abierta' : 'Solo plantilla'}</span>
                    </div>
                    <p>${escapeHtml(conversation.last_message_preview || 'Sin actividad aun')}</p>
                </button>
            `;
        }).join('');
    }

    function renderConversationView(conversation, templates) {
        const sessionOpen = hasOpenSession(conversation);
        const forcedTemplateId = String(conversation.bot_context?.forced_template_id || '');

        return `
            <div class="wa-chat-header">
                <div>
                    <p class="wa-card-eyebrow">Conversacion</p>
                    <h2>${escapeHtml(conversation.customer_name || conversation.customer_phone || 'Contacto')}</h2>
                    <p class="wa-chat-meta">${escapeHtml(conversation.customer_phone || '')}${conversation.customer_email ? ` - ${escapeHtml(conversation.customer_email)}` : ''}${conversation.customer_company ? ` - ${escapeHtml(conversation.customer_company)}` : ''}</p>
                </div>
                <div class="wa-chat-actions">
                    <a class="wa-icon-link" href="${escapeHtml(conversation.customer_phone ? `https://wa.me/${encodeURIComponent(conversation.customer_phone)}` : '#')}" target="_blank" rel="noreferrer noopener">
                        <span class="material-symbols-rounded">forum</span>
                    </a>
                    ${conversation.customer_email ? `
                        <a class="wa-icon-link" href="mailto:${escapeHtml(conversation.customer_email)}">
                            <span class="material-symbols-rounded">mail</span>
                        </a>
                    ` : ''}
                    <button type="button" class="wa-primary-button wa-primary-button--small" data-toggle-conversation="${conversation.conversation_state === 'bot_pausado' ? 'resume_bot' : 'pause_bot'}" data-conversation-id="${escapeHtml(conversation.id || '')}">
                        <span class="material-symbols-rounded">${conversation.conversation_state === 'bot_pausado' ? 'smart_toy' : 'person'}</span>
                        <span>${conversation.conversation_state === 'bot_pausado' ? 'Reactivar bot' : 'Pausar Bot y Responder'}</span>
                    </button>
                </div>
            </div>

            <div class="wa-chat-body">
                ${(state.messages || []).map(renderMessageBubble).join('') || renderEmptyState('Todavia no hay mensajes en esta conversacion.')}
            </div>

            <form class="wa-chat-composer" data-message-form>
                <input type="hidden" name="conversation_id" value="${escapeHtml(conversation.id || '')}">
                <input type="hidden" name="bot_id" value="${escapeHtml(activeBot()?.id || '')}">
                ${!sessionOpen ? `
                    <div class="wa-session-warning">
                        La ventana de 24 horas esta cerrada. Usa una plantilla aprobada para retomar la conversacion.
                    </div>
                ` : ''}
                <div class="wa-field-grid wa-field-grid--compact">
                    <label class="wa-field wa-field--wide">
                        <span>Texto libre</span>
                        <textarea name="body" rows="3" placeholder="${sessionOpen ? 'Escribe una respuesta humana...' : 'Selecciona una plantilla para continuar'}" ${sessionOpen ? '' : 'disabled'}></textarea>
                    </label>
                    <label class="wa-field">
                        <span>Plantilla</span>
                        <select name="template_id">
                            <option value="">Sin plantilla</option>
                            ${templates.map((template) => `
                                <option value="${escapeHtml(template.id || '')}"${forcedTemplateId !== '' && forcedTemplateId === template.id ? ' selected' : ''}>
                                    ${escapeHtml(template.name || 'Plantilla')}
                                </option>
                            `).join('')}
                        </select>
                    </label>
                </div>
                <button type="submit" class="wa-primary-button">
                    <span class="material-symbols-rounded">send</span>
                    <span>${sessionOpen ? 'Enviar' : 'Enviar plantilla'}</span>
                </button>
            </form>
        `;
    }

    function renderMessageBubble(message) {
        const outgoing = String(message.direction || 'incoming') === 'outgoing';
        const statusIcon = outgoing ? renderDeliveryIcon(message.delivery_status || 'sent') : '';

        return `
            <article class="wa-message-bubble${outgoing ? ' is-outgoing' : ''}">
                <div class="wa-message-meta">
                    <strong>${escapeHtml(labelForMessageAuthor(message.author_type || 'customer'))}</strong>
                    <span>${escapeHtml(formatShortDate(message.created_at || ''))}</span>
                </div>
                <div class="wa-message-body">${escapeHtml(message.body || '') || '<span class="wa-message-empty">Archivo enviado</span>'}</div>
                ${message.attachment_url ? `
                    <a class="wa-message-attachment" href="${escapeHtml(message.attachment_url)}" target="_blank" rel="noreferrer noopener">
                        <span class="material-symbols-rounded">${iconForAttachmentKind(message.message_type || 'document')}</span>
                        <span>Abrir archivo</span>
                    </a>
                ` : ''}
                ${statusIcon}
            </article>
        `;
    }

    function renderEmptyChat() {
        return `
            <div class="wa-empty-chat">
                <span class="material-symbols-rounded">chat</span>
                <h2>Selecciona una conversacion</h2>
                <p>Desde aqui podras pausar el bot, responder manualmente o continuar con plantillas aprobadas.</p>
            </div>
        `;
    }

    function saveSetup() {
        const form = root.querySelector('[data-setup-form]');

        if (!(form instanceof HTMLFormElement)) {
            return Promise.resolve();
        }

        const bot = activeBot();

        if (!bot) {
            return Promise.resolve();
        }

        const payload = {
            id: String(bot.id || ''),
            active_conversation_id: String(state.active_conversation_id || ''),
            name: String(form.querySelector('[name="name"]')?.value || '').trim(),
            tone: String(form.querySelector('[name="tone"]')?.value || 'amigable'),
            status: String(form.querySelector('[name="status"]')?.value || 'draft'),
            welcome_message: String(form.querySelector('[name="welcome_message"]')?.value || '').trim(),
            handoff_message: String(form.querySelector('[name="handoff_message"]')?.value || '').trim(),
            off_hours_message: String(form.querySelector('[name="off_hours_message"]')?.value || '').trim(),
            fallback_message: String(form.querySelector('[name="fallback_message"]')?.value || '').trim(),
            unknown_attempt_limit: Number(form.querySelector('[name="unknown_attempt_limit"]')?.value || 3),
            timezone: String(form.querySelector('[name="timezone"]')?.value || 'America/Mexico_City').trim(),
            provider_phone_number_id: String(form.querySelector('[name="provider_phone_number_id"]')?.value || '').trim(),
            provider_waba_id: String(form.querySelector('[name="provider_waba_id"]')?.value || '').trim(),
            flow_definition: {
                main_buttons: readFlowRows('[data-main-buttons] [data-repeater-row]'),
                list_options: readFlowRows('[data-list-options] [data-repeater-row]'),
                field_prompts: {
                    name: 'Antes de avanzar, me compartes tu nombre?',
                    email: 'Perfecto. Cual es tu correo electronico?',
                    phone: 'En que numero te podemos contactar?',
                    requirement: 'Cuentame brevemente que necesitas.',
                },
            },
            schedule_definition: readScheduleDefinition(),
            routing_definition: {
                follow_up_stage_key: String(form.querySelector('[name="follow_up_stage_key"]')?.value || 'contactar-de-nuevo').trim(),
                follow_up_template_id: String(form.querySelector('[name="follow_up_template_id"]')?.value || '').trim(),
                campaign_triggers: readCampaignRows(),
            },
        };

        return postJson('save-bot', payload);
    }

    function saveTemplate() {
        const form = root.querySelector('[data-template-form]');

        if (!(form instanceof HTMLFormElement) || !activeBot()) {
            return Promise.resolve();
        }

        const payload = {
            id: String(form.querySelector('[name="id"]')?.value || '').trim(),
            bot_id: String(activeBot()?.id || ''),
            active_conversation_id: String(state.active_conversation_id || ''),
            name: String(form.querySelector('[name="name"]')?.value || '').trim(),
            category: String(form.querySelector('[name="category"]')?.value || 'utility'),
            approval_status: String(form.querySelector('[name="approval_status"]')?.value || 'pendiente'),
            meta_template_id: String(form.querySelector('[name="meta_template_id"]')?.value || '').trim(),
            header_text: String(form.querySelector('[name="header_text"]')?.value || '').trim(),
            body_text: String(form.querySelector('[name="body_text"]')?.value || '').trim(),
            footer_text: String(form.querySelector('[name="footer_text"]')?.value || '').trim(),
            variables: extractVariables(String(form.querySelector('[name="body_text"]')?.value || '')),
            media: {
                kind: String(form.querySelector('[name="media_kind"]')?.value || 'none'),
                url: String(form.querySelector('[name="media_url"]')?.value || ''),
                path: String(form.querySelector('[name="media_storage_path"]')?.value || ''),
                mime_type: String(form.querySelector('[name="media_mime_type"]')?.value || ''),
                file_name: String(form.querySelector('[name="media_file_name"]')?.value || ''),
            },
        };

        return postJson('save-template', payload).then(() => {
            selectedTemplateId = payload.id || selectedTemplateId;
        });
    }

    function sendMessage(form) {
        const payload = {
            bot_id: String(form.querySelector('[name="bot_id"]')?.value || ''),
            conversation_id: String(form.querySelector('[name="conversation_id"]')?.value || ''),
            body: String(form.querySelector('[name="body"]')?.value || '').trim(),
            template_id: String(form.querySelector('[name="template_id"]')?.value || '').trim(),
        };

        return postJson('send-message', payload);
    }

    function readFlowRows(selector) {
        return Array.from(root.querySelectorAll(selector)).map((row) => {
            const label = String(row.querySelector('[data-field="label"]')?.value || '').trim();
            const responseText = String(row.querySelector('[data-field="response_text"]')?.value || '').trim();
            const successMessage = String(row.querySelector('[data-field="success_message"]')?.value || '').trim();
            const captureSequence = [
                String(row.querySelector('[data-field="capture_one"]')?.value || '').trim(),
                String(row.querySelector('[data-field="capture_two"]')?.value || '').trim(),
                String(row.querySelector('[data-field="capture_three"]')?.value || '').trim(),
            ].filter(Boolean);

            return {
                id: String(row.querySelector('[data-field="id"]')?.value || label).trim(),
                label,
                response_text: responseText,
                capture_sequence: Array.from(new Set(captureSequence)),
                human_on_complete: Boolean(row.querySelector('[data-field="human_on_complete"]')?.checked),
                create_lead_on_complete: Boolean(row.querySelector('[data-field="create_lead_on_complete"]')?.checked),
                success_message: successMessage,
                attachment: readAttachmentFields(row),
            };
        }).filter((item) => item.label !== '');
    }

    function readAttachmentFields(scope) {
        const url = String(scope.querySelector('[data-field="attachment_url"]')?.value || '').trim();
        const path = String(scope.querySelector('[data-field="attachment_path"]')?.value || '').trim();

        if (url === '' && path === '') {
            return null;
        }

        return {
            kind: String(scope.querySelector('[data-field="attachment_kind"]')?.value || 'document'),
            url,
            path,
            mime_type: String(scope.querySelector('[data-field="attachment_mime"]')?.value || ''),
            file_name: String(scope.querySelector('[data-field="attachment_file_name"]')?.value || ''),
        };
    }

    function readScheduleDefinition() {
        const days = {};

        Array.from(root.querySelectorAll('[data-schedule-day]')).forEach((row) => {
            const dayKey = String(row.getAttribute('data-schedule-day') || '');

            if (dayKey === '') {
                return;
            }

            days[dayKey] = {
                enabled: Boolean(row.querySelector('[data-field="enabled"]')?.checked),
                from: String(row.querySelector('[data-field="from"]')?.value || '09:00'),
                to: String(row.querySelector('[data-field="to"]')?.value || '18:00'),
            };
        });

        return { days };
    }

    function readCampaignRows() {
        return Array.from(root.querySelectorAll('[data-campaign-triggers] [data-repeater-row]')).map((row) => {
            return {
                id: String(row.querySelector('[data-field="id"]')?.value || '').trim(),
                trigger_text: String(row.querySelector('[data-field="trigger_text"]')?.value || '').trim(),
                target_option_id: String(row.querySelector('[data-field="target_option_id"]')?.value || '').trim(),
            };
        }).filter((item) => item.trigger_text !== '' && item.target_option_id !== '');
    }

    function addFlowRow(type) {
        const selector = type === 'main' ? '[data-main-buttons]' : '[data-list-options]';
        const container = root.querySelector(selector);

        if (!(container instanceof HTMLElement)) {
            return;
        }

        const rows = container.querySelectorAll('[data-repeater-row]');
        const limit = type === 'main' ? 3 : 10;

        if (rows.length >= limit) {
            showNotice('error', type === 'main' ? 'Solo puedes tener 3 botones principales.' : 'La lista desplegable permite hasta 10 opciones.');
            return;
        }

        container.insertAdjacentHTML('beforeend', renderFlowRow({
            id: `${type}-${Date.now()}`,
            label: '',
            response_text: '',
            capture_sequence: [],
            human_on_complete: false,
            create_lead_on_complete: false,
            success_message: '',
            attachment: null,
        }, rows.length, type));
        refreshRepeaterState();
    }

    function addCampaignTriggerRow() {
        const container = root.querySelector('[data-campaign-triggers]');
        const setupForm = root.querySelector('[data-setup-form]');

        if (!(container instanceof HTMLElement) || !(setupForm instanceof HTMLElement)) {
            return;
        }

        container.insertAdjacentHTML('beforeend', renderCampaignRow({
            id: `trigger-${Date.now()}`,
            trigger_text: '',
            target_option_id: '',
        }, allOptionChoicesFromDom(setupForm)));
    }

    function refreshRepeaterState() {
        const mainAddButton = root.querySelector('[data-add-main-button]');
        const listAddButton = root.querySelector('[data-add-list-option]');
        const setupForm = root.querySelector('[data-setup-form]');

        if (mainAddButton instanceof HTMLButtonElement) {
            mainAddButton.disabled = root.querySelectorAll('[data-main-buttons] [data-repeater-row]').length >= 3;
        }

        if (listAddButton instanceof HTMLButtonElement) {
            listAddButton.disabled = root.querySelectorAll('[data-list-options] [data-repeater-row]').length >= 10;
        }

        if (setupForm instanceof HTMLElement) {
            const optionChoices = allOptionChoicesFromDom(setupForm);
            root.querySelectorAll('[data-field="target_option_id"]').forEach((selectNode) => {
                if (!(selectNode instanceof HTMLSelectElement)) {
                    return;
                }

                const currentValue = selectNode.value;
                selectNode.innerHTML = `<option value="">Seleccionar destino</option>${renderSelectOptions(optionChoices, currentValue)}`;
            });
        }
    }

    function activeBot() {
        return (state.bots || []).find((bot) => String(bot.id || '') === String(state.active_bot_id || '')) || null;
    }

    function activeConversation() {
        return (state.conversations || []).find((conversation) => String(conversation.id || '') === String(state.active_conversation_id || '')) || null;
    }

    function selectedTemplate() {
        if (selectedTemplateId === '__new__') {
            return blankTemplate();
        }

        return (state.templates || []).find((template) => String(template.id || '') === String(selectedTemplateId)) || blankTemplate();
    }

    function filteredConversations() {
        const conversations = state.conversations || [];

        if (inboxFilter === 'bot') {
            return conversations.filter((conversation) => String(conversation.inbox_status || 'bot') === 'bot');
        }

        if (inboxFilter === 'human') {
            return conversations.filter((conversation) => String(conversation.inbox_status || 'bot') === 'humano');
        }

        return conversations;
    }

    function loadState(params = {}) {
        const searchParams = new URLSearchParams({
            action: 'state',
            bot_id: String(params.bot_id ?? state.active_bot_id ?? ''),
            conversation_id: String(params.conversation_id ?? state.active_conversation_id ?? ''),
        });

        return fetch(`${apiUrl}&${searchParams.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => response.json().then((payload) => ({ response, payload })))
            .then(({ response, payload }) => {
                if (!response.ok || payload.success !== true) {
                    throw new Error(String(payload.message || 'No fue posible refrescar la herramienta.'));
                }

                const previousHumanCount = Number(state.inbox_counts?.human || 0);
                state = normalizeState(payload.data || {});

                if (!(state.templates || []).some((template) => String(template.id || '') === String(selectedTemplateId))) {
                    selectedTemplateId = state.templates[0]?.id || '__new__';
                }

                renderApp();

                const nextHumanCount = Number(state.inbox_counts?.human || 0);

                if (currentTab === 'inbox' && nextHumanCount > previousHumanCount) {
                    playNotificationTone();
                }
            })
            .catch((error) => {
                showNotice('error', humanizeError(error));
                throw error;
            });
    }

    function postJson(action, payload) {
        return fetch(`${apiUrl}&action=${encodeURIComponent(action)}`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        })
            .then((response) => response.json().then((data) => ({ response, data })))
            .then(({ response, data }) => {
                if (!response.ok || data.success !== true) {
                    throw new Error(String(data.message || 'No fue posible completar la accion.'));
                }

                if (data.data) {
                    state = normalizeState(data.data);

                    if (!(state.templates || []).some((template) => String(template.id || '') === String(selectedTemplateId))) {
                        selectedTemplateId = state.templates[0]?.id || '__new__';
                    }

                    renderApp();
                }

                showNotice('success', String(data.message || 'Cambios guardados.'));
                return data;
            })
            .catch((error) => {
                showNotice('error', humanizeError(error));
                throw error;
            });
    }

    function uploadFile(file, target) {
        const formData = new FormData();
        formData.set('action', 'upload-media');
        formData.set('target', target);
        formData.set('file', file);

        return fetch(`${apiUrl}&action=upload-media`, {
            method: 'POST',
            body: formData,
        })
            .then((response) => response.json().then((payload) => ({ response, payload })))
            .then(({ response, payload }) => {
                if (!response.ok || payload.success !== true) {
                    throw new Error(String(payload.message || 'No fue posible subir el archivo.'));
                }

                return payload.data || {};
            });
    }

    function applyUploadedFile(fileInput, uploaded) {
        const scope = fileInput.closest('[data-repeater-row], [data-template-form]');

        if (!(scope instanceof HTMLElement)) {
            return;
        }

        const preview = scope.querySelector('[data-attachment-preview]');

        const updateHidden = (selector, value) => {
            const field = scope.querySelector(selector);

            if (field instanceof HTMLInputElement) {
                field.value = value;
            }
        };

        updateHidden('[data-field="attachment_kind"], [name="media_kind"]', String(uploaded.kind || 'document'));
        updateHidden('[data-field="attachment_url"], [name="media_url"]', String(uploaded.url || ''));
        updateHidden('[data-field="attachment_path"], [name="media_storage_path"]', String(uploaded.path || ''));
        updateHidden('[data-field="attachment_mime"], [name="media_mime_type"]', String(uploaded.mime_type || ''));
        updateHidden('[data-field="attachment_file_name"], [name="media_file_name"]', String(uploaded.file_name || ''));

        if (preview instanceof HTMLElement) {
            preview.innerHTML = renderAttachmentPreview(uploaded);
        }
    }

    function startPolling() {
        window.clearInterval(pollTimer);

        if (currentTab !== 'inbox' || !activeBot()) {
            return;
        }

        pollTimer = window.setInterval(() => {
            void loadState({
                bot_id: activeBot()?.id || '',
                conversation_id: state.active_conversation_id || '',
            });
        }, 20000);
    }

    function playNotificationTone() {
        if (!hasInteracted || typeof window.AudioContext !== 'function') {
            return;
        }

        try {
            const context = new window.AudioContext();
            const oscillator = context.createOscillator();
            const gain = context.createGain();
            oscillator.type = 'sine';
            oscillator.frequency.value = 880;
            gain.gain.value = 0.06;
            oscillator.connect(gain);
            gain.connect(context.destination);
            oscillator.start();
            oscillator.stop(context.currentTime + 0.18);
        } catch (error) {
            console.error(error);
        }
    }

    function normalizeState(payload) {
        const bots = Array.isArray(payload.bots) ? payload.bots.map(normalizeBot) : [];
        const activeBotId = String(payload.active_bot_id || bots[0]?.id || '');

        return {
            project: payload.project && typeof payload.project === 'object' ? payload.project : {},
            bots,
            active_bot_id: activeBotId,
            templates: Array.isArray(payload.templates) ? payload.templates.map(normalizeTemplate) : [],
            conversations: Array.isArray(payload.conversations) ? payload.conversations.map(normalizeConversation) : [],
            messages: Array.isArray(payload.messages) ? payload.messages.map(normalizeMessage) : [],
            active_conversation_id: String(payload.active_conversation_id || ''),
            inbox_counts: payload.inbox_counts && typeof payload.inbox_counts === 'object'
                ? {
                    bot: Number(payload.inbox_counts.bot || 0),
                    human: Number(payload.inbox_counts.human || 0),
                }
                : { bot: 0, human: 0 },
            webhook: payload.webhook && typeof payload.webhook === 'object' ? payload.webhook : { url: '', public_key: '', verify_token: '' },
        };
    }

    function normalizeBot(bot) {
        return {
            ...bot,
            flow_definition: normalizeFlowDefinition(bot.flow_definition),
            schedule_definition: normalizeScheduleDefinition(bot.schedule_definition),
            routing_definition: normalizeRoutingDefinition(bot.routing_definition),
        };
    }

    function normalizeTemplate(template) {
        return {
            ...template,
            media: {
                kind: template.media_kind || 'none',
                url: template.media_url || '',
                path: template.media_storage_path || '',
                mime_type: template.media_mime_type || '',
                file_name: template.media_file_name || '',
            },
        };
    }

    function normalizeConversation(conversation) {
        return {
            ...conversation,
            bot_context: conversation.bot_context && typeof conversation.bot_context === 'object' ? conversation.bot_context : {},
        };
    }

    function normalizeMessage(message) {
        return {
            ...message,
            payload: message.payload && typeof message.payload === 'object' ? message.payload : {},
        };
    }

    function normalizeFlowDefinition(flowDefinition) {
        const defaults = {
            main_buttons: [],
            list_options: [],
            field_prompts: {
                name: 'Antes de avanzar, me compartes tu nombre?',
                email: 'Perfecto. Cual es tu correo electronico?',
                phone: 'En que numero te podemos contactar?',
                requirement: 'Cuentame brevemente que necesitas.',
            },
        };
        const candidate = flowDefinition && typeof flowDefinition === 'object' ? flowDefinition : {};

        return {
            ...defaults,
            ...candidate,
            main_buttons: Array.isArray(candidate.main_buttons) ? candidate.main_buttons : [],
            list_options: Array.isArray(candidate.list_options) ? candidate.list_options : [],
        };
    }

    function normalizeScheduleDefinition(scheduleDefinition) {
        const defaults = {
            days: {
                monday: { enabled: true, from: '09:00', to: '18:00' },
                tuesday: { enabled: true, from: '09:00', to: '18:00' },
                wednesday: { enabled: true, from: '09:00', to: '18:00' },
                thursday: { enabled: true, from: '09:00', to: '18:00' },
                friday: { enabled: true, from: '09:00', to: '18:00' },
                saturday: { enabled: false, from: '10:00', to: '14:00' },
                sunday: { enabled: false, from: '10:00', to: '14:00' },
            },
        };
        const candidate = scheduleDefinition && typeof scheduleDefinition === 'object' ? scheduleDefinition : {};

        return {
            days: {
                ...defaults.days,
                ...(candidate.days || {}),
            },
        };
    }

    function normalizeRoutingDefinition(routingDefinition) {
        const candidate = routingDefinition && typeof routingDefinition === 'object' ? routingDefinition : {};

        return {
            follow_up_stage_key: String(candidate.follow_up_stage_key || 'contactar-de-nuevo'),
            follow_up_template_id: String(candidate.follow_up_template_id || ''),
            campaign_triggers: Array.isArray(candidate.campaign_triggers) ? candidate.campaign_triggers : [],
        };
    }

    function blankTemplate() {
        return {
            id: '',
            name: '',
            category: 'utility',
            approval_status: 'pendiente',
            header_text: '',
            body_text: '',
            footer_text: '',
            meta_template_id: '',
            media: {
                kind: 'none',
                url: '',
                path: '',
                mime_type: '',
                file_name: '',
            },
        };
    }

    function allOptionChoices(flow) {
        return Array.from(new Map(
            [...(flow.main_buttons || []), ...(flow.list_options || [])]
                .map((item) => [String(item.id || ''), [String(item.id || ''), String(item.label || 'Opcion')]])
        ).values());
    }

    function allOptionChoicesFromDom(scope) {
        return Array.from(scope.querySelectorAll('[data-repeater-row]')).map((row) => {
            const id = String(row.querySelector('[data-field="id"]')?.value || '').trim();
            const label = String(row.querySelector('[data-field="label"]')?.value || '').trim();
            return id && label ? [id, label] : null;
        }).filter(Boolean);
    }

    function renderFlowRow(item, index, type) {
        const capture = Array.isArray(item.capture_sequence) ? item.capture_sequence : [];
        const attachment = item.attachment || null;

        return `
            <article class="wa-flow-card" data-repeater-row data-flow-type="${escapeHtml(type)}">
                <div class="wa-flow-card-head">
                    <strong>${type === 'main' ? 'Boton' : 'Opcion'} ${index + 1}</strong>
                    <button type="button" class="wa-icon-link" data-remove-row aria-label="Eliminar">
                        <span class="material-symbols-rounded">delete</span>
                    </button>
                </div>
                <div class="wa-field-grid wa-field-grid--compact">
                    <input type="hidden" data-field="id" value="${escapeHtml(item.id || '')}">
                    <label class="wa-field">
                        <span>Titulo</span>
                        <input type="text" data-field="label" value="${escapeHtml(item.label || '')}" placeholder="Cotizar">
                    </label>
                    <label class="wa-field wa-field--wide">
                        <span>Respuesta</span>
                        <textarea data-field="response_text" rows="3" placeholder="Con gusto te ayudo...">${escapeHtml(item.response_text || '')}</textarea>
                    </label>
                    <label class="wa-field">
                        <span>Dato 1</span>
                        <select data-field="capture_one">${renderSelectOptions([
                            ['', 'Sin captura'],
                            ['name', 'Nombre'],
                            ['email', 'Correo'],
                            ['phone', 'Telefono'],
                            ['requirement', 'Requerimiento'],
                        ], capture[0] || '')}</select>
                    </label>
                    <label class="wa-field">
                        <span>Dato 2</span>
                        <select data-field="capture_two">${renderSelectOptions([
                            ['', 'Sin captura'],
                            ['name', 'Nombre'],
                            ['email', 'Correo'],
                            ['phone', 'Telefono'],
                            ['requirement', 'Requerimiento'],
                        ], capture[1] || '')}</select>
                    </label>
                    <label class="wa-field">
                        <span>Dato 3</span>
                        <select data-field="capture_three">${renderSelectOptions([
                            ['', 'Sin captura'],
                            ['name', 'Nombre'],
                            ['email', 'Correo'],
                            ['phone', 'Telefono'],
                            ['requirement', 'Requerimiento'],
                        ], capture[2] || '')}</select>
                    </label>
                    <label class="wa-field wa-field--wide">
                        <span>Mensaje al terminar</span>
                        <input type="text" data-field="success_message" value="${escapeHtml(item.success_message || '')}" placeholder="Gracias, un asesor sigue contigo.">
                    </label>
                </div>
                <div class="wa-toggle-row">
                    <label class="wa-toggle">
                        <input type="checkbox" data-field="create_lead_on_complete" ${item.create_lead_on_complete ? 'checked' : ''}>
                        <span>Crear lead al terminar</span>
                    </label>
                    <label class="wa-toggle">
                        <input type="checkbox" data-field="human_on_complete" ${item.human_on_complete ? 'checked' : ''}>
                        <span>Escalar a humano al terminar</span>
                    </label>
                </div>
                <div class="wa-attachment-box" data-attachment-preview>
                    ${renderAttachmentPreview(attachment)}
                </div>
                <div class="wa-upload-inline">
                    <button type="button" class="wa-link-button" data-upload-trigger>Subir archivo</button>
                    <input type="file" class="hidden" data-upload-input data-upload-target="${escapeHtml(type === 'main' ? 'main-option' : 'list-option')}" accept=".jpg,.jpeg,.png,.webp,.gif,.mp3,.ogg,.wav,.pdf">
                    <input type="hidden" data-field="attachment_kind" value="${escapeHtml(attachment?.kind || 'none')}">
                    <input type="hidden" data-field="attachment_url" value="${escapeHtml(attachment?.url || '')}">
                    <input type="hidden" data-field="attachment_path" value="${escapeHtml(attachment?.path || '')}">
                    <input type="hidden" data-field="attachment_mime" value="${escapeHtml(attachment?.mime_type || '')}">
                    <input type="hidden" data-field="attachment_file_name" value="${escapeHtml(attachment?.file_name || '')}">
                </div>
            </article>
        `;
    }

    function renderCampaignRow(trigger, optionChoices) {
        return `
            <article class="wa-flow-card" data-repeater-row>
                <div class="wa-flow-card-head">
                    <strong>Trigger</strong>
                    <button type="button" class="wa-icon-link" data-remove-row aria-label="Eliminar">
                        <span class="material-symbols-rounded">delete</span>
                    </button>
                </div>
                <div class="wa-field-grid wa-field-grid--compact">
                    <input type="hidden" data-field="id" value="${escapeHtml(trigger.id || '')}">
                    <label class="wa-field wa-field--wide">
                        <span>Frase exacta</span>
                        <input type="text" data-field="trigger_text" value="${escapeHtml(trigger.trigger_text || '')}" placeholder="Quiero la Promo de Verano">
                    </label>
                    <label class="wa-field">
                        <span>Destino</span>
                        <select data-field="target_option_id">
                            <option value="">Seleccionar destino</option>
                            ${renderSelectOptions(optionChoices, trigger.target_option_id || '')}
                        </select>
                    </label>
                </div>
            </article>
        `;
    }

    function renderScheduleRow(dayKey, config) {
        return `
            <article class="wa-schedule-row" data-schedule-day="${escapeHtml(dayKey)}">
                <label class="wa-toggle">
                    <input type="checkbox" data-field="enabled" ${config.enabled ? 'checked' : ''}>
                    <span>${escapeHtml(dayLabel(dayKey))}</span>
                </label>
                <div class="wa-schedule-times">
                    <input type="time" data-field="from" value="${escapeHtml(config.from || '09:00')}">
                    <span>a</span>
                    <input type="time" data-field="to" value="${escapeHtml(config.to || '18:00')}">
                </div>
            </article>
        `;
    }

    function renderTemplateOptions(templates, selectedId) {
        return templates.map((template) => `
            <option value="${escapeHtml(template.id || '')}"${String(template.id || '') === String(selectedId || '') ? ' selected' : ''}>
                ${escapeHtml(template.name || 'Plantilla')}
            </option>
        `).join('');
    }

    function renderSelectOptions(options, selectedValue) {
        return options.map(([value, label]) => `
            <option value="${escapeHtml(String(value))}"${String(value) === String(selectedValue) ? ' selected' : ''}>
                ${escapeHtml(String(label))}
            </option>
        `).join('');
    }

    function renderAttachmentPreview(attachment) {
        if (!attachment || (!attachment.url && !attachment.path)) {
            return '<div class="wa-attachment-empty">Sin archivo adjunto</div>';
        }

        return `
            <a class="wa-attachment-preview" href="${escapeHtml(attachment.url || '#')}" target="_blank" rel="noreferrer noopener">
                <span class="material-symbols-rounded">${escapeHtml(iconForAttachmentKind(attachment.kind || 'document'))}</span>
                <span>${escapeHtml(attachment.file_name || attachment.url || 'Archivo')}</span>
            </a>
        `;
    }

    function renderDeliveryIcon(status) {
        const icon = status === 'read' ? 'done_all' : (status === 'delivered' ? 'done_all' : (status === 'failed' ? 'error' : 'done'));
        return `<span class="wa-message-status material-symbols-rounded">${escapeHtml(icon)}</span>`;
    }

    function labelForMessageAuthor(author) {
        if (author === 'bot') {
            return 'Bot';
        }

        if (author === 'human') {
            return 'Humano';
        }

        if (author === 'system') {
            return 'Sistema';
        }

        return 'Cliente';
    }

    function labelForBotStatus(status) {
        return {
            draft: 'Borrador',
            active: 'Activo',
            paused: 'Pausado',
        }[status] || 'Borrador';
    }

    function labelForTemplateStatus(status) {
        return {
            pendiente: 'Pendiente',
            aprobado: 'Aprobado',
            rechazado: 'Rechazado',
        }[status] || 'Pendiente';
    }

    function iconForAttachmentKind(kind) {
        return {
            image: 'image',
            audio: 'mic',
            document: 'description',
            pdf: 'description',
        }[kind] || 'attach_file';
    }

    function dayLabel(dayKey) {
        return {
            monday: 'Lunes',
            tuesday: 'Martes',
            wednesday: 'Miercoles',
            thursday: 'Jueves',
            friday: 'Viernes',
            saturday: 'Sabado',
            sunday: 'Domingo',
        }[dayKey] || dayKey;
    }

    function extractVariables(body) {
        const matches = String(body || '').match(/\{[^}]+\}/g);

        return matches ? Array.from(new Set(matches)) : [];
    }

    function insertTemplateVariable(variable) {
        const textarea = root.querySelector('[name="body_text"]');

        if (!(textarea instanceof HTMLTextAreaElement) || variable === '') {
            return;
        }

        const start = textarea.selectionStart || 0;
        const end = textarea.selectionEnd || 0;
        textarea.setRangeText(variable, start, end, 'end');
        textarea.focus();
    }

    function showNotice(type, message) {
        if (!(notice instanceof HTMLElement)) {
            return;
        }

        notice.textContent = String(message || '');
        notice.className = `wa-bot-notice wa-bot-notice--${type}`;
        notice.classList.remove('hidden');

        window.clearTimeout(showNotice.timeoutId);
        showNotice.timeoutId = window.setTimeout(() => {
            notice.classList.add('hidden');
        }, 5000);
    }

    function humanizeError(error) {
        return error instanceof Error ? error.message : 'Ocurrio un error inesperado.';
    }

    async function copyText(value) {
        if (String(value || '').trim() === '') {
            return;
        }

        await navigator.clipboard.writeText(String(value));
        showNotice('success', 'Copiado al portapapeles.');
    }

    function formatShortDate(value) {
        if (!value) {
            return '';
        }

        const date = new Date(String(value));

        if (Number.isNaN(date.getTime())) {
            return '';
        }

        return new Intl.DateTimeFormat('es-MX', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(date);
    }

    function hasOpenSession(conversation) {
        const expiresAt = String(conversation.session_expires_at || '').trim();

        if (expiresAt === '') {
            return false;
        }

        const date = new Date(expiresAt);

        return !Number.isNaN(date.getTime()) && date.getTime() > Date.now();
    }

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function parseJson(value) {
        try {
            return JSON.parse(value);
        } catch (error) {
            console.error(error);
            return {};
        }
    }

    function renderEmptyState(message) {
        return `
            <div class="wa-empty-state">
                <span class="material-symbols-rounded">inbox</span>
                <p>${escapeHtml(message)}</p>
            </div>
        `;
    }
})();
