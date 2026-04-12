(() => {
    const root = document.querySelector('[data-landing-builder]');

    if (!(root instanceof HTMLElement) || root.dataset.landingBuilderReady === 'true') {
        return;
    }

    root.dataset.landingBuilderReady = 'true';

    const blocksField = root.querySelector('#landing-blocks-json');
    const settingsField = root.querySelector('#landing-settings-json');
    const canvas = root.querySelector('[data-landing-canvas]');
    const inspector = root.querySelector('[data-landing-inspector]');
    const form = root.querySelector('[data-landing-form]');
    const titleInput = root.querySelector('[data-landing-title-input]');
    const docTitle = root.querySelector('[data-landing-doc-title]');
    const previewFrame = root.querySelector('.landing-builder-preview-frame');
    const uploadUrl = String(root.dataset.uploadUrl || '').trim();
    const storagePublicBase = String(root.dataset.storagePublicBase || '').trim().toLowerCase();
    const termsUrl = String(root.dataset.termsUrl || `${window.location.origin}/terminos-y-condiciones`).trim();
    const privacyUrl = String(root.dataset.privacyUrl || `${window.location.origin}/aviso-de-privacidad`).trim();

    if (!(blocksField instanceof HTMLTextAreaElement)
        || !(settingsField instanceof HTMLTextAreaElement)
        || !(canvas instanceof HTMLElement)
        || !(inspector instanceof HTMLElement)) {
        return;
    }

    const defaultSettings = {
        brand_name: 'AiScaler',
        primary_color: '#d93025',
        accent_color: '#1a73e8',
        background_color: '#fff7f5',
        surface_color: '#ffffff',
        text_color: '#202124',
        font_family: 'Roboto',
        seo_title: '',
        seo_description: '',
        og_image: '',
        canonical_url: '',
    };

    let blocks = normalizeBlocks(parseJson(blocksField.value, []));
    let settings = { ...defaultSettings, ...parseJson(settingsField.value, {}) };
    let selectedId = blocks[0]?.id ?? '';
    let inspectorTab = 'block';
    let draggingId = '';
    let uploadFeedback = null;

    function parseJson(value, fallback) {
        try {
            const parsed = JSON.parse(String(value || '').trim());
            return parsed && typeof parsed === 'object' ? parsed : fallback;
        } catch (error) {
            return fallback;
        }
    }

    function normalizeBlocks(nextBlocks) {
        const normalized = Array.isArray(nextBlocks) ? nextBlocks.filter((block) => block && typeof block === 'object') : [];
        return normalized.length > 0 ? normalized : [createBlock('top'), createBlock('hero'), createBlock('feature_grid'), createBlock('cta'), createBlock('footer')];
    }

    function createBlock(type) {
        const id = `block_${Date.now().toString(16)}_${Math.random().toString(16).slice(2, 8)}`;

        const factories = {
            top: () => ({
                id,
                type: 'top',
                brand_name: 'AiScaler',
                logo_url: '',
                items: [
                    { title: 'Inicio', body: '#inicio' },
                    { title: 'Beneficios', body: '#beneficios' },
                    { title: 'Contacto', body: '#contacto' },
                ],
                button_label: 'Empezar',
                button_url: '#contacto',
                background_image_url: '',
                background_color: '#ffffff',
                background_opacity: '0',
            }),
            hero: () => ({
                id,
                type: 'hero',
                eyebrow: 'Nueva oferta',
                heading: 'Convierte mas visitantes en clientes',
                body: 'Presenta tu propuesta con una landing clara, visual y lista para compartir.',
                primary_label: 'Quiero empezar',
                primary_url: '#contacto',
                secondary_label: 'Ver beneficios',
                secondary_url: '#beneficios',
                image_url: '',
                background_image_url: '',
                background_color: '#fff7f5',
                background_opacity: '0',
            }),
            feature_grid: () => ({
                id,
                type: 'feature_grid',
                eyebrow: 'Beneficios',
                heading: 'Todo lo importante en una sola pagina',
                body: 'Explica por que tu oferta importa sin obligar al usuario a pensar demasiado.',
                items: [
                    { title: 'Mensaje claro', body: 'Una promesa principal visible desde el primer scroll.' },
                    { title: 'Prueba rapida', body: 'Bloques para mostrar beneficios y siguiente paso.' },
                    { title: 'Accion directa', body: 'Llamadas a la accion listas para convertir.' },
                ],
                background_image_url: '',
                background_color: '#ffffff',
                background_opacity: '0',
            }),
            split: () => ({
                id,
                type: 'split',
                eyebrow: 'Como funciona',
                heading: 'Muestra tu proceso en un bloque facil de leer',
                body: 'Combina texto, prueba visual y una accion principal para explicar sin saturar.',
                image_url: '',
                button_label: 'Conocer mas',
                button_url: '#contacto',
                media_position: 'right',
                background_image_url: '',
                background_color: '#ffffff',
                background_opacity: '0',
            }),
            testimonial: () => ({
                id,
                type: 'testimonial',
                quote: 'Esta landing nos ayudo a explicar la oferta de forma clara y recibir mejores prospectos.',
                author: 'Cliente ideal',
                role: 'Fundador de empresa',
                background_image_url: '',
                background_color: '#ffffff',
                background_opacity: '0',
            }),
            cta: () => ({
                id,
                type: 'cta',
                eyebrow: 'Siguiente paso',
                heading: 'Publica una version simple y mejora con datos reales',
                body: 'Evita construir de mas. Lanza, mide y ajusta la pagina con claridad.',
                button_label: 'Solicitar informacion',
                button_url: '#contacto',
                background_image_url: '',
                background_color: '#fff7f5',
                background_opacity: '0',
            }),
            footer: () => ({
                id,
                type: 'footer',
                brand_name: 'AiScaler',
                body: 'Una landing clara para presentar tu oferta y convertir visitantes en oportunidades.',
                items: [
                    { title: 'Terminos', body: termsUrl },
                    { title: 'Privacidad', body: privacyUrl },
                ],
                copyright: '(c) 2026 AiScaler. Todos los derechos reservados.',
                background_image_url: '',
                background_color: '#ffffff',
                background_opacity: '0',
            }),
        };

        return (factories[type] ?? factories.hero)();
    }

    function syncState() {
        sanitizeStateImages();
        blocksField.value = JSON.stringify(blocks);
        settingsField.value = JSON.stringify(settings);
    }

    function sanitizeStateImages() {
        blocks.forEach((block) => {
            ['image_url', 'logo_url', 'background_image_url'].forEach((field) => {
                if (Object.prototype.hasOwnProperty.call(block, field)) {
                    block[field] = sanitizeImageValue(block[field]);
                }
            });
        });

        ['og_image'].forEach((field) => {
            if (Object.prototype.hasOwnProperty.call(settings, field)) {
                settings[field] = sanitizeImageValue(settings[field]);
            }
        });
    }

    function render() {
        renderCanvas();
        renderInspector();
    }

    function renderCanvas() {
        syncState();
        applySettings();

        if (!blocks.some((block) => block.id === selectedId)) {
            selectedId = blocks[0]?.id ?? '';
        }

        canvas.innerHTML = blocks.map(renderBlock).join('');
    }

    function applySettings() {
        if (previewFrame instanceof HTMLElement) {
            previewFrame.style.setProperty('--landing-primary', settings.primary_color || defaultSettings.primary_color);
            previewFrame.style.setProperty('--landing-accent', settings.accent_color || defaultSettings.accent_color);
            previewFrame.style.setProperty('--landing-bg', settings.background_color || defaultSettings.background_color);
            previewFrame.style.setProperty('--landing-surface', settings.surface_color || defaultSettings.surface_color);
            previewFrame.style.setProperty('--landing-text', settings.text_color || defaultSettings.text_color);
            previewFrame.style.setProperty('--landing-font', settings.font_family || defaultSettings.font_family);
        }

    }

    function renderBlock(block) {
        const selected = block.id === selectedId;
        const toolbar = selected ? renderFloatingToolbar() : '';
        const className = `landing-preview-block landing-preview-block--${escapeToken(block.type)}${selected ? ' is-selected' : ''}`;
        const style = renderBlockBackgroundStyle(block);

        return `
            <section class="${className}" data-landing-block-id="${escapeHtml(block.id)}" draggable="true" style="${escapeHtml(style)}">
                ${toolbar}
                ${renderBlockContent(block)}
            </section>
        `;
    }

    function renderBlockBackgroundStyle(block) {
        const imageUrl = sanitizeImageValue(block.background_image_url || '');
        const opacity = normalizeOpacity(block.background_opacity);
        const color = normalizeColor(block.background_color, settings.background_color || defaultSettings.background_color);

        return [
            `--block-bg-image: ${imageUrl !== '' ? `url("${escapeCssUrl(imageUrl)}")` : 'none'}`,
            `--block-bg-color: ${color}`,
            `--block-bg-opacity: ${(opacity / 100).toFixed(2)}`,
        ].join('; ');
    }

    function renderFloatingToolbar() {
        return `
            <div class="landing-builder-floating-tools">
                <button type="button" data-landing-action="move-up" title="Subir"><span class="material-symbols-rounded">arrow_upward</span></button>
                <button type="button" data-landing-action="move-down" title="Bajar"><span class="material-symbols-rounded">arrow_downward</span></button>
                <button type="button" data-landing-action="duplicate" title="Duplicar"><span class="material-symbols-rounded">content_copy</span></button>
                <button type="button" data-landing-action="delete" class="is-danger" title="Eliminar"><span class="material-symbols-rounded">delete</span></button>
            </div>
        `;
    }

    function renderBlockContent(block) {
        if (block.type === 'top') {
            return `
                <div class="landing-preview-topbar">
                    <a href="#inicio" class="landing-preview-brand" data-landing-preview-link>
                        ${renderLogo(block.logo_url)}
                        <strong>${escapeHtml(block.brand_name || settings.brand_name || 'AiScaler')}</strong>
                    </a>
                    <nav class="landing-preview-nav-links">
                        ${renderNavLinks(block.items)}
                    </nav>
                    ${renderButton(block.button_label, block.button_url, true)}
                </div>
            `;
        }

        if (block.type === 'feature_grid') {
            return `
                <div class="landing-preview-section-copy">
                    <span>${escapeHtml(block.eyebrow || 'Beneficios')}</span>
                    <h2>${escapeHtml(block.heading || 'Beneficios principales')}</h2>
                    <p>${escapeHtml(block.body || 'Describe por que esta oferta importa.')}</p>
                </div>
                <div class="landing-preview-feature-grid">
                    ${(Array.isArray(block.items) ? block.items : []).map((item) => `
                        <article>
                            <span class="material-symbols-rounded">check_circle</span>
                            <h3>${escapeHtml(item.title || 'Beneficio')}</h3>
                            <p>${escapeHtml(item.body || 'Explica el valor de forma breve.')}</p>
                        </article>
                    `).join('')}
                </div>
            `;
        }

        if (block.type === 'split') {
            return `
                <div class="landing-preview-split ${block.media_position === 'left' ? 'is-reversed' : ''}">
                    ${renderMedia(block.image_url, 'Imagen del bloque')}
                    <div class="landing-preview-section-copy">
                        <span>${escapeHtml(block.eyebrow || 'Como funciona')}</span>
                        <h2>${escapeHtml(block.heading || 'Titulo del bloque')}</h2>
                        <p>${escapeHtml(block.body || 'Describe el proceso o la transformacion que ofreces.')}</p>
                        ${renderButton(block.button_label, block.button_url, false)}
                    </div>
                </div>
            `;
        }

        if (block.type === 'testimonial') {
            return `
                <div class="landing-preview-testimonial-card">
                    <span class="material-symbols-rounded">format_quote</span>
                    <blockquote>${escapeHtml(block.quote || 'Agrega una frase poderosa de prueba social.')}</blockquote>
                    <strong>${escapeHtml(block.author || 'Nombre del cliente')}</strong>
                    <small>${escapeHtml(block.role || 'Rol o empresa')}</small>
                </div>
            `;
        }

        if (block.type === 'cta') {
            return `
                <div class="landing-preview-cta-card" id="contacto">
                    <span>${escapeHtml(block.eyebrow || 'Siguiente paso')}</span>
                    <h2>${escapeHtml(block.heading || 'Invita al usuario a tomar accion')}</h2>
                    <p>${escapeHtml(block.body || 'Cierra con una instruccion simple y clara.')}</p>
                    ${renderButton(block.button_label, block.button_url, true)}
                </div>
            `;
        }

        if (block.type === 'footer') {
            return `
                <footer class="landing-preview-footer">
                    <div>
                        <strong>${escapeHtml(block.brand_name || settings.brand_name || 'AiScaler')}</strong>
                        <p>${escapeHtml(block.body || 'Agrega una descripcion breve para cerrar la pagina.')}</p>
                    </div>
                    <nav class="landing-preview-footer-links">
                        ${renderNavLinks(block.items)}
                    </nav>
                    <small>${escapeHtml(block.copyright || '')}</small>
                </footer>
            `;
        }

        return `
            <div class="landing-preview-hero">
                <div class="landing-preview-hero-copy">
                    <span>${escapeHtml(block.eyebrow || 'Nueva oferta')}</span>
                    <h1>${escapeHtml(block.heading || 'Titulo principal de la landing')}</h1>
                    <p>${escapeHtml(block.body || 'Explica tu propuesta en una frase clara.')}</p>
                    <div class="landing-preview-actions">
                        ${renderButton(block.primary_label, block.primary_url, true)}
                        ${renderButton(block.secondary_label, block.secondary_url, false)}
                    </div>
                </div>
                ${renderMedia(block.image_url, 'Imagen principal')}
            </div>
        `;
    }

    function renderMedia(url, alt) {
        const imageUrl = sanitizeImageValue(url);

        if (imageUrl === '') {
            return `
                <div class="landing-preview-media is-empty">
                    <span class="material-symbols-rounded">add_photo_alternate</span>
                    <small>Imagen demo 16:9</small>
                </div>
            `;
        }

        return `<div class="landing-preview-media"><img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(alt)}" loading="lazy"></div>`;
    }

    function renderButton(label, url, primary) {
        const text = String(label || '').trim();

        if (text === '') {
            return '';
        }

        return `<a href="${escapeHtml(url || '#')}" data-landing-preview-link class="${primary ? 'landing-preview-button' : 'landing-preview-button landing-preview-button--secondary'}">${escapeHtml(text)}</a>`;
    }

    function renderLogo(url) {
        const logoUrl = sanitizeImageValue(url);

        if (logoUrl === '') {
            return '<span class="landing-preview-brand-mark" aria-hidden="true">A</span>';
        }

        return `<img src="${escapeHtml(logoUrl)}" alt="" loading="lazy">`;
    }

    function renderNavLinks(items) {
        const links = Array.isArray(items) ? items : [];

        return links
            .filter((item) => item && typeof item === 'object' && String(item.title || '').trim() !== '')
            .map((item) => `<a href="${escapeHtml(item.body || '#')}" data-landing-preview-link>${escapeHtml(item.title || '')}</a>`)
            .join('');
    }

    function renderInspector() {
        const selectedBlock = blocks.find((block) => block.id === selectedId);
        const blockPanel = selectedBlock ? renderBlockInspector(selectedBlock) : `
            <div class="landing-builder-inspector-empty">
                <span class="material-symbols-rounded">touch_app</span>
                <h3>Selecciona un bloque</h3>
                <p>Al hacer clic en una seccion, aqui apareceran sus controles.</p>
            </div>
        `;

        inspector.innerHTML = `
            <div class="landing-builder-inspector-tabs" role="tablist" aria-label="Panel de configuracion">
                <button type="button" class="${inspectorTab === 'block' ? 'is-active' : ''}" data-landing-inspector-tab="block" role="tab" aria-selected="${inspectorTab === 'block' ? 'true' : 'false'}">
                    Elemento
                </button>
                <button type="button" class="${inspectorTab === 'style' ? 'is-active' : ''}" data-landing-inspector-tab="style" role="tab" aria-selected="${inspectorTab === 'style' ? 'true' : 'false'}">
                    Estilo global
                </button>
                <button type="button" class="${inspectorTab === 'seo' ? 'is-active' : ''}" data-landing-inspector-tab="seo" role="tab" aria-selected="${inspectorTab === 'seo' ? 'true' : 'false'}">
                    SEO
                </button>
            </div>
            ${renderUploadFeedback()}
            ${inspectorTab === 'block' ? blockPanel : ''}
            ${inspectorTab === 'style' ? renderGlobalStyleInspector() : ''}
            ${inspectorTab === 'seo' ? renderSeoInspector() : ''}
        `;
    }

    function renderUploadFeedback() {
        if (!uploadFeedback) {
            return '';
        }

        return `
            <div class="landing-builder-upload-feedback ${uploadFeedback.type === 'error' ? 'is-error' : 'is-success'}">
                ${escapeHtml(uploadFeedback.message)}
            </div>
        `;
    }

    function renderGlobalStyleInspector() {
        return `
            <div class="landing-builder-inspector-section">
                <h3>Estilo global</h3>
                ${renderSettingInput('brand_name', 'Marca')}
                ${renderSettingInput('primary_color', 'Color principal', 'color')}
                ${renderSettingInput('accent_color', 'Color acento', 'color')}
                ${renderSettingInput('background_color', 'Fondo', 'color')}
                ${renderSettingInput('surface_color', 'Tarjetas', 'color')}
                ${renderSettingInput('text_color', 'Texto', 'color')}
            </div>
        `;
    }

    function renderSeoInspector() {
        return `
            <div class="landing-builder-inspector-section">
                <h3>Opciones SEO</h3>
                ${renderSettingInput('seo_title', 'Titulo SEO')}
                ${renderSettingTextarea('seo_description', 'Descripcion SEO', 'Texto recomendado para resultados de busqueda y redes sociales.')}
                ${renderSettingImageInput('og_image', 'Imagen para compartir')}
                ${renderSettingInput('canonical_url', 'URL canonica')}
            </div>
        `;
    }

    function renderBlockInspector(block) {
        const title = {
            top: 'Top / navegacion',
            hero: 'Hero principal',
            feature_grid: 'Beneficios',
            split: 'Texto + imagen',
            testimonial: 'Testimonio',
            cta: 'CTA',
            footer: 'Footer',
        }[block.type] ?? 'Bloque';

        return `
            <div class="landing-builder-inspector-section">
                <div class="landing-builder-inspector-head">
                    <h3>${escapeHtml(title)}</h3>
                    <span>${escapeHtml(block.type)}</span>
                </div>
                ${renderFieldsForBlock(block)}
            </div>
        `;
    }

    function renderFieldsForBlock(block) {
        if (block.type === 'top') {
            return `
                ${renderBlockInput(block, 'brand_name', 'Marca')}
                ${renderBlockImageInput(block, 'logo_url', 'Logo')}
                ${renderBlockTextarea(block, 'items', 'Vinculos del menu', 'Texto | URL\\nBeneficios | #beneficios', itemsToText(block.items))}
                ${renderBlockInput(block, 'button_label', 'Texto del boton')}
                ${renderBlockInput(block, 'button_url', 'URL del boton')}
                ${renderBlockBackgroundControls(block)}
            `;
        }

        if (block.type === 'feature_grid') {
            return `
                ${renderBlockInput(block, 'eyebrow', 'Etiqueta')}
                ${renderBlockInput(block, 'heading', 'Titulo')}
                ${renderBlockTextarea(block, 'body', 'Descripcion')}
                ${renderBlockTextarea(block, 'items', 'Beneficios', 'Titulo | descripcion\\nTitulo 2 | descripcion 2', itemsToText(block.items))}
                ${renderBlockBackgroundControls(block)}
            `;
        }

        if (block.type === 'split') {
            return `
                ${renderBlockInput(block, 'eyebrow', 'Etiqueta')}
                ${renderBlockInput(block, 'heading', 'Titulo')}
                ${renderBlockTextarea(block, 'body', 'Texto')}
                ${renderBlockImageInput(block, 'image_url', 'Imagen del bloque')}
                ${renderBlockInput(block, 'button_label', 'Texto del boton')}
                ${renderBlockInput(block, 'button_url', 'URL del boton')}
                <label class="landing-builder-control">
                    <span>Posicion de imagen</span>
                    <select data-landing-block-field="media_position">
                        <option value="right" ${block.media_position === 'right' ? 'selected' : ''}>Derecha</option>
                        <option value="left" ${block.media_position === 'left' ? 'selected' : ''}>Izquierda</option>
                    </select>
                </label>
                ${renderBlockBackgroundControls(block)}
            `;
        }

        if (block.type === 'testimonial') {
            return `
                ${renderBlockTextarea(block, 'quote', 'Frase')}
                ${renderBlockInput(block, 'author', 'Autor')}
                ${renderBlockInput(block, 'role', 'Rol o empresa')}
                ${renderBlockBackgroundControls(block)}
            `;
        }

        if (block.type === 'cta') {
            return `
                ${renderBlockInput(block, 'eyebrow', 'Etiqueta')}
                ${renderBlockInput(block, 'heading', 'Titulo')}
                ${renderBlockTextarea(block, 'body', 'Descripcion')}
                ${renderBlockInput(block, 'button_label', 'Texto del boton')}
                ${renderBlockInput(block, 'button_url', 'URL del boton')}
                ${renderBlockBackgroundControls(block)}
            `;
        }

        if (block.type === 'footer') {
            return `
                ${renderBlockInput(block, 'brand_name', 'Marca')}
                ${renderBlockTextarea(block, 'body', 'Descripcion')}
                ${renderBlockTextarea(block, 'items', 'Vinculos del footer', `Texto | URL\\nPrivacidad | ${privacyUrl}`, itemsToText(block.items))}
                ${renderBlockInput(block, 'copyright', 'Copyright')}
                ${renderBlockBackgroundControls(block)}
            `;
        }

        return `
            ${renderBlockInput(block, 'eyebrow', 'Etiqueta')}
            ${renderBlockInput(block, 'heading', 'Titulo')}
            ${renderBlockTextarea(block, 'body', 'Descripcion')}
            ${renderBlockInput(block, 'primary_label', 'Boton principal')}
            ${renderBlockInput(block, 'primary_url', 'URL principal')}
            ${renderBlockInput(block, 'secondary_label', 'Boton secundario')}
            ${renderBlockInput(block, 'secondary_url', 'URL secundaria')}
            ${renderBlockImageInput(block, 'image_url', 'Imagen principal')}
            ${renderBlockBackgroundControls(block)}
        `;
    }

    function renderSettingInput(key, label, type = 'text') {
        const value = type === 'color'
            ? normalizeColor(settings[key], '#ffffff')
            : settings[key] ?? '';

        return `
            <label class="landing-builder-control">
                <span>${escapeHtml(label)}</span>
                <input type="${escapeHtml(type)}" value="${escapeHtml(value)}" data-landing-setting="${escapeHtml(key)}">
            </label>
        `;
    }

    function renderSettingImageInput(key, label) {
        const value = sanitizeImageValue(settings[key] ?? '');

        return renderImageUploadControl({
            label,
            value,
            inputAttribute: `data-landing-setting="${escapeHtml(key)}"`,
            uploadAttribute: `data-landing-upload-setting="${escapeHtml(key)}"`,
            clearAttribute: `data-landing-clear-setting="${escapeHtml(key)}"`,
        });
    }

    function renderSettingTextarea(key, label, placeholder = '') {
        return `
            <label class="landing-builder-control">
                <span>${escapeHtml(label)}</span>
                <textarea rows="4" placeholder="${escapeHtml(placeholder)}" data-landing-setting="${escapeHtml(key)}">${escapeHtml(settings[key] ?? '')}</textarea>
            </label>
        `;
    }

    function renderBlockInput(block, key, label, type = 'text') {
        const value = type === 'color'
            ? normalizeColor(block[key], '#ffffff')
            : block[key] ?? '';

        return `
            <label class="landing-builder-control">
                <span>${escapeHtml(label)}</span>
                <input type="${escapeHtml(type)}" value="${escapeHtml(value)}" data-landing-block-field="${escapeHtml(key)}">
            </label>
        `;
    }

    function renderBlockImageInput(block, key, label) {
        const value = sanitizeImageValue(block[key] ?? '');

        return renderImageUploadControl({
            label,
            value,
            inputAttribute: `data-landing-block-field="${escapeHtml(key)}"`,
            uploadAttribute: `data-landing-upload-field="${escapeHtml(key)}" data-landing-upload-block-id="${escapeHtml(block.id)}"`,
            clearAttribute: `data-landing-clear-image-field="${escapeHtml(key)}" data-landing-clear-block-id="${escapeHtml(block.id)}"`,
        });
    }

    function renderImageUploadControl({ label, value, inputAttribute, uploadAttribute, clearAttribute }) {
        const safeValue = sanitizeImageValue(value);

        return `
            <div class="landing-builder-control landing-builder-image-control">
                <span>${escapeHtml(label)}</span>
                <input type="url" value="${escapeHtml(safeValue)}" ${inputAttribute} readonly placeholder="Sube una imagen para generar la URL segura">
                <div class="landing-builder-upload-row">
                    <label class="landing-builder-upload-button">
                        <input type="file" accept="image/*" ${uploadAttribute}>
                        <span class="material-symbols-rounded">upload</span>
                        <span>Subir archivo</span>
                    </label>
                    ${safeValue !== '' ? `
                        <a href="${escapeHtml(safeValue)}" target="_blank" rel="noreferrer noopener">Ver</a>
                        <button type="button" class="landing-builder-upload-clear" ${clearAttribute}>Quitar</button>
                    ` : ''}
                </div>
                <small>Se guarda como archivo en Supabase Storage; no se usa base64.</small>
            </div>
        `;
    }

    function renderBlockBackgroundControls(block) {
        const opacity = normalizeOpacity(block.background_opacity);

        return `
            <div class="landing-builder-background-controls">
                <h4>Fondo del bloque</h4>
                ${renderBlockImageInput(block, 'background_image_url', 'Imagen de fondo')}
                ${renderBlockInput(block, 'background_color', 'Color combinado', 'color')}
                <label class="landing-builder-control">
                    <span>Transparencia del color: ${opacity}%</span>
                    <input type="range" min="0" max="100" step="1" value="${escapeHtml(opacity)}" data-landing-block-field="background_opacity">
                </label>
            </div>
        `;
    }

    function renderBlockTextarea(block, key, label, placeholder = '', value = null) {
        return `
            <label class="landing-builder-control">
                <span>${escapeHtml(label)}</span>
                <textarea rows="4" placeholder="${escapeHtml(placeholder)}" data-landing-block-field="${escapeHtml(key)}">${escapeHtml(value ?? block[key] ?? '')}</textarea>
            </label>
        `;
    }

    function itemsToText(items) {
        return (Array.isArray(items) ? items : [])
            .map((item) => `${item.title || ''} | ${item.body || ''}`.trim())
            .filter(Boolean)
            .join('\n');
    }

    function textToItems(value) {
        return String(value || '')
            .split('\n')
            .map((line) => {
                const [title, ...bodyParts] = line.split('|');
                return {
                    title: String(title || '').trim(),
                    body: bodyParts.join('|').trim(),
                };
            })
            .filter((item) => item.title !== '' || item.body !== '');
    }

    function updateSelectedBlock(field, value) {
        const block = blocks.find((entry) => entry.id === selectedId);

        if (!block) {
            return;
        }

        block[field] = normalizeBlockFieldValue(field, value);
        renderCanvas();
    }

    function updateBlockById(blockId, field, value) {
        const block = blocks.find((entry) => entry.id === blockId);

        if (!block) {
            return;
        }

        block[field] = normalizeBlockFieldValue(field, value);
        selectedId = block.id;
        render();
    }

    function normalizeBlockFieldValue(field, value) {
        if (field === 'items') {
            return textToItems(value);
        }

        if (isImageField(field)) {
            return sanitizeImageValue(value);
        }

        if (field === 'background_opacity') {
            return String(normalizeOpacity(value));
        }

        return value;
    }

    function normalizeSettingValue(field, value) {
        return isImageField(field) ? sanitizeImageValue(value) : value;
    }

    function moveSelected(offset) {
        const index = blocks.findIndex((block) => block.id === selectedId);
        const targetIndex = index + offset;

        if (index < 0 || targetIndex < 0 || targetIndex >= blocks.length) {
            return;
        }

        const [block] = blocks.splice(index, 1);
        blocks.splice(targetIndex, 0, block);
        render();
    }

    function duplicateSelected() {
        const index = blocks.findIndex((block) => block.id === selectedId);

        if (index < 0) {
            return;
        }

        const copy = JSON.parse(JSON.stringify(blocks[index]));
        copy.id = `block_${Date.now().toString(16)}_${Math.random().toString(16).slice(2, 8)}`;
        blocks.splice(index + 1, 0, copy);
        selectedId = copy.id;
        render();
    }

    function deleteSelected() {
        if (blocks.length <= 1) {
            return;
        }

        const index = blocks.findIndex((block) => block.id === selectedId);
        blocks = blocks.filter((block) => block.id !== selectedId);
        selectedId = blocks[Math.max(0, index - 1)]?.id ?? blocks[0]?.id ?? '';
        render();
    }

    async function uploadImageFile(file) {
        if (!(file instanceof File)) {
            throw new Error('Selecciona una imagen valida.');
        }

        if (uploadUrl === '') {
            throw new Error('No encontramos la ruta segura para subir imagenes.');
        }

        if (!file.type.startsWith('image/')) {
            throw new Error('El archivo debe ser una imagen.');
        }

        if (file.size > 10 * 1024 * 1024) {
            throw new Error('La imagen debe pesar menos de 10 MB.');
        }

        const payload = new FormData();
        payload.append('action', 'upload_image');
        payload.append('image', file);

        const response = await fetch(uploadUrl, {
            method: 'POST',
            body: payload,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
            },
        });
        const body = await response.json().catch(() => null);

        if (!response.ok || !body?.success) {
            throw new Error(body?.message || 'No fue posible subir la imagen.');
        }

        const imageUrl = sanitizeImageValue(body.data?.url || '');

        if (imageUrl === '') {
            throw new Error('Supabase no regreso una URL valida para la imagen.');
        }

        return imageUrl;
    }

    async function handleImageUpload(target) {
        const file = target.files?.[0] ?? null;

        if (!(file instanceof File)) {
            return;
        }

        const blockField = target.dataset.landingUploadField;
        const blockId = target.dataset.landingUploadBlockId || selectedId;
        const settingKey = target.dataset.landingUploadSetting;

        uploadFeedback = { type: 'success', message: 'Subiendo imagen a Supabase Storage...' };
        renderInspector();

        try {
            const imageUrl = await uploadImageFile(file);

            if (settingKey) {
                settings[settingKey] = imageUrl;
            } else if (blockField) {
                updateBlockById(blockId, blockField, imageUrl);
            }

            uploadFeedback = { type: 'success', message: 'Imagen cargada correctamente.' };
            render();
        } catch (error) {
            uploadFeedback = { type: 'error', message: error instanceof Error ? error.message : 'No fue posible subir la imagen.' };
            renderInspector();
        }
    }

    function clearImageValue(target) {
        const blockField = target.dataset.landingClearImageField;
        const blockId = target.dataset.landingClearBlockId || selectedId;
        const settingKey = target.dataset.landingClearSetting;

        if (settingKey) {
            settings[settingKey] = '';
            render();
            return;
        }

        if (blockField) {
            updateBlockById(blockId, blockField, '');
        }
    }

    function isImageField(field) {
        return ['image_url', 'logo_url', 'og_image', 'background_image_url'].includes(String(field || ''));
    }

    function sanitizeImageValue(value) {
        const trimmed = String(value || '').trim();

        if (trimmed === '') {
            return '';
        }

        if (trimmed.toLowerCase().startsWith('data:') || !isManagedLandingAssetUrl(trimmed)) {
            return '';
        }

        return trimmed;
    }

    function isManagedLandingAssetUrl(value) {
        const normalized = String(value || '').toLowerCase();

        return storagePublicBase !== '' && normalized.startsWith(storagePublicBase);
    }

    function normalizeOpacity(value) {
        const numeric = Number.parseInt(String(value ?? '0'), 10);

        if (Number.isNaN(numeric)) {
            return 0;
        }

        return Math.max(0, Math.min(100, numeric));
    }

    function normalizeColor(value, fallback) {
        const candidate = String(value || '').trim();

        return /^#[0-9a-f]{3}([0-9a-f]{3})?$/i.test(candidate) ? candidate : fallback;
    }

    function escapeCssUrl(value) {
        return String(value || '')
            .replaceAll('\\', '\\\\')
            .replaceAll('"', '\\"')
            .replaceAll('\n', '')
            .replaceAll('\r', '');
    }

    root.addEventListener('click', (event) => {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (target.closest('[data-landing-preview-link]')) {
            event.preventDefault();
            return;
        }

        const clearImageButton = target.closest('[data-landing-clear-image-field], [data-landing-clear-setting]');

        if (clearImageButton instanceof HTMLElement) {
            event.preventDefault();
            clearImageValue(clearImageButton);
            return;
        }

        const inspectorTabButton = target.closest('[data-landing-inspector-tab]');

        if (inspectorTabButton instanceof HTMLElement) {
            inspectorTab = String(inspectorTabButton.dataset.landingInspectorTab || 'block');
            renderInspector();
            return;
        }

        const addButton = target.closest('[data-landing-add-block]');

        if (addButton instanceof HTMLElement) {
            const block = createBlock(String(addButton.dataset.landingAddBlock || 'hero'));
            blocks.push(block);
            selectedId = block.id;
            render();
            return;
        }

        const actionButton = target.closest('[data-landing-action]');

        if (actionButton instanceof HTMLElement) {
            event.preventDefault();
            const action = String(actionButton.dataset.landingAction || '');

            if (action === 'move-up') {
                moveSelected(-1);
            } else if (action === 'move-down') {
                moveSelected(1);
            } else if (action === 'duplicate') {
                duplicateSelected();
            } else if (action === 'delete') {
                deleteSelected();
            }

            return;
        }

        const blockElement = target.closest('[data-landing-block-id]');

        if (blockElement instanceof HTMLElement) {
            selectedId = String(blockElement.dataset.landingBlockId || '');
            inspectorTab = 'block';
            render();
        }
    });

    root.addEventListener('input', (event) => {
        const target = event.target;

        if (!(target instanceof HTMLInputElement) && !(target instanceof HTMLTextAreaElement)) {
            return;
        }

        const settingKey = target.dataset.landingSetting;
        const blockField = target.dataset.landingBlockField;

        if (settingKey) {
            settings[settingKey] = normalizeSettingValue(settingKey, target.value);
            renderCanvas();
        } else if (blockField) {
            updateSelectedBlock(blockField, target.value);
        }

        if (target === titleInput && docTitle instanceof HTMLElement) {
            docTitle.textContent = target.value.trim() || 'Landing sin titulo';
        }
    });

    root.addEventListener('change', (event) => {
        const target = event.target;

        if (target instanceof HTMLInputElement && target.type === 'file') {
            void handleImageUpload(target);
            return;
        }

        if (target instanceof HTMLSelectElement && target.dataset.landingBlockField) {
            updateSelectedBlock(target.dataset.landingBlockField, target.value);
        }
    });

    canvas.addEventListener('dragstart', (event) => {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        const block = target.closest('[data-landing-block-id]');

        if (!(block instanceof HTMLElement)) {
            return;
        }

        draggingId = String(block.dataset.landingBlockId || '');
        event.dataTransfer?.setData('text/plain', draggingId);
    });

    canvas.addEventListener('dragover', (event) => {
        if (draggingId !== '') {
            event.preventDefault();
        }
    });

    canvas.addEventListener('drop', (event) => {
        event.preventDefault();
        const target = event.target;
        const dropBlock = target instanceof HTMLElement ? target.closest('[data-landing-block-id]') : null;

        if (!(dropBlock instanceof HTMLElement) || draggingId === '') {
            draggingId = '';
            return;
        }

        const targetId = String(dropBlock.dataset.landingBlockId || '');
        const fromIndex = blocks.findIndex((block) => block.id === draggingId);
        const toIndex = blocks.findIndex((block) => block.id === targetId);

        if (fromIndex >= 0 && toIndex >= 0 && fromIndex !== toIndex) {
            const [block] = blocks.splice(fromIndex, 1);
            blocks.splice(toIndex, 0, block);
            selectedId = block.id;
            render();
        }

        draggingId = '';
    });

    form?.addEventListener('submit', syncState);
    render();
})();

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function escapeToken(value) {
    return String(value ?? '').replace(/[^a-z0-9_-]/gi, '') || 'block';
}
