(() => {
    const form = document.querySelector('[data-form-builder]');

    if (!form) {
        return;
    }

    const fieldsContainer = form.querySelector('[data-form-fields]');
    const newFieldTypeSelect = form.querySelector('[data-form-new-field-type]');
    const addFieldButton = form.querySelector('[data-form-add-field]');
    const duplicateActiveButton = form.querySelector('[data-form-duplicate-active]');
    const settingsButton = form.querySelector('[data-form-focus-settings]');
    const titleInput = form.querySelector('[data-form-title-input]');
    const docTitle = form.querySelector('[data-form-doc-title]');

    if (!fieldsContainer || !newFieldTypeSelect || !addFieldButton) {
        return;
    }

    const fieldTypes = Array.from(newFieldTypeSelect.options).map((option) => ({
        value: option.value,
        label: option.textContent?.trim() || option.value,
    }));

    const choiceTypes = new Set(['single_choice', 'multiple_choice']);
    const typeLabel = (type) => fieldTypes.find((fieldType) => fieldType.value === type)?.label || 'Texto corto';
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

    const createId = (prefix) => {
        if (window.crypto?.randomUUID) {
            return `${prefix}_${window.crypto.randomUUID().replaceAll('-', '').slice(0, 12)}`;
        }

        return `${prefix}_${Date.now().toString(36)}${Math.random().toString(36).slice(2, 8)}`;
    };

    const createTypeOptions = (selectedType) => fieldTypes.map((fieldType) => (
        `<option value="${escapeHtml(fieldType.value)}"${fieldType.value === selectedType ? ' selected' : ''}>${escapeHtml(fieldType.label)}</option>`
    )).join('');

    const createFieldCard = (type) => {
        const safeType = fieldTypes.some((fieldType) => fieldType.value === type) ? type : 'short_text';
        const fieldId = createId('field');
        const defaultOptions = choiceTypes.has(safeType) ? 'Opcion 1\nOpcion 2' : '';

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
                    <label class="form-builder-field form-builder-type-field">
                        <span>Tipo</span>
                        <select name="field_type[0]" data-form-field-input="type">
                            ${createTypeOptions(safeType)}
                        </select>
                    </label>

                    <label class="form-builder-field form-builder-question-label">
                        <span>Etiqueta</span>
                        <input type="text" name="field_label[0]" value="${escapeHtml(typeLabel(safeType))}" placeholder="Pregunta sin titulo" data-form-field-input="label">
                    </label>

                    <label class="form-builder-field form-builder-field--wide">
                        <span>Ayuda del campo</span>
                        <input type="text" name="field_help_text[0]" value="" placeholder="Opcional" data-form-field-input="help_text">
                    </label>

                    <label class="form-builder-field form-builder-field--wide">
                        <span>Placeholder</span>
                        <input type="text" name="field_placeholder[0]" value="" placeholder="Texto de ayuda dentro del campo" data-form-field-input="placeholder">
                    </label>

                    <label class="form-builder-field form-builder-field--wide form-builder-options-field" data-form-options-wrap>
                        <span>Opciones, una por linea</span>
                        <textarea name="field_options[0]" rows="4" placeholder="Opcion 1&#10;Opcion 2" data-form-field-input="options">${escapeHtml(defaultOptions)}</textarea>
                        <small>Solo se usan para campos de opcion multiple.</small>
                    </label>
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

        optionsWrap?.classList.toggle('is-hidden', !choiceTypes.has(type));

        if (choiceTypes.has(type) && optionsInput && optionsInput.value.trim() === '') {
            optionsInput.value = 'Opcion 1\nOpcion 2';
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

            updateChoiceVisibility(card);
        });
    };

    const removeEmptyState = () => {
        fieldsContainer.querySelector('[data-form-empty]')?.remove();
    };

    const addField = (type) => {
        removeEmptyState();

        const card = createFieldCard(type);
        fieldsContainer.append(card);
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

    addFieldButton.addEventListener('click', (event) => {
        event.preventDefault();
        addField(newFieldTypeSelect.value);
    });

    duplicateActiveButton?.addEventListener('click', () => {
        const activeCard = fieldsContainer.querySelector('[data-form-field-card].is-active') || getLastCard();

        if (activeCard) {
            duplicateCard(activeCard);
        }
    });

    settingsButton?.addEventListener('click', () => {
        form.querySelector('[data-form-tab="settings"]')?.click();
    });

    titleInput?.addEventListener('input', updateDocTitle);

    form.querySelectorAll('[data-form-tab]').forEach((tab) => {
        tab.addEventListener('click', () => {
            const target = tab.dataset.formTab;

            form.querySelectorAll('[data-form-tab]').forEach((currentTab) => {
                currentTab.classList.toggle('is-active', currentTab === tab);
            });

            form.querySelectorAll('[data-form-panel]').forEach((panel) => {
                panel.classList.toggle('is-active', panel.dataset.formPanel === target);
            });
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

        if (!deleteButton && !duplicateButton && !moveButton) {
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
        const control = closest(event.target, '[data-form-field-input="type"]');

        if (!control) {
            return;
        }

        const card = control.closest('[data-form-field-card]');

        if (card) {
            updateChoiceVisibility(card);
        }
    });

    updateIndexes();
    updateDocTitle();
    setActiveCard(getCards()[0] || null);
})();
