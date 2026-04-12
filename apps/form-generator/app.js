(() => {
    const form = document.querySelector('[data-form-builder]');

    if (!form) {
        return;
    }

    const fieldsContainer = form.querySelector('[data-form-fields]');
    const addFieldButton = form.querySelector('[data-form-add-field]');
    const duplicateActiveButton = form.querySelector('[data-form-duplicate-active]');
    const settingsButton = form.querySelector('[data-form-focus-settings]');
    const sideTools = form.querySelector('[data-form-side-tools]');
    const titleInput = form.querySelector('[data-form-title-input]');
    const docTitle = form.querySelector('[data-form-doc-title]');
    const responsesShell = form.querySelector('[data-form-responses-shell]');
    const summaryStateNode = document.querySelector('[data-form-responses-state]');
    const fieldTypesStateNode = document.querySelector('[data-form-field-types]');
    const apiUrl = String(form.dataset.apiUrl || '').trim();
    const formId = String(form.dataset.formId || '').trim();
    const canLoadStats = String(form.dataset.canLoadStats || '') === 'true' && apiUrl !== '' && formId !== '';

    if (!fieldsContainer || !addFieldButton) {
        return;
    }

    const fieldTypes = (() => {
        if (!(fieldTypesStateNode instanceof HTMLScriptElement)) {
            return [];
        }

        try {
            const parsed = JSON.parse(fieldTypesStateNode.textContent || '[]');
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    })();
    const choiceTypes = new Set(['single_choice', 'multiple_choice', 'dropdown']);
    const chartPalette = ['#3367d6', '#d93025', '#f29900', '#1e8e3e', '#9334e6', '#0099c6'];
    const pollIntervalMs = 5000;
    let statsPollTimer = 0;
    let statsRequestController = null;

    const typeMeta = (type) => fieldTypes.find((fieldType) => fieldType.value === type) || { value: 'single_choice', label: 'Varias opciones', icon: 'radio_button_checked' };
    const closest = (target, selector) => {
        if (target instanceof Element) {
            return target.closest(selector);
        }

        return target?.parentElement?.closest(selector) || null;
    };
    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const formatDateTime = (value) => {
        const normalized = String(value || '').trim();

        if (normalized === '') {
            return 'Sin fecha';
        }

        const parsed = new Date(normalized);

        if (Number.isNaN(parsed.getTime())) {
            return normalized;
        }

        return new Intl.DateTimeFormat('es-MX', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(parsed);
    };

    const createId = (prefix) => {
        if (window.crypto?.randomUUID) {
            return `${prefix}_${window.crypto.randomUUID().replaceAll('-', '').slice(0, 12)}`;
        }

        return `${prefix}_${Date.now().toString(36)}${Math.random().toString(36).slice(2, 8)}`;
    };

    const createTypePicker = (selectedType) => {
        const selectedMeta = typeMeta(selectedType);

        return `
            <div class="form-builder-field form-builder-type-field">
                <span class="sr-only">Tipo</span>
                <details class="form-builder-type-picker" data-form-type-picker>
                    <summary class="form-builder-type-summary">
                        <span class="material-symbols-rounded" data-form-type-icon>${escapeHtml(selectedMeta.icon)}</span>
                        <span data-form-type-label>${escapeHtml(selectedMeta.label)}</span>
                        <span class="material-symbols-rounded form-builder-type-chevron">expand_more</span>
                    </summary>
                    <div class="form-builder-type-menu">
                        ${fieldTypes.map((fieldType) => `
                            <button
                                type="button"
                                class="form-builder-type-option${fieldType.value === selectedMeta.value ? ' is-active' : ''}"
                                data-form-type-option
                                data-value="${escapeHtml(fieldType.value)}"
                                data-label="${escapeHtml(fieldType.label)}"
                                data-icon="${escapeHtml(fieldType.icon)}"
                            >
                                <span class="material-symbols-rounded">${escapeHtml(fieldType.icon)}</span>
                                <span>${escapeHtml(fieldType.label)}</span>
                            </button>
                        `).join('')}
                    </div>
                </details>
                <input type="hidden" name="field_type[0]" value="${escapeHtml(selectedMeta.value)}" data-form-field-input="type">
            </div>
        `;
    };

    const optionIcon = (type) => {
        if (type === 'multiple_choice') {
            return 'check_box_outline_blank';
        }

        if (type === 'dropdown') {
            return 'arrow_drop_down';
        }

        return 'radio_button_unchecked';
    };

    const createOptionRow = (type, value = '', index = 0) => `
        <div class="form-builder-option-row" data-form-option-row>
            <span class="material-symbols-rounded form-builder-option-decorator" data-form-option-icon>${escapeHtml(optionIcon(type))}</span>
            <input type="text" value="${escapeHtml(value)}" placeholder="Opcion ${index + 1}" data-form-option-input>
            <button type="button" class="form-builder-option-remove" data-form-option-remove aria-label="Eliminar opcion">
                <span class="material-symbols-rounded">close</span>
            </button>
        </div>
    `;

    const createFieldCard = (type) => {
        const safeType = fieldTypes.some((fieldType) => fieldType.value === type) ? type : 'single_choice';
        const fieldId = createId('field');
        const defaultOptions = choiceTypes.has(safeType) ? ['Opcion 1', 'Opcion 2'] : [];

        const wrapper = document.createElement('div');
        wrapper.innerHTML = `
            <article class="form-builder-field-card" data-form-field-card>
                <div class="form-builder-field-card-head">
                    <span class="form-builder-drag-handle material-symbols-rounded" aria-hidden="true">drag_indicator</span>
                    <strong data-form-field-title>Pregunta</strong>
                    <div class="form-builder-field-tools">
                        <button type="submit" name="builder_action" value="up:0" class="form-builder-icon-button" aria-label="Subir campo" data-form-field-move="up">
                            <span class="material-symbols-rounded">arrow_upward</span>
                        </button>
                        <button type="submit" name="builder_action" value="down:0" class="form-builder-icon-button" aria-label="Bajar campo" data-form-field-move="down">
                            <span class="material-symbols-rounded">arrow_downward</span>
                        </button>
                        <button type="button" class="form-builder-icon-button" aria-label="Duplicar campo" data-form-field-duplicate>
                            <span class="material-symbols-rounded">content_copy</span>
                        </button>
                        <button type="submit" name="builder_action" value="delete:0" class="form-builder-icon-button form-builder-icon-button--danger" aria-label="Eliminar campo" data-form-field-delete>
                            <span class="material-symbols-rounded">delete</span>
                        </button>
                    </div>
                </div>

                <input type="hidden" name="field_id[0]" value="${escapeHtml(fieldId)}" data-form-field-input="id">

                <div class="form-builder-editor-grid">
                    ${createTypePicker(safeType)}

                    <label class="form-builder-field form-builder-question-label">
                        <span class="sr-only">Pregunta</span>
                        <input type="text" name="field_label[0]" value="" placeholder="Pregunta" data-form-field-input="label">
                    </label>

                    <label class="form-builder-field form-builder-field--wide form-builder-options-field" data-form-options-wrap>
                        <span class="sr-only">Opciones</span>
                        <div class="form-builder-option-list" data-form-option-items>
                            ${defaultOptions.map((option, index) => createOptionRow(safeType, option, index)).join('')}
                        </div>
                        <button type="button" class="form-builder-option-add" data-form-option-add>
                            <span class="material-symbols-rounded">add</span>
                            <span>Anadir opcion</span>
                        </button>
                        <textarea name="field_options[0]" rows="4" class="form-builder-options-storage" data-form-field-input="options">${escapeHtml(defaultOptions.join('\n'))}</textarea>
                    </label>
                </div>

                <div class="form-builder-question-preview" data-form-question-preview>
                    <div class="form-builder-preview-shell" data-form-preview-shell>
                        <div class="form-builder-preview-title" data-form-preview-title>Pregunta</div>
                        <div class="form-builder-preview-body" data-form-preview-body>
                            ${createPreviewBody(safeType, defaultOptions)}
                        </div>
                    </div>
                </div>

                <div class="form-builder-card-footer">
                    <label class="form-builder-check form-builder-check--switch">
                        <span>Obligatorio</span>
                        <input type="checkbox" name="field_required[0]" value="1" data-form-field-input="required">
                    </label>
                </div>
            </article>
        `.trim();

        return wrapper.firstElementChild;
    };

    const getCards = () => Array.from(fieldsContainer.querySelectorAll('[data-form-field-card]'));
    const getLastCard = () => {
        const cards = getCards();

        return cards[cards.length - 1] || null;
    };

    const setActiveCard = (card) => {
        getCards().forEach((currentCard) => {
            currentCard.classList.toggle('is-active', currentCard === card);
        });
    };

    const updateDocTitle = () => {
        if (!titleInput || !docTitle) {
            return;
        }

        docTitle.textContent = titleInput.value.trim() || 'Formulario sin titulo';
    };

    const updateChoiceVisibility = (card) => {
        const type = card.querySelector('[data-form-field-input="type"]')?.value || 'short_text';
        const optionsWrap = card.querySelector('[data-form-options-wrap]');
        const optionsInput = card.querySelector('[data-form-field-input="options"]');
        const optionItems = card.querySelector('[data-form-option-items]');

        optionsWrap?.classList.toggle('is-hidden', !choiceTypes.has(type));

        if (choiceTypes.has(type) && optionsInput && optionsInput.value.trim() === '') {
            optionsInput.value = 'Opcion 1\nOpcion 2';
        }

        optionItems?.querySelectorAll('[data-form-option-icon]').forEach((iconNode) => {
            iconNode.textContent = optionIcon(type);
        });

        if (choiceTypes.has(type) && optionItems && optionItems.children.length === 0) {
            optionItems.insertAdjacentHTML('beforeend', createOptionRow(type, 'Opcion 1', 0));
            optionItems.insertAdjacentHTML('beforeend', createOptionRow(type, 'Opcion 2', 1));
            syncOptionStorage(card);
        }
    };

    const syncTypePicker = (card) => {
        const typeInput = card.querySelector('[data-form-field-input="type"]');
        const summaryLabel = card.querySelector('[data-form-type-label]');
        const summaryIcon = card.querySelector('[data-form-type-icon]');
        const currentType = String(typeInput?.value || 'single_choice');
        const currentMeta = typeMeta(currentType);

        if (summaryLabel) {
            summaryLabel.textContent = currentMeta.label;
        }

        if (summaryIcon) {
            summaryIcon.textContent = currentMeta.icon;
        }

        card.querySelectorAll('[data-form-type-option]').forEach((optionButton) => {
            optionButton.classList.toggle('is-active', optionButton.dataset.value === currentMeta.value);
        });
    };

    const syncOptionStorage = (card) => {
        const storage = card.querySelector('[data-form-field-input="options"]');
        const values = Array.from(card.querySelectorAll('[data-form-option-input]'))
            .map((input) => input.value.trim())
            .filter((value) => value !== '');

        if (storage) {
            storage.value = values.join('\n');
        }

        card.querySelectorAll('[data-form-option-row]').forEach((row, index) => {
            const input = row.querySelector('[data-form-option-input]');

            if (input) {
                input.placeholder = `Opcion ${index + 1}`;
            }
        });
    };

    const createPreviewBody = (type, options) => {
        const normalizedOptions = Array.isArray(options) && options.length > 0 ? options : ['Opcion 1', 'Opcion 2'];

        if (type === 'single_choice' || type === 'multiple_choice') {
            return `
                <div class="form-builder-preview-options">
                    ${normalizedOptions.map((option) => `
                        <div class="form-builder-preview-option">
                            <span class="material-symbols-rounded">${escapeHtml(optionIcon(type))}</span>
                            <span>${escapeHtml(option)}</span>
                        </div>
                    `).join('')}
                </div>
            `;
        }

        if (type === 'dropdown') {
            return `
                <div class="form-builder-preview-input form-builder-preview-input--dropdown">
                    <span>Selecciona una opcion</span>
                    <span class="material-symbols-rounded">expand_more</span>
                </div>
            `;
        }

        if (type === 'long_text') {
            return '<div class="form-builder-preview-input form-builder-preview-input--textarea"></div>';
        }

        return '<div class="form-builder-preview-input"></div>';
    };

    const syncPreview = (card) => {
        const titleInput = card.querySelector('[data-form-field-input="label"]');
        const typeInput = card.querySelector('[data-form-field-input="type"]');
        const previewTitle = card.querySelector('[data-form-preview-title]');
        const previewBody = card.querySelector('[data-form-preview-body]');
        const options = Array.from(card.querySelectorAll('[data-form-option-input]'))
            .map((input) => input.value.trim())
            .filter((value) => value !== '');
        const currentType = String(typeInput?.value || 'single_choice');

        if (previewTitle) {
            previewTitle.textContent = String(titleInput?.value || '').trim() || 'Pregunta';
        }

        if (previewBody) {
            previewBody.innerHTML = createPreviewBody(currentType, options);
        }
    };

    const updateIndexes = () => {
        getCards().forEach((card, index) => {
            const title = card.querySelector('[data-form-field-title]');
            const controls = card.querySelectorAll('[data-form-field-input]');
            const upButton = card.querySelector('[data-form-field-move="up"]');
            const downButton = card.querySelector('[data-form-field-move="down"]');
            const deleteButton = card.querySelector('[data-form-field-delete]');

            if (title) {
                title.textContent = `Pregunta ${index + 1}`;
            }

            controls.forEach((control) => {
                const key = control.dataset.formFieldInput;

                if (key) {
                    control.name = `field_${key}[${index}]`;
                }
            });

            if (upButton) {
                upButton.value = `up:${index}`;
            }

            if (downButton) {
                downButton.value = `down:${index}`;
            }

            if (deleteButton) {
                deleteButton.value = `delete:${index}`;
            }

            syncTypePicker(card);
            syncOptionStorage(card);
            syncPreview(card);
            updateChoiceVisibility(card);
        });
    };

    const removeEmptyState = () => {
        fieldsContainer.querySelector('[data-form-empty]')?.remove();
    };

    const addField = (type) => {
        removeEmptyState();

        const card = createFieldCard(type);
        const activeCard = fieldsContainer.querySelector('[data-form-field-card].is-active');

        if (activeCard?.parentElement === fieldsContainer) {
            activeCard.insertAdjacentElement('afterend', card);
        } else {
            fieldsContainer.append(card);
        }

        updateIndexes();
        setActiveCard(card);
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        card.querySelector('[data-form-field-input="label"]')?.focus();
    };

    const duplicateCard = (card) => {
        const clone = card.cloneNode(true);
        const idInput = clone.querySelector('[data-form-field-input="id"]');

        if (idInput) {
            idInput.value = createId('field');
        }

        card.after(clone);
        updateIndexes();
        setActiveCard(clone);
        clone.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    const describePieSlice = (centerX, centerY, radius, startAngle, endAngle) => {
        const startX = centerX + (radius * Math.cos(startAngle));
        const startY = centerY + (radius * Math.sin(startAngle));
        const endX = centerX + (radius * Math.cos(endAngle));
        const endY = centerY + (radius * Math.sin(endAngle));
        const largeArcFlag = endAngle - startAngle > Math.PI ? 1 : 0;

        return `M ${centerX.toFixed(3)} ${centerY.toFixed(3)} L ${startX.toFixed(3)} ${startY.toFixed(3)} A ${radius.toFixed(3)} ${radius.toFixed(3)} 0 ${largeArcFlag} 1 ${endX.toFixed(3)} ${endY.toFixed(3)} Z`;
    };

    const buildChoiceChartSegments = (options) => {
        let angle = -Math.PI / 2;

        return (Array.isArray(options) ? options : []).map((option, index) => {
            const percent = Math.max(0, Math.min(100, Number(option?.percent || 0)));
            const sweep = (Math.PI * 2 * percent) / 100;
            const startAngle = angle;
            const endAngle = angle + sweep;
            const segment = {
                color: chartPalette[index % chartPalette.length],
                percent,
                label: String(option?.label || ''),
                count: Math.max(0, Number(option?.count || 0)),
                startAngle,
                endAngle,
                midAngle: startAngle + (sweep / 2),
            };

            angle = endAngle;
            return segment;
        });
    };

    const renderChoicePieSvg = (options) => {
        const segments = buildChoiceChartSegments(options);
        const visibleSegments = segments.filter((segment) => segment.percent > 0.01);

        return `
            <svg class="form-builder-pie-svg" viewBox="0 0 280 280" role="img" aria-hidden="true">
                <circle cx="140" cy="140" r="112" fill="#eef1f5"></circle>
                ${visibleSegments.map((segment) => `
                    <path d="${escapeHtml(describePieSlice(140, 140, 112, segment.startAngle, segment.endAngle))}" fill="${escapeHtml(segment.color)}" stroke="#ffffff" stroke-width="2"></path>
                `).join('')}
                ${visibleSegments.map((segment) => {
                    if (segment.percent < 6) {
                        return '';
                    }

                    const labelRadius = 78.4;
                    const x = 140 + (labelRadius * Math.cos(segment.midAngle));
                    const y = 140 + (labelRadius * Math.sin(segment.midAngle));

                    return `
                        <text x="${escapeHtml(x.toFixed(2))}" y="${escapeHtml(y.toFixed(2))}" text-anchor="middle" dominant-baseline="middle" class="form-builder-pie-svg-label">${escapeHtml(String(Math.round(segment.percent)))}%</text>
                    `;
                }).join('')}
                ${visibleSegments.length === 0 ? '<text x="140" y="146" text-anchor="middle" class="form-builder-pie-svg-empty">Sin datos</text>' : ''}
            </svg>
        `;
    };

    const setCopyButtonState = (button, label) => {
        const labelNode = button?.querySelector('span:last-child');

        if (labelNode) {
            labelNode.textContent = label;
        }
    };

    const wrapCanvasText = (context, text, maxWidth) => {
        const normalized = String(text || '').trim();

        if (normalized === '') {
            return ['Pregunta'];
        }

        const words = normalized.split(/\s+/);
        const lines = [];
        let currentLine = '';

        words.forEach((word) => {
            const candidate = currentLine === '' ? word : `${currentLine} ${word}`;

            if (context.measureText(candidate).width <= maxWidth || currentLine === '') {
                currentLine = candidate;
                return;
            }

            lines.push(currentLine);
            currentLine = word;
        });

        if (currentLine !== '') {
            lines.push(currentLine);
        }

        return lines;
    };

    const drawRoundedRect = (context, x, y, width, height, radius) => {
        context.beginPath();
        context.moveTo(x + radius, y);
        context.arcTo(x + width, y, x + width, y + height, radius);
        context.arcTo(x + width, y + height, x, y + height, radius);
        context.arcTo(x, y + height, x, y, radius);
        context.arcTo(x, y, x + width, y, radius);
        context.closePath();
    };

    const canvasToBlob = (canvas) => new Promise((resolve, reject) => {
        canvas.toBlob((blob) => {
            if (blob) {
                resolve(blob);
                return;
            }

            reject(new Error('No fue posible generar la imagen.'));
        }, 'image/png');
    });

    const buildChoiceChartCanvas = (question) => {
        const title = String(question?.label || 'Pregunta');
        const answerCount = Math.max(0, Number(question?.answer_count || 0));
        const options = Array.isArray(question?.options) ? question.options : [];
        const segments = buildChoiceChartSegments(options);
        const width = 1440;
        const padding = 44;
        const chartCenterX = 470;
        const chartRadius = 170;
        const legendX = 900;
        const legendRowHeight = 74;
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');

        if (!context) {
            throw new Error('No fue posible preparar el grafico.');
        }

        context.font = '600 30px Arial';
        const titleLines = wrapCanvasText(context, title, width - (padding * 2));
        const titleBlockHeight = titleLines.length * 38;
        const subtitleY = 68 + titleBlockHeight + 26;
        const visualTop = subtitleY + 44;
        const chartCenterY = visualTop + 158;
        const legendTop = visualTop + 34;
        const legendHeight = Math.max(280, options.length * legendRowHeight);
        const height = Math.max(680, visualTop + Math.max(chartRadius * 2, legendHeight) + 70);

        canvas.width = width;
        canvas.height = height;

        context.fillStyle = '#f5f7fb';
        context.fillRect(0, 0, width, height);
        context.shadowColor = 'rgba(15, 23, 42, 0.08)';
        context.shadowBlur = 24;
        context.shadowOffsetY = 10;
        drawRoundedRect(context, 12, 12, width - 24, height - 24, 26);
        context.fillStyle = '#ffffff';
        context.fill();
        context.shadowColor = 'transparent';

        context.strokeStyle = '#d9dee8';
        context.lineWidth = 2;
        drawRoundedRect(context, 12, 12, width - 24, height - 24, 26);
        context.stroke();

        context.fillStyle = '#202124';
        context.font = '600 30px Arial';
        titleLines.forEach((line, index) => {
            context.fillText(line, padding, 68 + (index * 38));
        });

        context.fillStyle = '#202124';
        context.font = '400 24px Arial';
        context.fillText(`${answerCount} respuestas`, padding, subtitleY);

        context.fillStyle = '#eef1f5';
        context.beginPath();
        context.arc(chartCenterX, chartCenterY, chartRadius, 0, Math.PI * 2);
        context.fill();

        segments.forEach((segment) => {
            if (segment.percent <= 0.01) {
                return;
            }

            context.beginPath();
            context.moveTo(chartCenterX, chartCenterY);
            context.arc(chartCenterX, chartCenterY, chartRadius, segment.startAngle, segment.endAngle);
            context.closePath();
            context.fillStyle = segment.color;
            context.fill();
            context.strokeStyle = '#ffffff';
            context.lineWidth = 3;
            context.stroke();
        });

        segments.forEach((segment) => {
            if (segment.percent < 6) {
                return;
            }

            const labelRadius = chartRadius * 0.72;
            const x = chartCenterX + (labelRadius * Math.cos(segment.midAngle));
            const y = chartCenterY + (labelRadius * Math.sin(segment.midAngle));
            context.fillStyle = '#ffffff';
            context.font = '700 22px Arial';
            context.textAlign = 'center';
            context.textBaseline = 'middle';
            context.fillText(`${Math.round(segment.percent)}%`, x, y);
        });

        context.textAlign = 'left';
        context.textBaseline = 'alphabetic';

        options.forEach((option, index) => {
            const y = legendTop + (index * legendRowHeight);
            context.fillStyle = chartPalette[index % chartPalette.length];
            context.beginPath();
            context.arc(legendX, y, 12, 0, Math.PI * 2);
            context.fill();

            context.fillStyle = '#202124';
            context.font = '500 21px Arial';
            context.fillText(String(option?.label || ''), legendX + 28, y + 8);

            context.fillStyle = '#5f6368';
            context.font = '400 18px Arial';
            context.fillText(`${Math.max(0, Number(option?.count || 0))} respuestas • ${Math.round(Math.max(0, Number(option?.percent || 0)))}%`, legendX + 28, y + 36);
        });

        return canvas;
    };

    const copyChoiceChart = async (button) => {
        const originalLabel = button?.querySelector('span:last-child')?.textContent || 'Copiar grafico';
        let resetTimer = 0;

        button.disabled = true;
        setCopyButtonState(button, 'Copiando...');

        try {
            const payload = JSON.parse(String(button.dataset.chartPayload || '{}'));
            const canvas = buildChoiceChartCanvas(payload);
            const blob = await canvasToBlob(canvas);

            if (navigator.clipboard?.write && window.ClipboardItem) {
                await navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]);
                setCopyButtonState(button, 'Imagen copiada');
            } else {
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = 'grafico-formulario.png';
                link.click();
                window.setTimeout(() => URL.revokeObjectURL(url), 1000);
                setCopyButtonState(button, 'Imagen descargada');
            }
        } catch (error) {
            setCopyButtonState(button, 'No se pudo copiar');
        } finally {
            resetTimer = window.setTimeout(() => {
                setCopyButtonState(button, originalLabel);
                button.disabled = false;
                window.clearTimeout(resetTimer);
            }, 1800);
        }
    };

    const renderChoiceQuestionCard = (question) => {
        const options = Array.isArray(question?.options) ? question.options : [];
        const payload = {
            label: String(question?.label || 'Pregunta'),
            answer_count: Math.max(0, Number(question?.answer_count || 0)),
            options: options.map((option) => ({
                label: String(option?.label || ''),
                count: Math.max(0, Number(option?.count || 0)),
                percent: Math.max(0, Number(option?.percent || 0)),
            })),
        };

        return `
            <article class="form-builder-chart-card form-builder-chart-card--choice">
                <div class="form-builder-chart-head">
                    <div class="form-builder-chart-head-main">
                        <h3>${escapeHtml(question?.label || 'Pregunta')}</h3>
                        <p>${escapeHtml(String(question?.answer_count || 0))} respuestas</p>
                    </div>
                    <button type="button" class="form-builder-chart-copy" data-form-copy-chart data-chart-payload="${escapeHtml(JSON.stringify(payload))}">
                        <span class="material-symbols-rounded">content_copy</span>
                        <span>Copiar grafico</span>
                    </button>
                </div>

                <div class="form-builder-chart-body form-builder-chart-body--choice">
                    <div class="form-builder-chart-visual">
                        ${renderChoicePieSvg(options)}
                    </div>

                    <div class="form-builder-chart-legend">
                        ${options.map((option, index) => `
                            <div class="form-builder-chart-legend-item">
                                <span class="form-builder-chart-swatch" style="background: ${escapeHtml(chartPalette[index % chartPalette.length])};"></span>
                                <div>
                                    <strong>${escapeHtml(option?.label || '')}</strong>
                                    <small>${escapeHtml(String(option?.count || 0))} respuestas • ${escapeHtml(String(Math.round(Number(option?.percent || 0))))}%</small>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            </article>
        `;
    };

    const renderOpenQuestionCard = (question) => {
        const responses = Array.isArray(question?.responses) ? question.responses : [];

        return `
            <article class="form-builder-open-card">
                <div class="form-builder-chart-head">
                    <h3>${escapeHtml(question?.label || 'Pregunta abierta')}</h3>
                    <p>${escapeHtml(String(responses.length))} respuestas completas</p>
                </div>

                ${responses.length === 0
                    ? `
                        <div class="form-builder-open-empty">
                            <span class="material-symbols-rounded">notes</span>
                            <p>Aun no hay respuestas completas para esta pregunta.</p>
                        </div>
                    `
                    : `
                        <div class="form-builder-open-table-wrap">
                            <table class="form-builder-open-table">
                                <thead>
                                    <tr>
                                        <th>Respuesta</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${responses.map((response) => `
                                        <tr>
                                            <td>${escapeHtml(response?.value || '').replaceAll('\n', '<br>')}</td>
                                            <td>${escapeHtml(formatDateTime(response?.submitted_at || ''))}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    `}
            </article>
        `;
    };

    const renderResponses = (summary) => {
        if (!(responsesShell instanceof HTMLElement)) {
            return;
        }

        const completedCount = Number(summary?.completed_count || 0);
        const visitsCount = Number(summary?.visits_count || 0);
        const startedCount = Number(summary?.started_count || 0);
        const finishedCount = Number(summary?.completed_sessions_count || completedCount);
        const funnel = summary?.funnel && typeof summary.funnel === 'object' ? summary.funnel : {};
        const choiceQuestions = Array.isArray(summary?.choice_questions) ? summary.choice_questions : [];
        const openQuestions = Array.isArray(summary?.open_questions) ? summary.open_questions : [];

        const renderLiteralFunnel = (funnelData) => {
            const arrived = Math.max(0, Number(funnelData?.arrived || visitsCount));
            const started = Math.min(arrived, Math.max(0, Number(funnelData?.started || startedCount)));
            const completed = Math.min(started, Math.max(0, Number(funnelData?.completed || finishedCount)));
            const base = Math.max(arrived, 1);
            const stages = [
                { label: 'Llegaron', count: arrived, width: 100, percent: 100, modifier: 1 },
                { label: 'Empezaron', count: started, width: Math.max(52, Math.round(78 * Math.max(0, started / base))), percent: Math.round((started / base) * 100), modifier: 2 },
                { label: 'Terminaron', count: completed, width: Math.max(34, Math.round(60 * Math.max(0, completed / base))), percent: Math.round((completed / base) * 100), modifier: 3 },
            ];

            return `
                <div class="form-builder-funnel" aria-label="Embudo del formulario">
                    ${stages.map((stage) => `
                        <article class="form-builder-funnel-stage form-builder-funnel-stage--${stage.modifier}" style="--funnel-stage-width: ${stage.width}%;">
                            <span class="form-builder-funnel-stage-label">${escapeHtml(stage.label)}</span>
                            <strong>${escapeHtml(String(stage.count))}</strong>
                            <small>${escapeHtml(String(stage.percent))}% del total</small>
                        </article>
                    `).join('')}
                </div>
            `;
        };

        responsesShell.innerHTML = !canLoadStats
            ? `
                <section class="form-google-empty-panel">
                    <span class="material-symbols-rounded">query_stats</span>
                    <h2>Guarda el formulario para ver resultados</h2>
                    <p>En cuanto exista el formulario, aqui veras respuestas completas, visitas y abandono en tiempo real.</p>
                </section>
            `
            : `
                <div class="form-builder-responses">
                    <section class="form-builder-responses-kpis">
                        <article class="form-builder-response-card form-builder-response-card--primary">
                            <span class="form-builder-response-label">Respuestas completas</span>
                            <strong>${escapeHtml(String(completedCount))}</strong>
                            <small>Solo contamos formularios terminados.</small>
                        </article>

                        <article class="form-builder-response-card">
                            <span class="form-builder-response-label">Visitas</span>
                            <strong>${escapeHtml(String(visitsCount))}</strong>
                            <small>Se actualiza automaticamente con cada nueva entrada.</small>
                        </article>
                    </section>

                    ${choiceQuestions.length > 0
                        ? `
                            <section class="form-builder-analytics-section">
                                <div class="form-builder-analytics-head">
                                    <h2>Preguntas cerradas</h2>
                                    <p>Cada grafica usa solo respuestas completas.</p>
                                </div>
                                <div class="form-builder-charts-grid">
                                    ${choiceQuestions.map(renderChoiceQuestionCard).join('')}
                                </div>
                            </section>
                        `
                        : ''}

                    ${openQuestions.length > 0
                        ? `
                            <section class="form-builder-analytics-section">
                                <div class="form-builder-analytics-head">
                                    <h2>Preguntas abiertas</h2>
                                    <p>Tabla viva con respuestas completas recibidas.</p>
                                </div>
                                <div class="form-builder-open-question-list">
                                    ${openQuestions.map(renderOpenQuestionCard).join('')}
                                </div>
                            </section>
                        `
                        : ''}

                    <section class="form-builder-analytics-section">
                        <div class="form-builder-analytics-head">
                            <h2>Embudo del formulario</h2>
                        </div>
                        <div class="form-builder-funnel-layout">
                            <div class="form-builder-funnel-visual">
                                ${renderLiteralFunnel(funnel)}
                            </div>
                        </div>
                    </section>
                </div>
            `;
    };

    const initialSummary = (() => {
        if (!(summaryStateNode instanceof HTMLScriptElement)) {
            return null;
        }

        try {
            return JSON.parse(summaryStateNode.textContent || '{}');
        } catch (error) {
            return null;
        }
    })();

    if (initialSummary) {
        renderResponses(initialSummary);
    }

    const stopStatsPolling = () => {
        window.clearInterval(statsPollTimer);
        statsPollTimer = 0;

        if (statsRequestController) {
            statsRequestController.abort();
            statsRequestController = null;
        }
    };

    const loadStats = async () => {
        if (!canLoadStats) {
            return;
        }

        if (statsRequestController) {
            statsRequestController.abort();
        }

        statsRequestController = new AbortController();

        try {
            const response = await fetch(`${apiUrl}&action=stats&form_id=${encodeURIComponent(formId)}`, {
                credentials: 'same-origin',
                signal: statsRequestController.signal,
            });
            const payload = await response.json();

            if (!response.ok || !payload?.success) {
                throw new Error(payload?.message || 'No fue posible cargar las respuestas.');
            }

            renderResponses(payload.data?.summary || {});
        } catch (error) {
            if (error?.name === 'AbortError') {
                return;
            }
        } finally {
            statsRequestController = null;
        }
    };

    const startStatsPolling = () => {
        if (!canLoadStats || statsPollTimer) {
            return;
        }

        void loadStats();
        statsPollTimer = window.setInterval(() => {
            void loadStats();
        }, pollIntervalMs);
    };

    const activateTab = (target) => {
        form.querySelectorAll('[data-form-tab]').forEach((tab) => {
            tab.classList.toggle('is-active', tab.dataset.formTab === target);
        });

        form.querySelectorAll('[data-form-panel]').forEach((panel) => {
            panel.classList.toggle('is-active', panel.dataset.formPanel === target);
        });

        if (sideTools instanceof HTMLElement) {
            sideTools.hidden = target !== 'questions';
        }

        if (target === 'responses') {
            startStatsPolling();
        } else {
            stopStatsPolling();
        }
    };

    addFieldButton.addEventListener('click', (event) => {
        event.preventDefault();
        addField('single_choice');
    });

    duplicateActiveButton?.addEventListener('click', () => {
        const activeCard = fieldsContainer.querySelector('[data-form-field-card].is-active') || getLastCard();

        if (activeCard) {
            duplicateCard(activeCard);
        }
    });

    settingsButton?.addEventListener('click', () => {
        activateTab('settings');
    });

    titleInput?.addEventListener('input', updateDocTitle);

    responsesShell?.addEventListener('click', (event) => {
        const copyButton = closest(event.target, '[data-form-copy-chart]');

        if (!copyButton) {
            return;
        }

        event.preventDefault();
        void copyChoiceChart(copyButton);
    });

    form.querySelectorAll('[data-form-tab]').forEach((tab) => {
        tab.addEventListener('click', () => {
            activateTab(tab.dataset.formTab || 'questions');
        });
    });

    fieldsContainer.addEventListener('focusin', (event) => {
        const card = closest(event.target, '[data-form-field-card]');

        if (card) {
            setActiveCard(card);
        }
    });

    fieldsContainer.addEventListener('click', (event) => {
        const card = closest(event.target, '[data-form-field-card]');

        if (card) {
            setActiveCard(card);
        }

        const deleteButton = closest(event.target, '[data-form-field-delete]');
        const duplicateButton = closest(event.target, '[data-form-field-duplicate]');
        const moveButton = closest(event.target, '[data-form-field-move]');
        const typeOptionButton = closest(event.target, '[data-form-type-option]');
        const optionAddButton = closest(event.target, '[data-form-option-add]');
        const optionRemoveButton = closest(event.target, '[data-form-option-remove]');

        if (typeOptionButton && card) {
            event.preventDefault();
            const typeInput = card.querySelector('[data-form-field-input="type"]');
            const picker = closest(typeOptionButton, '[data-form-type-picker]');

            if (typeInput) {
                typeInput.value = typeOptionButton.dataset.value || 'single_choice';
                syncTypePicker(card);
                syncPreview(card);
                updateChoiceVisibility(card);
            }

            if (picker) {
                picker.removeAttribute('open');
            }
            return;
        }

        if (optionAddButton && card) {
            event.preventDefault();
            const typeInput = card.querySelector('[data-form-field-input="type"]');
            const optionItems = card.querySelector('[data-form-option-items]');

            if (optionItems) {
                const type = String(typeInput?.value || 'single_choice');
                optionItems.insertAdjacentHTML('beforeend', createOptionRow(type, '', optionItems.children.length));
                syncOptionStorage(card);
                syncPreview(card);
                optionItems.querySelector('[data-form-option-row]:last-child [data-form-option-input]')?.focus();
            }
            return;
        }

        if (optionRemoveButton && card) {
            event.preventDefault();
            const optionRow = closest(optionRemoveButton, '[data-form-option-row]');
            optionRow?.remove();
            syncOptionStorage(card);
            syncPreview(card);
            updateChoiceVisibility(card);
            return;
        }

        if (!deleteButton && !duplicateButton && !moveButton) {
            if (!closest(event.target, '[data-form-type-picker]')) {
                form.querySelectorAll('[data-form-type-picker][open]').forEach((picker) => {
                    if (picker !== closest(event.target, '[data-form-type-picker]')) {
                        picker.removeAttribute('open');
                    }
                });
            }
            return;
        }

        event.preventDefault();

        if (!card) {
            return;
        }

        if (deleteButton) {
            card.remove();
            updateIndexes();
            setActiveCard(getLastCard());
            return;
        }

        if (duplicateButton) {
            duplicateCard(card);
            return;
        }

        const direction = moveButton.dataset.formFieldMove;

        if (direction === 'up' && card.previousElementSibling?.matches('[data-form-field-card]')) {
            fieldsContainer.insertBefore(card, card.previousElementSibling);
        }

        if (direction === 'down' && card.nextElementSibling?.matches('[data-form-field-card]')) {
            fieldsContainer.insertBefore(card.nextElementSibling, card);
        }

        updateIndexes();
        setActiveCard(card);
    });

    fieldsContainer.addEventListener('change', (event) => {
        const optionInput = closest(event.target, '[data-form-option-input]');

        if (optionInput) {
            const card = optionInput.closest('[data-form-field-card]');

            if (card) {
                syncOptionStorage(card);
                syncPreview(card);
            }

            return;
        }

        const control = closest(event.target, '[data-form-field-input="type"]');

        if (!control) {
            return;
        }

        const card = control.closest('[data-form-field-card]');

        if (card) {
            syncTypePicker(card);
            syncPreview(card);
            updateChoiceVisibility(card);
        }
    });

    fieldsContainer.addEventListener('input', (event) => {
        const optionInput = closest(event.target, '[data-form-option-input]');

        if (!optionInput) {
            return;
        }

        const card = optionInput.closest('[data-form-field-card]');

        if (card) {
            syncOptionStorage(card);
            syncPreview(card);
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopStatsPolling();
            return;
        }

        const activeTab = form.querySelector('[data-form-tab].is-active')?.dataset.formTab || 'questions';

        if (activeTab === 'responses') {
            startStatsPolling();
        }
    });

    updateIndexes();
    updateDocTitle();
    setActiveCard(getCards()[0] || null);

    const activeTab = form.querySelector('[data-form-tab].is-active')?.dataset.formTab || 'questions';
    activateTab(activeTab);
})();
