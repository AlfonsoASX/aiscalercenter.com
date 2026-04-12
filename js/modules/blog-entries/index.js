import {
    STORAGE_SCOPES,
    USER_FILES_STORAGE_BUCKET,
    buildUserStoragePath,
    extractStoragePathFromUrl,
    getStorageBucketForScopedPath,
} from '../../shared/storage.js';

export const BLOG_ENTRIES_SECTION_ID = 'entradas-del-blog';

const BLOG_STORAGE_BUCKET = USER_FILES_STORAGE_BUCKET;
const BLOG_LEGACY_STORAGE_BUCKET = 'blog-images';

export function createBlogEntriesModule({
    supabase,
    getCurrentUser,
    getUserContext,
    showNotice,
    humanizeError,
}) {
    const state = {
        items: [],
        blocks: [],
        editingId: null,
        dragIndex: null,
        tableReady: null,
        loading: false,
        autoSlug: true,
        coverImage: '',
        coverImageFile: null,
        publishedAt: null,
    };

    function renderSection(item) {
        return `
            <div id="blog-entries-module" class="workspace-section-card">
                <div class="blog-module-header">
                    <div>
                        <h2>${item.section_title ?? item.label}</h2>
                        <p class="workspace-section-subtitle">
                            Administra las entradas del blog desde una tabla conectada a Supabase y un editor visual por bloques.
                        </p>
                    </div>

                    <button id="blog-entry-create" type="button" class="workspace-primary-button">
                        <span class="material-symbols-rounded">add</span>
                        <span>Nuevo articulo</span>
                    </button>
                </div>

                <div id="blog-module-notice" class="blog-inline-notice hidden"></div>
                <div id="blog-module-setup" class="blog-setup hidden"></div>

                <div class="blog-table-card">
                    <div class="blog-table-wrap">
                        <table class="blog-table">
                            <thead>
                                <tr>
                                    <th>Imagen</th>
                                    <th>Titulo</th>
                                    <th>Visitas</th>
                                    <th>Herramientas</th>
                                </tr>
                            </thead>
                            <tbody id="blog-entries-body">
                                <tr>
                                    <td colspan="4" class="blog-empty-state">Cargando articulos...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="blog-editor-shell" class="blog-editor-shell hidden">
                    <div class="blog-editor-backdrop" data-blog-close="true"></div>
                    <div class="blog-editor-panel">
                        <div class="blog-editor-header">
                            <div>
                                <h3 id="blog-editor-heading">Nuevo articulo</h3>
                                <p class="workspace-section-subtitle">Escribe, ordena bloques y publica desde un flujo simple.</p>
                            </div>

                            <button type="button" class="workspace-icon-button" data-blog-close="true" aria-label="Cerrar editor">
                                <span class="material-symbols-rounded">close</span>
                            </button>
                        </div>

                        <form id="blog-entry-form" class="blog-editor-form">
                            <div id="blog-editor-notice" class="blog-editor-notice hidden" role="alert" aria-live="assertive">
                                <div class="blog-editor-notice__content">
                                    <span class="material-symbols-rounded blog-editor-notice__icon">error</span>
                                    <p id="blog-editor-notice-message" class="blog-editor-notice__message"></p>
                                </div>

                                <button type="button" class="blog-editor-notice__button" data-blog-dismiss-editor-notice="true">
                                    Aceptar
                                </button>
                            </div>

                            <input id="blog-entry-id" name="id" type="hidden">

                            <div class="blog-editor-grid">
                                <div class="blog-editor-field blog-editor-field--full">
                                    <label for="blog-entry-title" class="blog-editor-label">Titulo</label>
                                    <input id="blog-entry-title" name="title" type="text" class="blog-editor-input" required placeholder="Escribe el titulo del articulo">
                                </div>

                                <div class="blog-editor-field">
                                    <label for="blog-entry-slug" class="blog-editor-label">Slug</label>
                                    <input id="blog-entry-slug" name="slug" type="text" class="blog-editor-input" required placeholder="mi-articulo">
                                </div>

                                <div class="blog-editor-field">
                                    <label for="blog-entry-status" class="blog-editor-label">Estado</label>
                                    <select id="blog-entry-status" name="status" class="blog-editor-select">
                                        <option value="draft">Borrador</option>
                                        <option value="published">Publicado</option>
                                    </select>
                                </div>

                                <div class="blog-editor-field blog-editor-field--full">
                                    <label for="blog-entry-excerpt" class="blog-editor-label">Resumen</label>
                                    <textarea id="blog-entry-excerpt" name="excerpt" class="blog-editor-textarea" placeholder="Introduce un resumen corto del articulo"></textarea>
                                </div>

                                <div class="blog-editor-field blog-editor-field--full">
                                    <label class="blog-editor-label">Imagen destacada</label>
                                    <div id="blog-cover-dropzone" class="blog-dropzone" data-blog-cover-dropzone="true" tabindex="-1">
                                        <input id="blog-cover-input" type="file" accept="image/*">
                                        <img id="blog-cover-preview" class="blog-dropzone-preview hidden" alt="">
                                        <p>Arrastra una imagen aqui o haz clic para seleccionarla.</p>
                                        <div class="blog-actions">
                                            <button id="blog-cover-select" type="button" class="blog-action-button">
                                                <span class="material-symbols-rounded">image</span>
                                                <span>Seleccionar imagen</span>
                                            </button>
                                            <button id="blog-cover-clear" type="button" class="blog-action-button blog-action-button--danger">
                                                <span class="material-symbols-rounded">delete</span>
                                                <span>Quitar imagen</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="blog-editor-field blog-editor-field--full">
                                <label class="blog-editor-label">Contenido del articulo</label>
                                <div class="blog-builder-toolbar">
                                    <button type="button" class="blog-action-button" data-blog-add-block="heading">
                                        <span class="material-symbols-rounded">title</span>
                                        <span>Titulo</span>
                                    </button>
                                    <button type="button" class="blog-action-button" data-blog-add-block="paragraph">
                                        <span class="material-symbols-rounded">article</span>
                                        <span>Texto</span>
                                    </button>
                                    <button type="button" class="blog-action-button" data-blog-add-block="image">
                                        <span class="material-symbols-rounded">image</span>
                                        <span>Imagen</span>
                                    </button>
                                </div>
                                <div id="blog-blocks" class="blog-blocks"></div>
                            </div>

                            <div class="blog-editor-footer">
                                <button type="button" class="blog-secondary-button" data-blog-close="true">Cancelar</button>
                                <button type="submit" class="workspace-primary-button">
                                    <span class="material-symbols-rounded">save</span>
                                    <span>Guardar articulo</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
    }

    function bind() {
        const section = document.getElementById('blog-entries-module');

        if (!section) {
            return;
        }

        const createButton = document.getElementById('blog-entry-create');
        const form = document.getElementById('blog-entry-form');
        const titleInput = document.getElementById('blog-entry-title');
        const slugInput = document.getElementById('blog-entry-slug');
        const blocksContainer = document.getElementById('blog-blocks');
        const coverInput = document.getElementById('blog-cover-input');
        const coverDropzone = document.getElementById('blog-cover-dropzone');
        const coverSelect = document.getElementById('blog-cover-select');
        const coverClear = document.getElementById('blog-cover-clear');
        const entriesBody = document.getElementById('blog-entries-body');

        if (createButton) {
            createButton.addEventListener('click', () => {
                openEditor();
            });
        }

        if (form) {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                void handleSubmit(form);
            });
            form.addEventListener('input', handleEditorFieldInteraction);
            form.addEventListener('change', handleEditorFieldInteraction);
            form.addEventListener('click', (event) => {
                const target = event.target;

                if (!(target instanceof HTMLElement)) {
                    return;
                }

                if (target.closest('[data-blog-dismiss-editor-notice="true"]')) {
                    clearEditorError();
                }
            });
        }

        if (titleInput && slugInput) {
            titleInput.addEventListener('input', () => {
                if (state.autoSlug) {
                    slugInput.value = slugify(titleInput.value);
                }
            });

            slugInput.addEventListener('input', () => {
                state.autoSlug = slugInput.value.trim() === '' || slugInput.value.trim() === slugify(titleInput.value);
            });
        }

        section.querySelectorAll('[data-blog-close="true"]').forEach((button) => {
            button.addEventListener('click', () => {
                closeEditor();
            });
        });

        section.querySelectorAll('[data-blog-add-block]').forEach((button) => {
            button.addEventListener('click', () => {
                addBlock(button.dataset.blogAddBlock);
            });
        });

        if (entriesBody) {
            entriesBody.addEventListener('click', (event) => {
                const actionButton = event.target.closest('[data-blog-action]');

                if (!actionButton) {
                    return;
                }

                event.preventDefault();
                const action = actionButton.dataset.blogAction;
                const entryId = actionButton.dataset.entryId;

                if (!entryId) {
                    return;
                }

                if (action === 'edit') {
                    openEditor(entryId);
                    return;
                }

                if (action === 'delete') {
                    void deleteEntry(entryId);
                }
            });
        }

        if (blocksContainer) {
            blocksContainer.addEventListener('input', (event) => {
                const target = event.target;

                if (!(target instanceof HTMLElement)) {
                    return;
                }

                const blockElement = target.closest('[data-blog-block-index]');
                const field = target.dataset.blogField;

                if (!blockElement || field === undefined) {
                    return;
                }

                const index = Number(blockElement.dataset.blogBlockIndex);

                if (Number.isNaN(index) || !state.blocks[index]) {
                    return;
                }

                state.blocks[index][field] = target.value;
            });

            blocksContainer.addEventListener('change', async (event) => {
                const target = event.target;

                if (!(target instanceof HTMLInputElement)) {
                    return;
                }

                if (target.dataset.blogUpload !== 'block-image') {
                    return;
                }

                const blockElement = target.closest('[data-blog-block-index]');
                const index = Number(blockElement?.dataset.blogBlockIndex ?? '');
                const file = target.files?.[0];

                if (!file || Number.isNaN(index) || !state.blocks[index]) {
                    return;
                }

                clearEditorError();
                setBlockDraftFile(index, file);
                renderBlocks();
            });

            blocksContainer.addEventListener('click', (event) => {
                const target = event.target;

                if (!(target instanceof HTMLElement)) {
                    return;
                }

                const removeButton = target.closest('[data-blog-remove-block]');

                if (removeButton) {
                    const index = Number(removeButton.dataset.blogRemoveBlock);

                    if (!Number.isNaN(index)) {
                        clearEditorError();
                        state.blocks.splice(index, 1);
                        renderBlocks();
                    }

                    return;
                }

                const chooseButton = target.closest('[data-blog-choose-image]');

                if (chooseButton) {
                    const blockElement = chooseButton.closest('[data-blog-block-index]');
                    const input = blockElement?.querySelector('input[data-blog-upload="block-image"]');

                    if (input instanceof HTMLInputElement) {
                        input.click();
                    }
                }
            });

            blocksContainer.addEventListener('dragstart', (event) => {
                const blockElement = event.target.closest('[data-blog-block-index]');

                if (!blockElement) {
                    return;
                }

                state.dragIndex = Number(blockElement.dataset.blogBlockIndex);
                blockElement.classList.add('is-dragging');
            });

            blocksContainer.addEventListener('dragend', (event) => {
                const blockElement = event.target.closest('[data-blog-block-index]');

                if (blockElement) {
                    blockElement.classList.remove('is-dragging');
                }

                state.dragIndex = null;
            });

            blocksContainer.addEventListener('dragover', (event) => {
                if (event.target.closest('[data-blog-image-dropzone]') || event.target.closest('[data-blog-block-index]')) {
                    event.preventDefault();
                }
            });

            blocksContainer.addEventListener('drop', async (event) => {
                const imageDropzone = event.target.closest('[data-blog-image-dropzone]');

                if (imageDropzone && event.dataTransfer?.files?.length) {
                    event.preventDefault();
                    const blockElement = imageDropzone.closest('[data-blog-block-index]');
                    const index = Number(blockElement?.dataset.blogBlockIndex ?? '');
                    const file = event.dataTransfer.files[0];

                    if (!file || Number.isNaN(index) || !state.blocks[index]) {
                        return;
                    }

                    clearEditorError();
                    setBlockDraftFile(index, file);
                    renderBlocks();
                    return;
                }

                const blockElement = event.target.closest('[data-blog-block-index]');

                if (!blockElement) {
                    return;
                }

                event.preventDefault();
                const dropIndex = Number(blockElement.dataset.blogBlockIndex);
                const dragIndex = state.dragIndex;

                if (dragIndex === null || Number.isNaN(dropIndex) || dragIndex === dropIndex) {
                    return;
                }

                const [movedBlock] = state.blocks.splice(dragIndex, 1);
                state.blocks.splice(dropIndex, 0, movedBlock);
                state.dragIndex = null;
                renderBlocks();
            });
        }

        if (coverSelect && coverInput) {
            coverSelect.addEventListener('click', () => {
                coverInput.click();
            });
        }

        if (coverClear) {
            coverClear.addEventListener('click', () => {
                clearEditorError();
                clearCoverDraft(coverInput);
            });
        }

        if (coverInput) {
            coverInput.addEventListener('change', () => {
                const file = coverInput.files?.[0];

                if (!file) {
                    return;
                }

                clearEditorError();
                setCoverDraftFile(file);
                renderCoverPreview();
            });
        }

        if (coverDropzone) {
            coverDropzone.addEventListener('dragover', (event) => {
                event.preventDefault();
                coverDropzone.classList.add('is-over');
            });

            coverDropzone.addEventListener('dragleave', () => {
                coverDropzone.classList.remove('is-over');
            });

            coverDropzone.addEventListener('drop', (event) => {
                event.preventDefault();
                coverDropzone.classList.remove('is-over');
                const file = event.dataTransfer?.files?.[0];

                if (!file) {
                    return;
                }

                clearEditorError();
                setCoverDraftFile(file);
                renderCoverPreview();
            });
        }

        renderCoverPreview();
        renderBlocks();
        void loadEntries();
    }

    async function loadEntries() {
        const entriesBody = document.getElementById('blog-entries-body');

        if (!entriesBody) {
            return;
        }

        state.loading = true;
        renderTable();

        const { data, error } = await supabase
            .from('blog_entries')
            .select('id, title, slug, cover_image_url, view_count, status, updated_at, excerpt, content_blocks, published_at, author_name')
            .order('updated_at', { ascending: false });

        state.loading = false;

        if (error) {
            state.items = [];
            state.tableReady = false;
            renderSetup(error);
            renderTable();
            return;
        }

        state.items = data ?? [];
        state.tableReady = true;
        renderSetup(null);
        renderTable();
    }

    function renderTable() {
        const entriesBody = document.getElementById('blog-entries-body');

        if (!entriesBody) {
            return;
        }

        if (state.loading) {
            entriesBody.innerHTML = `
                <tr>
                    <td colspan="4" class="blog-empty-state">Cargando articulos...</td>
                </tr>
            `;
            return;
        }

        if (state.tableReady === false) {
            entriesBody.innerHTML = `
                <tr>
                    <td colspan="4" class="blog-empty-state">La tabla del blog aun no esta lista en Supabase.</td>
                </tr>
            `;
            return;
        }

        if (state.items.length === 0) {
            entriesBody.innerHTML = `
                <tr>
                    <td colspan="4" class="blog-empty-state">Aun no hay articulos. Usa "Nuevo articulo" para crear el primero.</td>
                </tr>
            `;
            return;
        }

        entriesBody.innerHTML = state.items.map((entry) => {
            const statusLabel = entry.status === 'published' ? 'Publicado' : 'Borrador';
            const articleUrl = buildBlogEntryUrl(entry.slug);
            const viewAction = entry.status === 'published'
                ? `<a class="blog-action-button" href="${articleUrl}" target="_blank" rel="noopener noreferrer">
                        <span class="material-symbols-rounded">open_in_new</span>
                        <span>Ver en sitio Web</span>
                   </a>`
                : `<button type="button" class="blog-action-button" disabled>
                        <span class="material-symbols-rounded">open_in_new</span>
                        <span>Publica para ver</span>
                   </button>`;

            return `
                <tr>
                    <td>
                        <img class="blog-thumb" src="${entry.cover_image_url || 'img/miniaturas/7.png'}" alt="">
                    </td>
                    <td class="blog-title-cell">
                        <strong>${escapeHtml(entry.title)}</strong>
                        <span>${statusLabel}</span>
                    </td>
                    <td>${Number(entry.view_count ?? 0).toLocaleString('es-MX')}</td>
                    <td>
                        <div class="blog-actions">
                            <button type="button" class="blog-action-button" data-blog-action="edit" data-entry-id="${entry.id}">
                                <span class="material-symbols-rounded">edit</span>
                                <span>Editar</span>
                            </button>
                            <button type="button" class="blog-action-button blog-action-button--danger" data-blog-action="delete" data-entry-id="${entry.id}">
                                <span class="material-symbols-rounded">delete</span>
                                <span>Borrar</span>
                            </button>
                            ${viewAction}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function renderSetup(error) {
        const setup = document.getElementById('blog-module-setup');
        const notice = document.getElementById('blog-module-notice');

        if (notice) {
            notice.classList.add('hidden');
            notice.textContent = '';
        }

        if (!setup) {
            return;
        }

        if (!error) {
            setup.classList.add('hidden');
            setup.innerHTML = '';
            return;
        }

        setup.classList.remove('hidden');
        setup.innerHTML = `
            <strong>Falta preparar la tabla en Supabase.</strong>
            <p>Detecte que <code>blog_entries</code> aun no existe en tu proyecto. Ejecuta el archivo <code>supabase/blog_entries_schema.sql</code> en el SQL Editor de Supabase y vuelve a cargar este modulo.</p>
        `;
    }

    function openEditor(entryId = null) {
        const shell = document.getElementById('blog-editor-shell');
        const heading = document.getElementById('blog-editor-heading');
        const form = document.getElementById('blog-entry-form');
        const titleInput = document.getElementById('blog-entry-title');
        const slugInput = document.getElementById('blog-entry-slug');
        const excerptInput = document.getElementById('blog-entry-excerpt');
        const statusInput = document.getElementById('blog-entry-status');
        const idInput = document.getElementById('blog-entry-id');

        if (!shell || !form || !titleInput || !slugInput || !excerptInput || !statusInput || !idInput) {
            return;
        }

        releaseDraftUrls();

        const entry = entryId ? state.items.find((item) => item.id === entryId) ?? null : null;
        state.editingId = entry?.id ?? null;
        state.autoSlug = !entry;
        state.publishedAt = entry?.published_at ?? null;
        state.coverImage = entry?.cover_image_url ?? '';
        state.coverImageFile = null;
        state.blocks = Array.isArray(entry?.content_blocks)
            ? JSON.parse(JSON.stringify(entry.content_blocks))
            : [];

        heading.textContent = entry ? 'Editar articulo' : 'Nuevo articulo';
        idInput.value = entry?.id ?? '';
        titleInput.value = entry?.title ?? '';
        slugInput.value = entry?.slug ?? '';
        excerptInput.value = entry?.excerpt ?? '';
        statusInput.value = entry?.status ?? 'draft';

        renderCoverPreview();
        renderBlocks();
        clearEditorError();
        shell.classList.remove('hidden');
    }

    function closeEditor() {
        const shell = document.getElementById('blog-editor-shell');
        const form = document.getElementById('blog-entry-form');

        releaseDraftUrls();

        if (shell) {
            shell.classList.add('hidden');
        }

        if (form) {
            form.reset();
        }

        state.editingId = null;
        state.blocks = [];
        state.coverImage = '';
        state.coverImageFile = null;
        state.publishedAt = null;
        state.autoSlug = true;
        clearEditorError();
        renderCoverPreview();
        renderBlocks();
    }

    async function handleSubmit(form) {
        const submitButton = form.querySelector('button[type="submit"]');
        const title = form.title.value.trim();
        const slug = slugify(form.slug.value.trim());
        const excerpt = form.excerpt.value.trim();
        const status = form.status.value;
        const currentUser = getCurrentUser();
        const userContext = getUserContext();
        const currentEntry = state.editingId
            ? state.items.find((item) => item.id === state.editingId) ?? null
            : null;

        clearEditorError();

        if (!title) {
            presentEditorError(createEditorValidationError('Escribe el titulo del articulo.', {
                focusSelector: '#blog-entry-title',
                highlightSelector: '#blog-entry-title',
            }));
            return;
        }

        if (!slug) {
            presentEditorError(createEditorValidationError('Escribe un slug valido para el articulo.', {
                focusSelector: '#blog-entry-slug',
                highlightSelector: '#blog-entry-slug',
            }));
            return;
        }

        if (state.blocks.length === 0) {
            presentEditorError(createEditorValidationError('Agrega al menos un bloque al articulo.', {
                focusSelector: '[data-blog-add-block]',
                highlightSelector: '#blog-blocks',
            }));
            return;
        }

        setButtonBusy(submitButton, true, 'Guardando...');

        try {
            const coverImageUrl = await resolveCoverImageUrl({
                slug,
                currentUserId: currentUser?.id ?? 'guest',
            });
            const contentBlocks = await Promise.all(state.blocks.map((block, index) => {
                return resolveBlockForSave(block, {
                    slug,
                    index,
                    currentUserId: currentUser?.id ?? 'guest',
                });
            }));
            const payload = {
                title,
                slug,
                excerpt,
                cover_image_url: coverImageUrl,
                content_blocks: contentBlocks,
                status,
                author_user_id: currentUser?.id ?? null,
                author_name: userContext?.displayName ?? currentUser?.email ?? '',
                published_at: status === 'published'
                    ? (state.publishedAt ?? new Date().toISOString())
                    : null,
            };

            const query = state.editingId
                ? supabase.from('blog_entries').update(payload).eq('id', state.editingId)
                : supabase.from('blog_entries').insert(payload);

            const { data: savedEntry, error } = await query.select().single();

            if (error) {
                setButtonBusy(submitButton, false);
                presentEditorError(mapPersistenceError(error));
                return;
            }

            const previousPaths = collectManagedImagePaths(currentEntry);
            const nextPaths = collectManagedImagePaths(savedEntry ?? payload);
            const stalePaths = previousPaths.filter((path) => !nextPaths.includes(path));

            if (stalePaths.length > 0) {
                await removeStorageObjects(stalePaths);
            }

            setButtonBusy(submitButton, false);
            showModuleNotice('success', state.editingId ? 'Articulo actualizado.' : 'Articulo creado.');
            closeEditor();
            void loadEntries();
        } catch (error) {
            setButtonBusy(submitButton, false);
            presentEditorError(error);
        }
    }

    async function deleteEntry(entryId) {
        const entry = state.items.find((item) => item.id === entryId);

        if (!entry) {
            return;
        }

        const confirmed = window.confirm(`Vas a borrar "${entry.title}". Esta accion no se puede deshacer.`);

        if (!confirmed) {
            return;
        }

        const managedPaths = collectManagedImagePaths(entry);
        const { error } = await supabase.from('blog_entries').delete().eq('id', entryId);

        if (error) {
            showModuleNotice('error', humanizeError(error.message));
            return;
        }

        if (managedPaths.length > 0) {
            await removeStorageObjects(managedPaths);
        }

        showModuleNotice('success', 'Articulo eliminado.');
        void loadEntries();
    }

    function addBlock(type) {
        clearEditorError();
        state.blocks.push(createBlock(type));
        renderBlocks();
    }

    function createBlock(type) {
        const baseBlock = {
            id: crypto.randomUUID(),
            type,
        };

        if (type === 'heading') {
            return {
                ...baseBlock,
                content: '',
                level: 'h2',
            };
        }

        if (type === 'image') {
            return {
                ...baseBlock,
                src: '',
                alt: '',
                caption: '',
            };
        }

        return {
            ...baseBlock,
            content: '',
        };
    }

    function renderBlocks() {
        const blocksContainer = document.getElementById('blog-blocks');

        if (!blocksContainer) {
            return;
        }

        if (state.blocks.length === 0) {
            blocksContainer.innerHTML = `
                <div class="blog-empty-state">Aun no hay bloques. Empieza agregando titulo, texto o imagen.</div>
            `;
            return;
        }

        blocksContainer.innerHTML = state.blocks.map((block, index) => {
            if (block.type === 'heading') {
                return `
                    <div class="blog-block-card" data-blog-block-index="${index}" draggable="true">
                        <div class="blog-block-head">
                            <span class="blog-block-handle"><span class="material-symbols-rounded">drag_indicator</span>Encabezado</span>
                            <div class="blog-block-tools">
                                <button type="button" class="blog-action-button blog-action-button--danger" data-blog-remove-block="${index}">
                                    <span class="material-symbols-rounded">delete</span>
                                    <span>Quitar</span>
                                </button>
                            </div>
                        </div>
                        <div class="blog-editor-grid">
                            <div class="blog-editor-field">
                                <label class="blog-editor-label">Nivel</label>
                                <select class="blog-editor-select" data-blog-field="level">
                                    <option value="h2" ${block.level === 'h2' ? 'selected' : ''}>H2</option>
                                    <option value="h3" ${block.level === 'h3' ? 'selected' : ''}>H3</option>
                                </select>
                            </div>
                            <div class="blog-editor-field blog-editor-field--full">
                                <label class="blog-editor-label">Texto</label>
                                <input type="text" class="blog-editor-input" data-blog-field="content" value="${escapeHtml(block.content ?? '')}" placeholder="Escribe el encabezado">
                            </div>
                        </div>
                    </div>
                `;
            }

            if (block.type === 'image') {
                return `
                    <div class="blog-block-card" data-blog-block-index="${index}" draggable="true">
                        <div class="blog-block-head">
                            <span class="blog-block-handle"><span class="material-symbols-rounded">drag_indicator</span>Imagen</span>
                            <div class="blog-block-tools">
                                <button type="button" class="blog-action-button blog-action-button--danger" data-blog-remove-block="${index}">
                                    <span class="material-symbols-rounded">delete</span>
                                    <span>Quitar</span>
                                </button>
                            </div>
                        </div>
                        <div class="blog-dropzone" data-blog-image-dropzone="true" tabindex="-1">
                            <input type="file" accept="image/*" data-blog-upload="block-image">
                            <img class="blog-block-image-preview ${block.src ? '' : 'hidden'}" src="${block.src ?? ''}" alt="">
                            <p>Arrastra una imagen a este bloque o selecciona un archivo.</p>
                            <button type="button" class="blog-action-button" data-blog-choose-image="true">
                                <span class="material-symbols-rounded">image</span>
                                <span>Seleccionar imagen</span>
                            </button>
                        </div>
                        <div class="blog-editor-grid">
                            <div class="blog-editor-field">
                                <label class="blog-editor-label">Texto alternativo</label>
                                <input type="text" class="blog-editor-input" data-blog-field="alt" value="${escapeHtml(block.alt ?? '')}" placeholder="Describe la imagen">
                            </div>
                            <div class="blog-editor-field">
                                <label class="blog-editor-label">Pie de foto</label>
                                <input type="text" class="blog-editor-input" data-blog-field="caption" value="${escapeHtml(block.caption ?? '')}" placeholder="Pie de foto opcional">
                            </div>
                        </div>
                    </div>
                `;
            }

            return `
                <div class="blog-block-card" data-blog-block-index="${index}" draggable="true">
                    <div class="blog-block-head">
                        <span class="blog-block-handle"><span class="material-symbols-rounded">drag_indicator</span>Texto</span>
                        <div class="blog-block-tools">
                            <button type="button" class="blog-action-button blog-action-button--danger" data-blog-remove-block="${index}">
                                <span class="material-symbols-rounded">delete</span>
                                <span>Quitar</span>
                            </button>
                        </div>
                    </div>
                    <div class="blog-editor-field blog-editor-field--full">
                        <label class="blog-editor-label">Contenido</label>
                        <textarea class="blog-editor-textarea" data-blog-field="content" placeholder="Escribe el contenido del parrafo">${escapeHtml(block.content ?? '')}</textarea>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderCoverPreview() {
        const preview = document.getElementById('blog-cover-preview');
        const dropzone = document.getElementById('blog-cover-dropzone');

        if (!(preview instanceof HTMLImageElement) || !dropzone) {
            return;
        }

        if (state.coverImage) {
            preview.src = state.coverImage;
            preview.classList.remove('hidden');
        } else {
            preview.src = '';
            preview.classList.add('hidden');
        }
    }

    function handleEditorFieldInteraction(event) {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (target.closest('#blog-editor-shell')) {
            clearEditorError();
        }
    }

    function createEditorValidationError(message, details = {}) {
        const error = new Error(message);
        error.name = 'BlogEditorValidationError';
        error.blogEditor = {
            message,
            focusSelector: details.focusSelector ?? '',
            highlightSelector: details.highlightSelector ?? '',
        };
        return error;
    }

    function presentEditorError(error) {
        const normalized = normalizeEditorError(error);

        if (!normalized) {
            return;
        }

        clearEditorError();
        showEditorNotice(normalized.message);

        if (!normalized.blogEditor) {
            return;
        }

        const focusTarget = normalized.blogEditor.focusSelector
            ? document.querySelector(normalized.blogEditor.focusSelector)
            : null;
        const highlightTarget = normalized.blogEditor.highlightSelector
            ? document.querySelector(normalized.blogEditor.highlightSelector)
            : focusTarget;

        if (highlightTarget instanceof HTMLElement) {
            highlightTarget.classList.add('is-invalid');
            highlightTarget.setAttribute('aria-invalid', 'true');
        }

        const messageContainer = resolveEditorErrorContainer(focusTarget, highlightTarget);

        if (messageContainer) {
            const messageNode = document.createElement('p');
            messageNode.className = 'blog-field-error';
            messageNode.dataset.blogErrorMessage = 'true';
            messageNode.textContent = normalized.message;
            messageContainer.append(messageNode);
        }

        focusEditorTarget(focusTarget, highlightTarget);
    }

    function normalizeEditorError(error) {
        if (error?.blogEditor?.message) {
            return error;
        }

        if (error instanceof Error) {
            return createEditorValidationError(humanizeError(error.message));
        }

        if (typeof error === 'string') {
            return createEditorValidationError(humanizeError(error));
        }

        return createEditorValidationError('Ocurrio un problema al guardar el articulo.');
    }

    function mapPersistenceError(error) {
        const rawMessage = String(error?.message ?? '').toLowerCase();

        if (rawMessage.includes('duplicate key') && rawMessage.includes('slug')) {
            return createEditorValidationError('Ya existe un articulo con ese slug. Usa uno diferente.', {
                focusSelector: '#blog-entry-slug',
                highlightSelector: '#blog-entry-slug',
            });
        }

        return createEditorValidationError(humanizeError(String(error?.message ?? '')));
    }

    function showEditorNotice(message) {
        const notice = document.getElementById('blog-editor-notice');
        const noticeMessage = document.getElementById('blog-editor-notice-message');

        if (!notice || !noticeMessage) {
            showModuleNotice('error', message);
            return;
        }

        noticeMessage.textContent = message;
        notice.classList.remove('hidden');
    }

    function clearEditorError() {
        const shell = document.getElementById('blog-editor-shell');
        const notice = document.getElementById('blog-editor-notice');
        const noticeMessage = document.getElementById('blog-editor-notice-message');

        if (notice) {
            notice.classList.add('hidden');
        }

        if (noticeMessage) {
            noticeMessage.textContent = '';
        }

        if (!shell) {
            return;
        }

        shell.querySelectorAll('.is-invalid').forEach((element) => {
            if (element instanceof HTMLElement) {
                element.classList.remove('is-invalid');
                element.removeAttribute('aria-invalid');
            }
        });

        shell.querySelectorAll('[data-blog-error-message="true"]').forEach((element) => {
            element.remove();
        });
    }

    function resolveEditorErrorContainer(focusTarget, highlightTarget) {
        const directField = focusTarget instanceof HTMLElement
            ? focusTarget.closest('.blog-editor-field')
            : null;

        if (directField instanceof HTMLElement) {
            return directField;
        }

        const blockCard = highlightTarget instanceof HTMLElement
            ? highlightTarget.closest('.blog-block-card')
            : null;

        if (blockCard instanceof HTMLElement) {
            return blockCard;
        }

        if (highlightTarget instanceof HTMLElement) {
            return highlightTarget;
        }

        return null;
    }

    function focusEditorTarget(focusTarget, highlightTarget) {
        const target = focusTarget instanceof HTMLElement ? focusTarget : highlightTarget;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        target.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
            inline: 'nearest',
        });

        window.setTimeout(() => {
            if (typeof target.focus === 'function') {
                target.focus({ preventScroll: true });
            }
        }, 120);
    }

    function showModuleNotice(type, message) {
        const notice = document.getElementById('blog-module-notice');

        if (!notice) {
            showNotice(type, message);
            return;
        }

        const palettes = {
            success: 'blog-inline-notice border border-emerald-500/20 bg-emerald-500/10 text-emerald-900',
            error: 'blog-inline-notice border border-red-500/20 bg-red-500/10 text-red-900',
            info: 'blog-inline-notice border border-sky-500/20 bg-sky-500/10 text-sky-900',
        };

        notice.className = palettes[type] ?? palettes.info;
        notice.textContent = message;
        notice.classList.remove('hidden');
    }

    function buildBlogEntryUrl(slug) {
        return new URL(`/blog/${encodeURIComponent(slug)}`, window.location.origin).toString();
    }

    function clearCoverDraft(coverInput = null) {
        releaseObjectUrl(state.coverImage, state.coverImageFile);
        state.coverImage = '';
        state.coverImageFile = null;

        if (coverInput) {
            coverInput.value = '';
        }

        renderCoverPreview();
    }

    function setCoverDraftFile(file) {
        releaseObjectUrl(state.coverImage, state.coverImageFile);
        state.coverImage = URL.createObjectURL(file);
        state.coverImageFile = file;
    }

    function setBlockDraftFile(index, file) {
        const block = state.blocks[index];

        if (!block) {
            return;
        }

        releaseBlockDraft(block);
        block.src = URL.createObjectURL(file);
        block.pendingFile = file;
    }

    async function resolveCoverImageUrl({ slug, currentUserId }) {
        if (state.coverImageFile instanceof File) {
            return await uploadImageFile(state.coverImageFile, {
                slug,
                currentUserId,
                folder: 'cover',
            });
        }

        if (isInlineDataUrl(state.coverImage)) {
            return await uploadDataUrl(state.coverImage, {
                slug,
                currentUserId,
                folder: 'cover',
                fallbackName: 'cover',
            });
        }

        return state.coverImage ?? '';
    }

    async function resolveBlockForSave(block, { slug, index, currentUserId }) {
        if (block.type === 'heading') {
            const content = String(block.content ?? '').trim();

            if (!content) {
                throw createEditorValidationError('Escribe el texto del encabezado.', {
                    focusSelector: `[data-blog-block-index="${index}"] [data-blog-field="content"]`,
                    highlightSelector: `[data-blog-block-index="${index}"] [data-blog-field="content"]`,
                });
            }

            return {
                id: block.id ?? crypto.randomUUID(),
                type: 'heading',
                content,
                level: block.level === 'h3' ? 'h3' : 'h2',
            };
        }

        if (block.type === 'image') {
            let src = block.src ?? '';

            if (block.pendingFile instanceof File) {
                src = await uploadImageFile(block.pendingFile, {
                    slug,
                    currentUserId,
                    folder: `content/block-${index + 1}`,
                });
            } else if (isInlineDataUrl(src)) {
                src = await uploadDataUrl(src, {
                    slug,
                    currentUserId,
                    folder: `content/block-${index + 1}`,
                    fallbackName: `block-${index + 1}`,
                });
            }

            if (!String(src ?? '').trim()) {
                throw createEditorValidationError('Selecciona una imagen para este bloque.', {
                    focusSelector: `[data-blog-block-index="${index}"] [data-blog-image-dropzone="true"]`,
                    highlightSelector: `[data-blog-block-index="${index}"] [data-blog-image-dropzone="true"]`,
                });
            }

            return {
                id: block.id ?? crypto.randomUUID(),
                type: 'image',
                src,
                alt: block.alt ?? '',
                caption: block.caption ?? '',
            };
        }

        const content = String(block.content ?? '').trim();

        if (!content) {
            throw createEditorValidationError('Escribe el contenido de este bloque de texto.', {
                focusSelector: `[data-blog-block-index="${index}"] [data-blog-field="content"]`,
                highlightSelector: `[data-blog-block-index="${index}"] [data-blog-field="content"]`,
            });
        }

        return {
            id: block.id ?? crypto.randomUUID(),
            type: 'paragraph',
            content,
        };
    }

    async function uploadDataUrl(dataUrl, options) {
        const file = dataUrlToFile(dataUrl, options.fallbackName ?? 'image');
        return await uploadImageFile(file, options);
    }

    async function uploadImageFile(file, { slug, currentUserId, folder }) {
        const safeSlug = slugify(slug || 'articulo');
        const extension = getFileExtension(file.name, file.type);
        const filePath = buildUserStoragePath(
            currentUserId,
            STORAGE_SCOPES.blog,
            safeSlug,
            folder,
            `${crypto.randomUUID()}.${extension}`
        );
        const bucket = supabase.storage.from(BLOG_STORAGE_BUCKET);
        const { error } = await bucket.upload(filePath, file, {
            cacheControl: '3600',
            upsert: false,
            contentType: file.type || undefined,
        });

        if (error) {
            throw error;
        }

        const { data } = bucket.getPublicUrl(filePath);
        return data.publicUrl;
    }

    async function removeStorageObjects(paths) {
        if (paths.length === 0) {
            return;
        }

        const groupedPaths = groupStoragePaths(paths);

        for (const [bucket, bucketPaths] of groupedPaths.entries()) {
            const { error } = await supabase.storage.from(bucket).remove(bucketPaths);

            if (error) {
                console.error(error);
            }
        }
    }

    function collectManagedImagePaths(entry) {
        if (!entry) {
            return [];
        }

        const urls = [];

        if (entry.cover_image_url) {
            urls.push(entry.cover_image_url);
        }

        if (Array.isArray(entry.content_blocks)) {
            entry.content_blocks.forEach((block) => {
                if (block?.type === 'image' && block.src) {
                    urls.push(block.src);
                }
            });
        }

        return [...new Set(urls.map(extractManagedStoragePath).filter(Boolean))];
    }

    function extractManagedStoragePath(url) {
        if (!url || isInlineDataUrl(url)) {
            return null;
        }

        try {
            const extracted = extractStoragePathFromUrl(url, [BLOG_STORAGE_BUCKET, BLOG_LEGACY_STORAGE_BUCKET]);

            return extracted?.path ?? null;
        } catch (error) {
            return null;
        }
    }

    function groupStoragePaths(paths) {
        const grouped = new Map();

        paths.forEach((path) => {
            const bucket = getStorageBucketForScopedPath(path, STORAGE_SCOPES.blog, BLOG_LEGACY_STORAGE_BUCKET);
            const current = grouped.get(bucket) ?? [];
            current.push(path);
            grouped.set(bucket, current);
        });

        return grouped;
    }

    function dataUrlToFile(dataUrl, fallbackName) {
        const [meta, content] = String(dataUrl).split(',');

        if (!meta || !content) {
            throw new Error('No se pudo preparar la imagen para Supabase Storage.');
        }

        const mimeMatch = meta.match(/data:(.*?);base64/);
        const mimeType = mimeMatch?.[1] ?? 'image/png';
        const extension = getFileExtension(`${fallbackName}.${mimeType.split('/')[1] ?? 'png'}`, mimeType);
        const binary = atob(content);
        const bytes = new Uint8Array(binary.length);

        for (let index = 0; index < binary.length; index += 1) {
            bytes[index] = binary.charCodeAt(index);
        }

        return new File([bytes], `${fallbackName}.${extension}`, { type: mimeType });
    }

    function getFileExtension(fileName, mimeType = '') {
        const explicitExtension = String(fileName ?? '').split('.').pop()?.toLowerCase();

        if (explicitExtension && explicitExtension !== fileName.toLowerCase()) {
            return explicitExtension;
        }

        const mimeMap = {
            'image/jpeg': 'jpg',
            'image/png': 'png',
            'image/webp': 'webp',
            'image/gif': 'gif',
            'image/svg+xml': 'svg',
            'image/avif': 'avif',
        };

        return mimeMap[mimeType] ?? 'png';
    }

    function isInlineDataUrl(value) {
        return String(value ?? '').startsWith('data:image/');
    }

    function releaseDraftUrls() {
        releaseObjectUrl(state.coverImage, state.coverImageFile);

        state.blocks.forEach((block) => {
            releaseBlockDraft(block);
        });
    }

    function releaseBlockDraft(block) {
        if (!block) {
            return;
        }

        releaseObjectUrl(block.src, block.pendingFile);
        delete block.pendingFile;
    }

    function releaseObjectUrl(url, file) {
        if (!(file instanceof File) || !String(url ?? '').startsWith('blob:')) {
            return;
        }

        URL.revokeObjectURL(url);
    }

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function slugify(value) {
        return String(value)
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    function setButtonBusy(button, isBusy, loadingText = 'Procesando...') {
        if (!button) {
            return;
        }

        if (!button.dataset.defaultLabel) {
            button.dataset.defaultLabel = button.innerHTML;
        }

        button.disabled = isBusy;
        button.classList.toggle('opacity-70', isBusy);
        button.classList.toggle('cursor-not-allowed', isBusy);
        button.innerHTML = isBusy ? loadingText : button.dataset.defaultLabel;
    }

    return {
        sectionId: BLOG_ENTRIES_SECTION_ID,
        renderSection,
        bind,
    };
}
