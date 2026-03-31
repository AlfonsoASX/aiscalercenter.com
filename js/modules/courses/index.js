export const COURSES_SECTION_ID = 'cursos';

const COURSE_STORAGE_BUCKET = 'course-assets';
const ASSET_TYPES = new Set(['video', 'pdf', 'audio']);
const CONTENT_TYPE_META = {
    video: {
        label: 'Video',
        accept: 'video/*',
        help: 'Sube un video para esta leccion.',
    },
    article: {
        label: 'Articulo',
        accept: '',
        help: 'Escribe el contenido del articulo directamente.',
    },
    pdf: {
        label: 'PDF',
        accept: 'application/pdf',
        help: 'Sube el documento PDF para esta leccion.',
    },
    audio: {
        label: 'Audio',
        accept: 'audio/*',
        help: 'Sube el archivo de audio para esta leccion.',
    },
};

export function createCoursesModule({
    supabase,
    getCurrentUser,
    getUserContext,
    showNotice,
    humanizeError,
}) {
    const state = {
        items: [],
        sections: [],
        looseItems: [],
        editingId: null,
        coverImageUrl: '',
        coverStoragePath: '',
        coverImageFile: null,
        loading: false,
        tableReady: null,
        autoSlug: true,
        dragSectionIndex: null,
        dragItemOwner: null,
        dragItemIndex: null,
    };

    function renderSection(item) {
        return `
            <div id="courses-module" class="workspace-section-card">
                <div class="course-module-header">
                    <div>
                        <h2>${item.section_title ?? item.label}</h2>
                        <p class="workspace-section-subtitle">
                            Crea cursos con secciones opcionales y contenidos de tipo video, articulo, PDF o audio.
                        </p>
                    </div>

                    <button id="course-create" type="button" class="workspace-primary-button">
                        <span class="material-symbols-rounded">add</span>
                        <span>Nuevo curso</span>
                    </button>
                </div>

                <div id="courses-module-notice" class="course-inline-notice hidden"></div>
                <div id="courses-module-setup" class="course-setup hidden"></div>

                <div class="course-table-card">
                    <div class="course-table-wrap">
                        <table class="course-table">
                            <thead>
                                <tr>
                                    <th>Curso</th>
                                    <th>Secciones</th>
                                    <th>Contenidos</th>
                                    <th>Herramientas</th>
                                </tr>
                            </thead>
                            <tbody id="courses-body">
                                <tr>
                                    <td colspan="4" class="course-empty-state">Cargando cursos...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="course-editor-shell" class="course-editor-shell hidden">
                    <div class="course-editor-backdrop" data-course-close="true"></div>
                    <div class="course-editor-panel">
                        <div class="course-editor-header">
                            <div>
                                <h3 id="course-editor-heading">Nuevo curso</h3>
                                <p class="workspace-section-subtitle">
                                    Organiza el curso con secciones opcionales y arrastra para definir el orden final.
                                </p>
                            </div>

                            <button type="button" class="workspace-icon-button" data-course-close="true" aria-label="Cerrar editor de cursos">
                                <span class="material-symbols-rounded">close</span>
                            </button>
                        </div>

                        <form id="course-form" class="course-editor-form">
                            <div id="course-editor-notice" class="course-editor-notice hidden" role="alert" aria-live="assertive">
                                <div class="course-editor-notice__content">
                                    <span class="material-symbols-rounded course-editor-notice__icon">error</span>
                                    <p id="course-editor-notice-message" class="course-editor-notice__message"></p>
                                </div>

                                <button type="button" class="course-editor-notice__button" data-course-dismiss-editor-notice="true">
                                    Aceptar
                                </button>
                            </div>

                            <input id="course-id" name="id" type="hidden">

                            <div class="course-editor-grid">
                                <div class="course-editor-field course-editor-field--full">
                                    <label for="course-title" class="course-editor-label">Titulo</label>
                                    <input id="course-title" name="title" type="text" class="course-editor-input" required placeholder="Nombre del curso">
                                </div>

                                <div class="course-editor-field">
                                    <label for="course-slug" class="course-editor-label">Slug</label>
                                    <input id="course-slug" name="slug" type="text" class="course-editor-input" required placeholder="curso-de-ejemplo">
                                </div>

                                <div class="course-editor-field">
                                    <label for="course-status" class="course-editor-label">Estado</label>
                                    <select id="course-status" name="status" class="course-editor-select">
                                        <option value="draft">Borrador</option>
                                        <option value="published">Publicado</option>
                                    </select>
                                </div>

                                <div class="course-editor-field course-editor-field--full">
                                    <label for="course-description" class="course-editor-label">Descripcion</label>
                                    <textarea id="course-description" name="description" class="course-editor-textarea" placeholder="Describe brevemente el curso"></textarea>
                                </div>

                                <div class="course-editor-field course-editor-field--full">
                                    <label class="course-editor-label">Imagen de portada</label>
                                    <div id="course-cover-dropzone" class="course-file-dropzone course-file-dropzone--cover" data-course-cover-dropzone="true" tabindex="-1">
                                        <input id="course-cover-input" type="file" accept="image/*">
                                        <img id="course-cover-preview" class="course-cover-preview hidden" alt="">
                                        <p>Sube una imagen que represente el curso. Esta portada se mostrara en Aprender.</p>
                                        <div class="course-actions">
                                            <button id="course-cover-select" type="button" class="course-action-button">
                                                <span class="material-symbols-rounded">image</span>
                                                <span>Seleccionar imagen</span>
                                            </button>
                                            <button id="course-cover-clear" type="button" class="course-action-button course-action-button--danger">
                                                <span class="material-symbols-rounded">delete</span>
                                                <span>Quitar portada</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="course-builder-shell" class="course-builder-shell">
                                <div class="course-builder-zone">
                                    <div class="course-builder-head">
                                        <div>
                                            <h4>Contenido sin seccion</h4>
                                            <p>Usa esta zona si el curso no necesita secciones.</p>
                                        </div>
                                    </div>

                                    <div class="course-builder-toolbar">
                                        ${renderAddItemButtons({ type: 'loose' })}
                                    </div>

                                    <div id="course-loose-items-zone"></div>
                                </div>

                                <div class="course-builder-zone">
                                    <div class="course-builder-head">
                                        <div>
                                            <h4>Secciones del curso</h4>
                                            <p>Las secciones son opcionales, pero si existe una, debe tener contenido.</p>
                                        </div>

                                        <button type="button" class="course-action-button" data-course-add-section="true">
                                            <span class="material-symbols-rounded">add</span>
                                            <span>Agregar seccion</span>
                                        </button>
                                    </div>

                                    <div id="course-sections-zone"></div>
                                </div>
                            </div>

                            <div class="course-editor-footer">
                                <button type="button" class="course-secondary-button" data-course-close="true">Cancelar</button>
                                <button type="submit" class="workspace-primary-button">
                                    <span class="material-symbols-rounded">save</span>
                                    <span>Guardar curso</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
    }

    function bind() {
        const section = document.getElementById('courses-module');

        if (!section) {
            return;
        }

        const createButton = document.getElementById('course-create');
        const form = document.getElementById('course-form');
        const titleInput = document.getElementById('course-title');
        const slugInput = document.getElementById('course-slug');
        const builderShell = document.getElementById('course-builder-shell');
        const body = document.getElementById('courses-body');
        const coverInput = document.getElementById('course-cover-input');
        const coverDropzone = document.getElementById('course-cover-dropzone');
        const coverSelect = document.getElementById('course-cover-select');
        const coverClear = document.getElementById('course-cover-clear');

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

                if (target.closest('[data-course-dismiss-editor-notice="true"]')) {
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

        section.querySelectorAll('[data-course-close="true"]').forEach((button) => {
            button.addEventListener('click', () => {
                closeEditor();
            });
        });

        if (body) {
            body.addEventListener('click', (event) => {
                const actionButton = event.target.closest('[data-course-action]');

                if (!actionButton) {
                    return;
                }

                event.preventDefault();
                const action = actionButton.dataset.courseAction;
                const courseId = actionButton.dataset.courseId;

                if (!courseId) {
                    return;
                }

                if (action === 'edit') {
                    openEditor(courseId);
                    return;
                }

                if (action === 'delete') {
                    void deleteCourse(courseId);
                }
            });
        }

        if (builderShell) {
            builderShell.addEventListener('input', handleBuilderInput);
            builderShell.addEventListener('click', handleBuilderClick);
            builderShell.addEventListener('change', (event) => {
                void handleBuilderChange(event);
            });
            builderShell.addEventListener('dragstart', handleBuilderDragStart);
            builderShell.addEventListener('dragend', handleBuilderDragEnd);
            builderShell.addEventListener('dragover', handleBuilderDragOver);
            builderShell.addEventListener('drop', handleBuilderDrop);
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
        renderBuilder();
        void loadCourses();
    }

    async function loadCourses() {
        const body = document.getElementById('courses-body');

        if (!body) {
            return;
        }

        state.loading = true;
        renderTable();

        const { data, error } = await supabase
            .from('courses')
            .select('id, title, slug, description, cover_image_url, cover_storage_path, status, sections, loose_items, updated_at')
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
        const body = document.getElementById('courses-body');

        if (!body) {
            return;
        }

        if (state.loading) {
            body.innerHTML = `
                <tr>
                    <td colspan="4" class="course-empty-state">Cargando cursos...</td>
                </tr>
            `;
            return;
        }

        if (state.tableReady === false) {
            body.innerHTML = `
                <tr>
                    <td colspan="4" class="course-empty-state">La tabla de cursos aun no esta lista en Supabase.</td>
                </tr>
            `;
            return;
        }

        if (state.items.length === 0) {
            body.innerHTML = `
                <tr>
                    <td colspan="4" class="course-empty-state">Todavia no hay cursos. Usa "Nuevo curso" para crear el primero.</td>
                </tr>
            `;
            return;
        }

        body.innerHTML = state.items.map((course) => {
            const sectionsCount = Array.isArray(course.sections) ? course.sections.length : 0;
            const itemsCount = countTotalItems(course.sections, course.loose_items);
            const statusLabel = course.status === 'published' ? 'Publicado' : 'Borrador';

            return `
                <tr>
                    <td class="course-title-cell">
                        <strong>${escapeHtml(course.title ?? '')}</strong>
                        <span>${statusLabel}</span>
                    </td>
                    <td>${sectionsCount}</td>
                    <td>${itemsCount}</td>
                    <td>
                        <div class="course-actions">
                            <button type="button" class="course-action-button" data-course-action="edit" data-course-id="${course.id}">
                                <span class="material-symbols-rounded">edit</span>
                                <span>Editar</span>
                            </button>
                            <button type="button" class="course-action-button course-action-button--danger" data-course-action="delete" data-course-id="${course.id}">
                                <span class="material-symbols-rounded">delete</span>
                                <span>Borrar</span>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function renderSetup(error) {
        const setup = document.getElementById('courses-module-setup');
        const notice = document.getElementById('courses-module-notice');

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
            <p>Detecte que <code>courses</code> aun no existe en tu proyecto. Ejecuta el archivo <code>supabase/courses_schema.sql</code> en el SQL Editor de Supabase y vuelve a cargar este modulo.</p>
        `;
    }

    function renderCoverPreview() {
        const preview = document.getElementById('course-cover-preview');
        const dropzone = document.getElementById('course-cover-dropzone');

        if (!(preview instanceof HTMLImageElement) || !dropzone) {
            return;
        }

        if (state.coverImageUrl) {
            preview.src = state.coverImageUrl;
            preview.classList.remove('hidden');
        } else {
            preview.src = '';
            preview.classList.add('hidden');
        }
    }

    async function hydrateCoverPreview() {
        if (!state.coverStoragePath || state.coverImageFile instanceof File) {
            return;
        }

        try {
            state.coverImageUrl = await createCoverPreviewUrl(state.coverStoragePath, state.coverImageUrl);
        } catch (error) {
            console.error(error);
        } finally {
            renderCoverPreview();
        }
    }

    async function createCoverPreviewUrl(storagePath, fallbackUrl = '') {
        const normalizedPath = String(storagePath ?? '').trim() || extractManagedStoragePath(fallbackUrl);

        if (!normalizedPath) {
            return String(fallbackUrl ?? '').trim();
        }

        const { data, error } = await supabase.storage
            .from(COURSE_STORAGE_BUCKET)
            .createSignedUrl(normalizedPath, 3600);

        if (error) {
            throw error;
        }

        return normalizeSignedUrl(data?.signedUrl ?? fallbackUrl);
    }

    function clearCoverDraft(coverInput = null) {
        releaseObjectUrl(state.coverImageUrl, state.coverImageFile);
        state.coverImageUrl = '';
        state.coverStoragePath = '';
        state.coverImageFile = null;

        if (coverInput) {
            coverInput.value = '';
        }

        renderCoverPreview();
    }

    function setCoverDraftFile(file) {
        releaseObjectUrl(state.coverImageUrl, state.coverImageFile);
        state.coverImageUrl = URL.createObjectURL(file);
        state.coverStoragePath = '';
        state.coverImageFile = file;
    }

    function openEditor(courseId = null) {
        const shell = document.getElementById('course-editor-shell');
        const heading = document.getElementById('course-editor-heading');
        const form = document.getElementById('course-form');
        const titleInput = document.getElementById('course-title');
        const slugInput = document.getElementById('course-slug');
        const descriptionInput = document.getElementById('course-description');
        const statusInput = document.getElementById('course-status');
        const idInput = document.getElementById('course-id');
        const coverInput = document.getElementById('course-cover-input');

        if (!shell || !form || !titleInput || !slugInput || !descriptionInput || !statusInput || !idInput) {
            return;
        }

        const course = courseId ? state.items.find((item) => item.id === courseId) ?? null : null;

        releaseObjectUrl(state.coverImageUrl, state.coverImageFile);
        state.editingId = course?.id ?? null;
        state.autoSlug = !course;
        state.coverStoragePath = String(course?.cover_storage_path ?? '').trim() || extractManagedStoragePath(course?.cover_image_url);
        state.coverImageUrl = String(course?.cover_image_url ?? '').trim();
        state.coverImageFile = null;
        state.sections = normalizeSections(course?.sections);
        state.looseItems = normalizeItems(course?.loose_items);
        state.dragSectionIndex = null;
        state.dragItemOwner = null;
        state.dragItemIndex = null;

        heading.textContent = course ? 'Editar curso' : 'Nuevo curso';
        idInput.value = course?.id ?? '';
        titleInput.value = course?.title ?? '';
        slugInput.value = course?.slug ?? '';
        descriptionInput.value = course?.description ?? '';
        statusInput.value = course?.status ?? 'draft';

        if (coverInput instanceof HTMLInputElement) {
            coverInput.value = '';
        }

        void hydrateCoverPreview();
        renderCoverPreview();
        renderBuilder();
        clearEditorError();
        shell.classList.remove('hidden');
    }

    function closeEditor() {
        const shell = document.getElementById('course-editor-shell');
        const form = document.getElementById('course-form');

        if (shell) {
            shell.classList.add('hidden');
        }

        if (form) {
            form.reset();
        }

        clearCoverDraft();
        state.editingId = null;
        state.sections = [];
        state.looseItems = [];
        state.autoSlug = true;
        state.dragSectionIndex = null;
        state.dragItemOwner = null;
        state.dragItemIndex = null;
        clearEditorError();
        renderBuilder();
    }

    async function handleSubmit(form) {
        const submitButton = form.querySelector('button[type="submit"]');
        const title = form.title.value.trim();
        const slug = slugify(form.slug.value.trim());
        const description = form.description.value.trim();
        const status = form.status.value;
        const currentUser = getCurrentUser();
        const userContext = getUserContext();
        const currentCourse = state.editingId
            ? state.items.find((item) => item.id === state.editingId) ?? null
            : null;

        clearEditorError();

        if (!title) {
            presentEditorError(createEditorValidationError('Escribe el titulo del curso.', {
                focusSelector: '#course-title',
                highlightSelector: '#course-title',
            }));
            return;
        }

        if (!slug) {
            presentEditorError(createEditorValidationError('Escribe un slug valido para el curso.', {
                focusSelector: '#course-slug',
                highlightSelector: '#course-slug',
            }));
            return;
        }

        if (countTotalItems(state.sections, state.looseItems) === 0) {
            presentEditorError(createEditorValidationError('Agrega al menos un contenido al curso.', {
                focusSelector: '[data-course-add-loose-item]',
                highlightSelector: '#course-builder-shell',
            }));
            return;
        }

        setButtonBusy(submitButton, true, 'Guardando...');

        try {
            const cover = await resolveCoverImage({
                slug,
                currentUserId: currentUser?.id ?? 'guest',
            });
            const looseItems = await Promise.all(state.looseItems.map((item, index) => {
                return resolveItemForSave(item, {
                    slug,
                    currentUserId: currentUser?.id ?? 'guest',
                    scope: `loose/item-${index + 1}`,
                    owner: 'loose',
                    itemIndex: index,
                });
            }));
            const sections = await Promise.all(state.sections.map((section, index) => {
                return resolveSectionForSave(section, {
                    slug,
                    currentUserId: currentUser?.id ?? 'guest',
                    sectionIndex: index,
                });
            }));
            const payload = {
                title,
                slug,
                description,
                cover_image_url: cover.url,
                cover_storage_path: cover.storagePath,
                status,
                sections,
                loose_items: looseItems,
                author_user_id: currentUser?.id ?? null,
                author_name: userContext?.displayName ?? currentUser?.email ?? '',
            };

            const query = state.editingId
                ? supabase.from('courses').update(payload).eq('id', state.editingId)
                : supabase.from('courses').insert(payload);

            const { data: savedCourse, error } = await query.select().single();

            if (error) {
                setButtonBusy(submitButton, false);
                presentEditorError(mapPersistenceError(error));
                return;
            }

            const previousPaths = collectManagedAssetPaths(currentCourse);
            const nextPaths = collectManagedAssetPaths(savedCourse ?? payload);
            const stalePaths = previousPaths.filter((path) => !nextPaths.includes(path));

            if (stalePaths.length > 0) {
                await removeStorageObjects(stalePaths);
            }

            setButtonBusy(submitButton, false);
            showModuleNotice('success', state.editingId ? 'Curso actualizado.' : 'Curso creado.');
            closeEditor();
            void loadCourses();
        } catch (error) {
            setButtonBusy(submitButton, false);
            presentEditorError(error);
        }
    }

    async function deleteCourse(courseId) {
        const course = state.items.find((item) => item.id === courseId);

        if (!course) {
            return;
        }

        const confirmed = window.confirm(`Vas a borrar "${course.title}". Esta accion no se puede deshacer.`);

        if (!confirmed) {
            return;
        }

        const managedPaths = collectManagedAssetPaths(course);
        const { error } = await supabase.from('courses').delete().eq('id', courseId);

        if (error) {
            showModuleNotice('error', humanizeError(error.message));
            return;
        }

        if (managedPaths.length > 0) {
            await removeStorageObjects(managedPaths);
        }

        showModuleNotice('success', 'Curso eliminado.');
        void loadCourses();
    }

    function renderBuilder() {
        const looseZone = document.getElementById('course-loose-items-zone');
        const sectionsZone = document.getElementById('course-sections-zone');

        if (looseZone) {
            looseZone.innerHTML = `
                <div class="course-items-list" data-course-items-list="loose">
                    ${renderItemsList(state.looseItems, 'loose')}
                </div>
            `;
        }

        if (sectionsZone) {
            sectionsZone.innerHTML = state.sections.length === 0
                ? `
                    <div class="course-builder-empty">
                        Aun no has creado secciones. Puedes trabajar solo con contenido sin seccion o agregar una nueva.
                    </div>
                `
                : state.sections.map((section, index) => renderSectionCard(section, index)).join('');
        }
    }

    function renderSectionCard(section, index) {
        return `
            <div class="course-section-card" draggable="true" data-course-section-card="true" data-section-index="${index}">
                <div class="course-section-head">
                    <span class="course-drag-handle">
                        <span class="material-symbols-rounded">drag_indicator</span>
                        Seccion ${index + 1}
                    </span>
                    <button type="button" class="course-action-button course-action-button--danger" data-course-remove-section="${index}">
                        <span class="material-symbols-rounded">delete</span>
                        <span>Quitar seccion</span>
                    </button>
                </div>

                <div class="course-editor-grid">
                    <div class="course-editor-field">
                        <label class="course-editor-label">Titulo de la seccion</label>
                        <input
                            type="text"
                            class="course-editor-input"
                            value="${escapeHtml(section.title ?? '')}"
                            data-course-section-field="title"
                            data-section-index="${index}"
                            placeholder="Por ejemplo: Modulo 1"
                        >
                    </div>

                    <div class="course-editor-field">
                        <label class="course-editor-label">Descripcion corta</label>
                        <input
                            type="text"
                            class="course-editor-input"
                            value="${escapeHtml(section.description ?? '')}"
                            data-course-section-field="description"
                            data-section-index="${index}"
                            placeholder="Objetivo de esta seccion"
                        >
                    </div>
                </div>

                <div class="course-builder-toolbar">
                    ${renderAddItemButtons({ type: 'section', sectionIndex: index })}
                </div>

                <div class="course-items-list" data-course-items-list="section:${index}">
                    ${renderItemsList(section.items, `section:${index}`)}
                </div>
            </div>
        `;
    }

    function renderItemsList(items, owner) {
        if (!Array.isArray(items) || items.length === 0) {
            return `
                <div class="course-builder-empty">
                    Todavia no hay contenidos en esta zona.
                </div>
            `;
        }

        return items.map((item, index) => renderItemCard(item, owner, index)).join('');
    }

    function renderItemCard(item, owner, index) {
        const meta = CONTENT_TYPE_META[item.type] ?? CONTENT_TYPE_META.article;
        const fileLabel = getItemFileLabel(item);
        const hasFile = Boolean(item.pendingFile instanceof File || item.storage_path || item.file_url);
        const canOpenCurrentFile = hasFile && !(item.pendingFile instanceof File);
        const currentLink = canOpenCurrentFile
            ? `
                <button type="button" class="course-action-button" data-course-open-file="true" data-owner="${owner}" data-item-index="${index}">
                    <span class="material-symbols-rounded">open_in_new</span>
                    <span>Abrir archivo</span>
                </button>
            `
            : '';

        return `
            <div class="course-item-card" draggable="true" data-course-item-card="true" data-owner="${owner}" data-item-index="${index}">
                <div class="course-item-head">
                    <div class="course-item-meta">
                        <span class="course-drag-handle">
                            <span class="material-symbols-rounded">drag_indicator</span>
                            Arrastrar
                        </span>
                        <span class="course-type-badge">${meta.label}</span>
                    </div>

                    <button type="button" class="course-action-button course-action-button--danger" data-course-remove-item="true" data-owner="${owner}" data-item-index="${index}">
                        <span class="material-symbols-rounded">delete</span>
                        <span>Quitar</span>
                    </button>
                </div>

                <div class="course-editor-grid">
                    <div class="course-editor-field">
                        <label class="course-editor-label">Titulo del contenido</label>
                        <input
                            type="text"
                            class="course-editor-input"
                            value="${escapeHtml(item.title ?? '')}"
                            data-course-item-field="title"
                            data-owner="${owner}"
                            data-item-index="${index}"
                            placeholder="Nombre de la leccion"
                        >
                    </div>

                    <div class="course-editor-field">
                        <label class="course-editor-label">Descripcion</label>
                        <input
                            type="text"
                            class="course-editor-input"
                            value="${escapeHtml(item.description ?? '')}"
                            data-course-item-field="description"
                            data-owner="${owner}"
                            data-item-index="${index}"
                            placeholder="Explica de que trata"
                        >
                    </div>

                    ${item.type === 'article'
                        ? `
                            <div class="course-editor-field course-editor-field--full">
                                <label class="course-editor-label">Contenido del articulo</label>
                                <textarea
                                    class="course-editor-textarea"
                                    data-course-item-field="body"
                                    data-owner="${owner}"
                                    data-item-index="${index}"
                                    placeholder="Escribe aqui el articulo completo"
                                >${escapeHtml(item.body ?? '')}</textarea>
                            </div>
                        `
                        : `
                            <div class="course-editor-field course-editor-field--full">
                                <label class="course-editor-label">Archivo</label>
                                <div class="course-file-dropzone" data-course-item-file-zone="true" data-owner="${owner}" data-item-index="${index}" tabindex="-1">
                                    <input
                                        type="file"
                                        accept="${meta.accept}"
                                        data-course-item-upload="true"
                                        data-owner="${owner}"
                                        data-item-index="${index}"
                                    >
                                    <p>${meta.help}</p>
                                    <div class="course-file-chip ${hasFile ? '' : 'hidden'}">${escapeHtml(fileLabel)}</div>
                                    <div class="course-actions">
                                        <button type="button" class="course-action-button" data-course-choose-file="true" data-owner="${owner}" data-item-index="${index}">
                                            <span class="material-symbols-rounded">upload</span>
                                            <span>Seleccionar archivo</span>
                                        </button>
                                        <button type="button" class="course-action-button course-action-button--danger" data-course-clear-file="true" data-owner="${owner}" data-item-index="${index}">
                                            <span class="material-symbols-rounded">delete</span>
                                            <span>Quitar archivo</span>
                                        </button>
                                        ${currentLink}
                                    </div>
                                </div>
                            </div>
                        `}
                </div>
            </div>
        `;
    }

    function renderAddItemButtons({ type, sectionIndex = null }) {
        const items = Object.entries(CONTENT_TYPE_META).map(([contentType, meta]) => {
            const baseAttributes = type === 'loose'
                ? `data-course-add-loose-item="${contentType}"`
                : `data-course-add-section-item="${contentType}" data-section-index="${sectionIndex}"`;

            return `
                <button type="button" class="course-action-button" ${baseAttributes}>
                    <span class="material-symbols-rounded">${getIconForContentType(contentType)}</span>
                    <span>${meta.label}</span>
                </button>
            `;
        });

        return items.join('');
    }

    function handleBuilderInput(event) {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        const sectionField = target.dataset.courseSectionField;

        if (sectionField !== undefined) {
            const sectionIndex = Number(target.dataset.sectionIndex ?? '');

            if (Number.isNaN(sectionIndex) || !state.sections[sectionIndex]) {
                return;
            }

            state.sections[sectionIndex][sectionField] = target.value;
            return;
        }

        const itemField = target.dataset.courseItemField;

        if (itemField !== undefined) {
            const item = getItemByOwner(target.dataset.owner ?? '', Number(target.dataset.itemIndex ?? ''));

            if (!item) {
                return;
            }

            item[itemField] = target.value;
        }
    }

    function handleBuilderClick(event) {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        const addSection = target.closest('[data-course-add-section]');

        if (addSection) {
            clearEditorError();
            state.sections.push(createSection());
            renderBuilder();
            return;
        }

        const addLooseItem = target.closest('[data-course-add-loose-item]');

        if (addLooseItem) {
            clearEditorError();
            state.looseItems.push(createItem(addLooseItem.dataset.courseAddLooseItem));
            renderBuilder();
            return;
        }

        const addSectionItem = target.closest('[data-course-add-section-item]');

        if (addSectionItem) {
            const sectionIndex = Number(addSectionItem.dataset.sectionIndex ?? '');

            if (!Number.isNaN(sectionIndex) && state.sections[sectionIndex]) {
                clearEditorError();
                state.sections[sectionIndex].items.push(createItem(addSectionItem.dataset.courseAddSectionItem));
                renderBuilder();
            }

            return;
        }

        const removeSection = target.closest('[data-course-remove-section]');

        if (removeSection) {
            const sectionIndex = Number(removeSection.dataset.courseRemoveSection ?? '');

            if (!Number.isNaN(sectionIndex)) {
                clearEditorError();
                state.sections.splice(sectionIndex, 1);
                renderBuilder();
            }

            return;
        }

        const removeItem = target.closest('[data-course-remove-item]');

        if (removeItem) {
            const owner = removeItem.dataset.owner ?? '';
            const itemIndex = Number(removeItem.dataset.itemIndex ?? '');
            const items = getItemsByOwner(owner);

            if (items && !Number.isNaN(itemIndex)) {
                clearEditorError();
                items.splice(itemIndex, 1);
                renderBuilder();
            }

            return;
        }

        const chooseFile = target.closest('[data-course-choose-file]');

        if (chooseFile) {
            const itemCard = chooseFile.closest('[data-course-item-card]');
            const input = itemCard?.querySelector('input[data-course-item-upload="true"]');

            if (input instanceof HTMLInputElement) {
                input.click();
            }

            return;
        }

        const clearFile = target.closest('[data-course-clear-file]');

        if (clearFile) {
            const item = getItemByOwner(clearFile.dataset.owner ?? '', Number(clearFile.dataset.itemIndex ?? ''));

            if (item) {
                clearEditorError();
                item.pendingFile = null;
                item.storage_path = '';
                item.file_url = '';
                item.file_name = '';
                renderBuilder();
            }

            return;
        }

        const openFile = target.closest('[data-course-open-file]');

        if (openFile) {
            const item = getItemByOwner(openFile.dataset.owner ?? '', Number(openFile.dataset.itemIndex ?? ''));

            if (item) {
                void openCourseAsset(item, openFile);
            }
        }
    }

    async function handleBuilderChange(event) {
        const target = event.target;

        if (!(target instanceof HTMLInputElement) || target.dataset.courseItemUpload !== 'true') {
            return;
        }

        const item = getItemByOwner(target.dataset.owner ?? '', Number(target.dataset.itemIndex ?? ''));
        const file = target.files?.[0];

        if (!item || !file) {
            return;
        }

        clearEditorError();
        item.pendingFile = file;
        item.file_name = file.name;
        renderBuilder();
    }

    function handleBuilderDragStart(event) {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        const itemCard = target.closest('[data-course-item-card]');

        if (itemCard) {
            state.dragItemOwner = itemCard.dataset.owner ?? null;
            state.dragItemIndex = Number(itemCard.dataset.itemIndex ?? '');
            itemCard.classList.add('is-dragging');
            return;
        }

        const sectionCard = target.closest('[data-course-section-card]');

        if (sectionCard) {
            state.dragSectionIndex = Number(sectionCard.dataset.sectionIndex ?? '');
            sectionCard.classList.add('is-dragging');
        }
    }

    function handleBuilderDragEnd(event) {
        const target = event.target;

        if (target instanceof HTMLElement) {
            const itemCard = target.closest('[data-course-item-card]');
            const sectionCard = target.closest('[data-course-section-card]');

            if (itemCard) {
                itemCard.classList.remove('is-dragging');
            }

            if (sectionCard) {
                sectionCard.classList.remove('is-dragging');
            }
        }

        state.dragSectionIndex = null;
        state.dragItemOwner = null;
        state.dragItemIndex = null;
    }

    function handleBuilderDragOver(event) {
        if (
            event.target.closest('[data-course-section-card]')
            || event.target.closest('[data-course-items-list]')
            || event.target.closest('[data-course-item-card]')
        ) {
            event.preventDefault();
        }
    }

    function handleBuilderDrop(event) {
        if (state.dragItemOwner !== null) {
            const itemsList = event.target.closest('[data-course-items-list]');

            if (!itemsList) {
                return;
            }

            event.preventDefault();
            const owner = itemsList.dataset.courseItemsList ?? '';

            if (owner !== state.dragItemOwner) {
                return;
            }

            const items = getItemsByOwner(owner);

            if (!items) {
                return;
            }

            const itemCard = event.target.closest('[data-course-item-card]');
            const dropIndex = itemCard ? Number(itemCard.dataset.itemIndex ?? '') : Math.max(items.length - 1, 0);

            if (Number.isNaN(dropIndex) || Number.isNaN(state.dragItemIndex)) {
                return;
            }

            reorderList(items, state.dragItemIndex, dropIndex);
            state.dragItemOwner = null;
            state.dragItemIndex = null;
            renderBuilder();
            return;
        }

        if (state.dragSectionIndex !== null) {
            const sectionCard = event.target.closest('[data-course-section-card]');

            if (!sectionCard) {
                return;
            }

            event.preventDefault();
            const dropIndex = Number(sectionCard.dataset.sectionIndex ?? '');

            if (Number.isNaN(dropIndex)) {
                return;
            }

            reorderList(state.sections, state.dragSectionIndex, dropIndex);
            state.dragSectionIndex = null;
            renderBuilder();
        }
    }

    async function resolveSectionForSave(section, { slug, currentUserId, sectionIndex }) {
        const title = String(section.title ?? '').trim();

        if (!title) {
            throw createEditorValidationError(`La seccion ${sectionIndex + 1} necesita un titulo.`, {
                focusSelector: `[data-course-section-field="title"][data-section-index="${sectionIndex}"]`,
                highlightSelector: `[data-course-section-field="title"][data-section-index="${sectionIndex}"]`,
            });
        }

        if (!Array.isArray(section.items) || section.items.length === 0) {
            throw createEditorValidationError(`La seccion "${title}" necesita al menos un contenido o debes eliminarla.`, {
                focusSelector: `[data-course-add-section-item][data-section-index="${sectionIndex}"]`,
                highlightSelector: `[data-course-section-card="true"][data-section-index="${sectionIndex}"]`,
            });
        }

        const items = await Promise.all(section.items.map((item, index) => {
            return resolveItemForSave(item, {
                slug,
                currentUserId,
                scope: `sections/section-${sectionIndex + 1}/item-${index + 1}`,
                owner: `section:${sectionIndex}`,
                itemIndex: index,
            });
        }));

        return {
            id: section.id ?? crypto.randomUUID(),
            title,
            description: String(section.description ?? '').trim(),
            items,
        };
    }

    async function resolveItemForSave(item, { slug, currentUserId, scope, owner, itemIndex }) {
        const type = item.type;
        const title = String(item.title ?? '').trim();

        if (!title) {
            throw createEditorValidationError('Cada contenido necesita un titulo.', {
                focusSelector: `[data-course-item-field="title"][data-owner="${owner}"][data-item-index="${itemIndex}"]`,
                highlightSelector: `[data-course-item-field="title"][data-owner="${owner}"][data-item-index="${itemIndex}"]`,
            });
        }

        if (type === 'article') {
            return {
                id: item.id ?? crypto.randomUUID(),
                type: 'article',
                title,
                description: String(item.description ?? '').trim(),
                body: String(item.body ?? '').trim(),
            };
        }

        let storagePath = String(item.storage_path ?? '').trim() || extractManagedStoragePath(item.file_url);
        let fileUrl = storagePath ? '' : String(item.file_url ?? '').trim();
        let fileName = String(item.file_name ?? '').trim();

        if (item.pendingFile instanceof File) {
            storagePath = await uploadAsset(item.pendingFile, {
                slug,
                currentUserId,
                scope,
                type,
            });
            fileUrl = '';
            fileName = item.pendingFile.name;
        }

        if (!storagePath && !fileUrl) {
            const typeLabel = CONTENT_TYPE_META[type]?.label ?? 'contenido';
            throw createEditorValidationError(`"${title}" necesita un archivo de tipo ${typeLabel}.`, {
                focusSelector: `[data-course-choose-file="true"][data-owner="${owner}"][data-item-index="${itemIndex}"]`,
                highlightSelector: `[data-course-item-file-zone="true"][data-owner="${owner}"][data-item-index="${itemIndex}"]`,
            });
        }

        return {
            id: item.id ?? crypto.randomUUID(),
            type,
            title,
            description: String(item.description ?? '').trim(),
            storage_path: storagePath,
            file_url: fileUrl,
            file_name: fileName || extractFileName(storagePath || fileUrl),
        };
    }

    async function uploadAsset(file, { slug, currentUserId, scope, type }) {
        const safeSlug = slugify(slug || 'curso');
        const extension = getFileExtension(file.name, file.type);
        const filePath = `${currentUserId}/${safeSlug}/${scope}/${type}-${crypto.randomUUID()}.${extension}`;
        const bucket = supabase.storage.from(COURSE_STORAGE_BUCKET);
        const { error } = await bucket.upload(filePath, file, {
            cacheControl: '3600',
            upsert: false,
            contentType: file.type || undefined,
        });

        if (error) {
            throw error;
        }

        return filePath;
    }

    async function resolveCoverImage({ slug, currentUserId }) {
        if (state.coverImageFile instanceof File) {
            const storagePath = await uploadAsset(state.coverImageFile, {
                slug,
                currentUserId,
                scope: 'cover',
                type: 'cover',
            });

            return {
                storagePath,
                url: '',
            };
        }

        const storagePath = String(state.coverStoragePath ?? '').trim() || extractManagedStoragePath(state.coverImageUrl);

        return {
            storagePath,
            url: storagePath ? '' : String(state.coverImageUrl ?? '').trim(),
        };
    }

    async function removeStorageObjects(paths) {
        if (paths.length === 0) {
            return;
        }

        const { error } = await supabase.storage.from(COURSE_STORAGE_BUCKET).remove(paths);

        if (error) {
            console.error(error);
        }
    }

    function collectManagedAssetPaths(course) {
        if (!course) {
            return [];
        }

        const paths = [];

        if (course.cover_storage_path) {
            paths.push(course.cover_storage_path);
        } else if (course.cover_image_url) {
            paths.push(course.cover_image_url);
        }

        const appendFromItems = (items) => {
            if (!Array.isArray(items)) {
                return;
            }

            items.forEach((item) => {
                if (!ASSET_TYPES.has(item?.type)) {
                    return;
                }

                if (item.storage_path) {
                    paths.push(item.storage_path);
                    return;
                }

                if (item.file_url) {
                    paths.push(item.file_url);
                }
            });
        };

        appendFromItems(course.loose_items);

        if (Array.isArray(course.sections)) {
            course.sections.forEach((section) => {
                appendFromItems(section?.items);
            });
        }

        return [...new Set(paths.map(extractManagedStoragePath).filter(Boolean))];
    }

    function extractManagedStoragePath(url) {
        if (!url) {
            return null;
        }

        if (!String(url).includes('://') && !String(url).startsWith('/')) {
            return String(url);
        }

        try {
            const parsed = new URL(url, window.location.origin);
            const prefixes = [
                `/storage/v1/object/public/${COURSE_STORAGE_BUCKET}/`,
                `/storage/v1/object/sign/${COURSE_STORAGE_BUCKET}/`,
            ];

            for (const prefix of prefixes) {
                const index = parsed.pathname.indexOf(prefix);

                if (index !== -1) {
                    return decodeURIComponent(parsed.pathname.slice(index + prefix.length));
                }
            }
        } catch (error) {
            return null;
        }

        return null;
    }

    function normalizeSections(sections) {
        if (!Array.isArray(sections)) {
            return [];
        }

        return sections.map((section) => {
            return {
                id: section?.id ?? crypto.randomUUID(),
                title: String(section?.title ?? ''),
                description: String(section?.description ?? ''),
                items: normalizeItems(section?.items),
            };
        });
    }

    function normalizeItems(items) {
        if (!Array.isArray(items)) {
            return [];
        }

        return items.map((item) => {
            return {
                id: item?.id ?? crypto.randomUUID(),
                type: String(item?.type ?? 'article'),
                title: String(item?.title ?? ''),
                description: String(item?.description ?? ''),
                body: String(item?.body ?? ''),
                storage_path: String(item?.storage_path ?? '') || extractManagedStoragePath(item?.file_url ?? ''),
                file_url: String(item?.file_url ?? ''),
                file_name: String(item?.file_name ?? ''),
                pendingFile: null,
            };
        });
    }

    function createSection() {
        return {
            id: crypto.randomUUID(),
            title: '',
            description: '',
            items: [],
        };
    }

    function createItem(type) {
        return {
            id: crypto.randomUUID(),
            type,
            title: '',
            description: '',
            body: '',
            storage_path: '',
            file_url: '',
            file_name: '',
            pendingFile: null,
        };
    }

    function getItemByOwner(owner, index) {
        const items = getItemsByOwner(owner);

        if (!items || Number.isNaN(index) || !items[index]) {
            return null;
        }

        return items[index];
    }

    function getItemsByOwner(owner) {
        if (owner === 'loose') {
            return state.looseItems;
        }

        if (!owner.startsWith('section:')) {
            return null;
        }

        const sectionIndex = Number(owner.split(':')[1] ?? '');

        if (Number.isNaN(sectionIndex) || !state.sections[sectionIndex]) {
            return null;
        }

        return state.sections[sectionIndex].items;
    }

    function reorderList(list, fromIndex, toIndex) {
        if (!Array.isArray(list) || fromIndex === toIndex) {
            return;
        }

        const [moved] = list.splice(fromIndex, 1);
        list.splice(toIndex, 0, moved);
    }

    function countTotalItems(sections, looseItems) {
        const looseCount = Array.isArray(looseItems) ? looseItems.length : 0;
        const sectionCount = Array.isArray(sections)
            ? sections.reduce((total, section) => total + (Array.isArray(section?.items) ? section.items.length : 0), 0)
            : 0;

        return looseCount + sectionCount;
    }

    function getItemFileLabel(item) {
        if (item.pendingFile instanceof File) {
            return item.pendingFile.name;
        }

        if (item.file_name) {
            return item.file_name;
        }

        if (item.storage_path) {
            return extractFileName(item.storage_path);
        }

        if (item.file_url) {
            return extractFileName(item.file_url);
        }

        return 'Sin archivo seleccionado';
    }

    function extractFileName(fileUrl) {
        try {
            const parsed = new URL(fileUrl, window.location.origin);
            const pathname = parsed.pathname.split('/');
            return decodeURIComponent(pathname[pathname.length - 1] ?? 'archivo');
        } catch (error) {
            const pathParts = String(fileUrl ?? '').split('/');
            return decodeURIComponent(pathParts[pathParts.length - 1] ?? 'archivo');
        }
    }

    async function openCourseAsset(item, triggerButton) {
        setButtonBusy(triggerButton, true, 'Abriendo...');

        try {
            const signedUrl = await getSignedAssetUrl(item);

            if (!signedUrl) {
                throw new Error('No encontramos un archivo disponible para este contenido.');
            }

            window.open(signedUrl, '_blank', 'noopener,noreferrer');
        } catch (error) {
            showModuleNotice('error', humanizeError(error instanceof Error ? error.message : String(error)));
        } finally {
            setButtonBusy(triggerButton, false);
        }
    }

    async function getSignedAssetUrl(item) {
        const storagePath = String(item.storage_path ?? '').trim() || extractManagedStoragePath(item.file_url);

        if (storagePath) {
            const { data, error } = await supabase.storage
                .from(COURSE_STORAGE_BUCKET)
                .createSignedUrl(storagePath, 120);

            if (error) {
                throw error;
            }

            return normalizeSignedUrl(data?.signedUrl ?? '');
        }

        return String(item.file_url ?? '').trim();
    }

    function normalizeSignedUrl(value) {
        const signedUrl = String(value ?? '').trim();

        if (!signedUrl) {
            return '';
        }

        if (signedUrl.startsWith('http://') || signedUrl.startsWith('https://')) {
            return signedUrl;
        }

        const projectUrl = String(window.AISCALER_AUTH_CONFIG?.supabaseUrl ?? '').trim().replace(/\/+$/, '');

        if (!projectUrl) {
            return signedUrl;
        }

        if (signedUrl.startsWith('/')) {
            return `${projectUrl}${signedUrl}`;
        }

        return `${projectUrl}/storage/v1/${signedUrl.replace(/^\/+/, '')}`;
    }

    function releaseObjectUrl(url, file) {
        if (!(file instanceof File) || !String(url ?? '').startsWith('blob:')) {
            return;
        }

        URL.revokeObjectURL(url);
    }

    function getFileExtension(fileName, mimeType = '') {
        const explicitExtension = String(fileName ?? '').split('.').pop()?.toLowerCase();

        if (explicitExtension && explicitExtension !== String(fileName ?? '').toLowerCase()) {
            return explicitExtension;
        }

        const mimeMap = {
            'video/mp4': 'mp4',
            'video/webm': 'webm',
            'video/quicktime': 'mov',
            'audio/mpeg': 'mp3',
            'audio/mp4': 'm4a',
            'audio/wav': 'wav',
            'audio/x-wav': 'wav',
            'audio/webm': 'webm',
            'audio/ogg': 'ogg',
            'application/pdf': 'pdf',
            'image/jpeg': 'jpg',
            'image/png': 'png',
            'image/webp': 'webp',
            'image/gif': 'gif',
            'image/svg+xml': 'svg',
            'image/avif': 'avif',
        };

        return mimeMap[mimeType] ?? 'bin';
    }

    function getIconForContentType(type) {
        const iconMap = {
            video: 'play_circle',
            article: 'article',
            pdf: 'picture_as_pdf',
            audio: 'graphic_eq',
        };

        return iconMap[type] ?? 'description';
    }

    function handleEditorFieldInteraction(event) {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (target.closest('#course-editor-shell')) {
            clearEditorError();
        }
    }

    function createEditorValidationError(message, details = {}) {
        const error = new Error(message);
        error.name = 'CourseEditorValidationError';
        error.courseEditor = {
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

        if (!normalized.courseEditor) {
            return;
        }

        const focusTarget = normalized.courseEditor.focusSelector
            ? document.querySelector(normalized.courseEditor.focusSelector)
            : null;
        const highlightTarget = normalized.courseEditor.highlightSelector
            ? document.querySelector(normalized.courseEditor.highlightSelector)
            : focusTarget;

        if (highlightTarget instanceof HTMLElement) {
            highlightTarget.classList.add('is-invalid');
            highlightTarget.setAttribute('aria-invalid', 'true');
        }

        const messageContainer = resolveEditorErrorContainer(focusTarget, highlightTarget);

        if (messageContainer) {
            const messageNode = document.createElement('p');
            messageNode.className = 'course-field-error';
            messageNode.dataset.courseErrorMessage = 'true';
            messageNode.textContent = normalized.message;
            messageContainer.append(messageNode);
        }

        focusEditorTarget(focusTarget, highlightTarget);
    }

    function normalizeEditorError(error) {
        if (error?.courseEditor?.message) {
            return error;
        }

        if (error instanceof Error) {
            return createEditorValidationError(humanizeError(error.message));
        }

        if (typeof error === 'string') {
            return createEditorValidationError(humanizeError(error));
        }

        return createEditorValidationError('Ocurrio un problema al guardar el curso.');
    }

    function mapPersistenceError(error) {
        const rawMessage = String(error?.message ?? '').toLowerCase();

        if (rawMessage.includes('duplicate key') && rawMessage.includes('slug')) {
            return createEditorValidationError('Ya existe un curso con ese slug. Usa uno diferente.', {
                focusSelector: '#course-slug',
                highlightSelector: '#course-slug',
            });
        }

        return createEditorValidationError(humanizeError(String(error?.message ?? '')));
    }

    function showEditorNotice(message) {
        const notice = document.getElementById('course-editor-notice');
        const noticeMessage = document.getElementById('course-editor-notice-message');

        if (!notice || !noticeMessage) {
            showModuleNotice('error', message);
            return;
        }

        noticeMessage.textContent = message;
        notice.classList.remove('hidden');
    }

    function clearEditorError() {
        const shell = document.getElementById('course-editor-shell');
        const notice = document.getElementById('course-editor-notice');
        const noticeMessage = document.getElementById('course-editor-notice-message');

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

        shell.querySelectorAll('[data-course-error-message="true"]').forEach((element) => {
            element.remove();
        });
    }

    function resolveEditorErrorContainer(focusTarget, highlightTarget) {
        const directField = focusTarget instanceof HTMLElement
            ? focusTarget.closest('.course-editor-field')
            : null;

        if (directField instanceof HTMLElement) {
            return directField;
        }

        const highlightField = highlightTarget instanceof HTMLElement
            ? highlightTarget.closest('.course-editor-field')
            : null;

        if (highlightField instanceof HTMLElement) {
            return highlightField;
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
        const notice = document.getElementById('courses-module-notice');

        if (!notice) {
            showNotice(type, message);
            return;
        }

        const palettes = {
            success: 'course-inline-notice border border-emerald-500/20 bg-emerald-500/10 text-emerald-900',
            error: 'course-inline-notice border border-red-500/20 bg-red-500/10 text-red-900',
            info: 'course-inline-notice border border-sky-500/20 bg-sky-500/10 text-sky-900',
        };

        notice.className = palettes[type] ?? palettes.info;
        notice.textContent = message;
        notice.classList.remove('hidden');
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
        sectionId: COURSES_SECTION_ID,
        renderSection,
        bind,
    };
}
