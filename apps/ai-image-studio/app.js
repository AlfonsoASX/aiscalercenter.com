(() => {
    const root = document.querySelector('[data-ai-images-app]');
    const stateNode = document.getElementById('ai-images-state');

    if (!root || !stateNode) {
        return;
    }

    let serverState = {};

    try {
        serverState = JSON.parse(stateNode.textContent || '{}');
    } catch (error) {
        serverState = {};
    }

    const form = root.querySelector('[data-ai-images-form]');
    const resultsRoot = root.querySelector('[data-ai-results]');
    const previewStage = root.querySelector('[data-ai-preview-stage]');
    const previewStyle = root.querySelector('[data-ai-preview-style]');
    const previewRatio = root.querySelector('[data-ai-preview-ratio]');
    const previewHeadline = root.querySelector('[data-ai-preview-headline]');
    const previewCopy = root.querySelector('[data-ai-preview-copy]');
    const styleInput = root.querySelector('[data-ai-style-input]');
    const styleCards = Array.from(root.querySelectorAll('[data-ai-style-option]'));
    const quickButtons = Array.from(root.querySelectorAll('[data-ai-prompt-insert]'));
    const apiUrl = root.getAttribute('data-api-url') || '';
    const styleLabelMap = new Map((Array.isArray(serverState.styles) ? serverState.styles : []).map((item) => [String(item.key || ''), String(item.label || '')]));

    if (!form || !resultsRoot || !previewStage || !previewStyle || !previewRatio || !previewHeadline || !previewCopy || !styleInput) {
        return;
    }

    const promptField = form.elements.namedItem('prompt');
    const ratioField = form.elements.namedItem('aspect_ratio');
    const brandField = form.elements.namedItem('brand_note');
    const negativeField = form.elements.namedItem('negative_prompt');
    const submitButton = form.querySelector('button[type="submit"]');

    function updatePreview() {
        const prompt = String(promptField?.value || '').trim();
        const ratio = String(ratioField?.value || '1:1').trim();
        const styleKey = String(styleInput.value || '').trim();
        const styleLabel = styleLabelMap.get(styleKey) || 'Estilo visual';
        const brandNote = String(brandField?.value || '').trim();
        const negative = String(negativeField?.value || '').trim();

        previewStyle.textContent = styleLabel;
        previewRatio.textContent = ratio;
        previewHeadline.textContent = prompt !== '' ? prompt.slice(0, 120) : 'Tu concepto aparecera aqui';

        const details = [];

        if (brandNote !== '') {
            details.push(`Marca: ${brandNote}`);
        }

        if (negative !== '') {
            details.push(`Evitar: ${negative}`);
        }

        previewCopy.textContent = details.length > 0
            ? details.join(' · ')
            : 'Describe la imagen que quieres crear y la herramienta armara un brief visual mas claro antes de enviarlo al motor de IA.';

        previewStage.setAttribute('data-ratio', ratio.replace(':', '-'));
    }

    function setActiveStyle(styleKey) {
        styleInput.value = styleKey;

        styleCards.forEach((card) => {
            card.classList.toggle('is-active', card.getAttribute('data-ai-style-option') === styleKey);
        });

        updatePreview();
    }

    function renderNotice(message, type) {
        const tone = type === 'error' ? 'ai-images-empty ai-images-empty--error' : 'ai-images-empty';
        resultsRoot.innerHTML = `
            <div class="${tone}">
                <strong>${escapeHtml(type === 'error' ? 'No fue posible generar' : 'Generador preparado')}</strong>
                <p>${escapeHtml(message)}</p>
            </div>
        `;
    }

    function renderResults(images) {
        if (!Array.isArray(images) || images.length === 0) {
            renderNotice('Aun no hay imagenes generadas.', 'info');
            return;
        }

        resultsRoot.innerHTML = images
            .map((image) => `
                <article class="ai-images-result-card">
                    <div class="ai-images-result-media">
                        <img src="${escapeAttribute(String(image.url || ''))}" alt="${escapeAttribute(String(image.alt || 'Imagen generada con IA'))}">
                    </div>
                    <div class="ai-images-result-copy">
                        <strong>${escapeHtml(String(image.label || 'Resultado'))}</strong>
                        <p>${escapeHtml(String(image.prompt || ''))}</p>
                    </div>
                </article>
            `)
            .join('');
    }

    async function handleSubmit(event) {
        event.preventDefault();

        if (!apiUrl) {
            renderNotice('No encontramos la ruta segura del generador.', 'error');
            return;
        }

        if (!serverState.provider_ready) {
            renderNotice(String(serverState.setup_message || 'Completa la configuracion del proveedor en config/ai_images.php.'), 'error');
            return;
        }

        if (submitButton) {
            submitButton.disabled = true;
        }

        resultsRoot.innerHTML = `
            <div class="ai-images-empty">
                <strong>Generando imagenes...</strong>
                <p>Estamos enviando el prompt al motor configurado en el servidor.</p>
            </div>
        `;

        try {
            const payload = {
                prompt: String(promptField?.value || ''),
                style: String(styleInput.value || ''),
                aspect_ratio: String(ratioField?.value || '1:1'),
                quantity: Number(form.elements.namedItem('quantity')?.value || 1),
                brand_note: String(brandField?.value || ''),
                negative_prompt: String(negativeField?.value || ''),
            };

            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok || !data.success) {
                throw new Error(String(data.message || 'No fue posible generar las imagenes.'));
            }

            renderResults(Array.isArray(data.data?.images) ? data.data.images : []);
        } catch (error) {
            renderNotice(error instanceof Error ? error.message : 'No fue posible generar las imagenes.', 'error');
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    }

    styleCards.forEach((card) => {
        card.addEventListener('click', () => {
            setActiveStyle(card.getAttribute('data-ai-style-option') || '');
        });
    });

    quickButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const snippet = button.getAttribute('data-ai-prompt-insert') || '';
            const current = String(promptField?.value || '').trim();
            const next = current === '' ? snippet : `${current}. ${snippet}`;

            if (promptField) {
                promptField.value = next;
            }

            updatePreview();
        });
    });

    form.addEventListener('input', updatePreview);
    form.addEventListener('submit', handleSubmit);
    setActiveStyle(String(styleInput.value || 'foto-producto'));
    updatePreview();

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escapeAttribute(value) {
        return escapeHtml(value);
    }
})();
