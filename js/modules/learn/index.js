import {
    STORAGE_SCOPES,
    USER_FILES_STORAGE_BUCKET,
    extractStoragePathFromUrl,
    getStorageBucketForScopedPath,
} from '../../shared/storage.js';

export const LEARN_SECTION_ID = 'Aprender';

const COURSE_STORAGE_BUCKET = USER_FILES_STORAGE_BUCKET;
const COURSE_LEGACY_STORAGE_BUCKET = 'course-assets';
const ASSET_TYPES = new Set(['video', 'audio', 'pdf']);
const CONTENT_META = {
    video: {
        label: 'Video',
        icon: 'play_circle',
        description: 'Reproduce la leccion directamente aqui.',
    },
    article: {
        label: 'Articulo',
        icon: 'article',
        description: 'Lee el contenido completo dentro del panel.',
    },
    pdf: {
        label: 'PDF',
        icon: 'picture_as_pdf',
        description: 'Consulta el documento desde el visor integrado.',
    },
    audio: {
        label: 'Audio',
        icon: 'graphic_eq',
        description: 'Escucha la leccion desde el reproductor integrado.',
    },
};

const FALLBACK_VISUALS = [
    { start: '#1A3C6E', end: '#2F7CEF' },
    { start: '#D93025', end: '#F28B82' },
    { start: '#DF9C0A', end: '#FBBF24' },
    { start: '#188038', end: '#34A853' },
];

export function createLearnModule({
    supabase,
    humanizeError,
}) {
    const state = {
        courses: [],
        loading: false,
        tableReady: null,
        activeCourseId: null,
        activeLessonKey: null,
        assetUrls: {},
        assetErrors: {},
        assetLoadingKey: null,
        coverUrls: {},
        coverLoadingIds: {},
        moduleNotice: null,
    };

    function renderSection(item) {
        return `
            <div id="learn-module" class="workspace-section-card learn-module">
                <div id="learn-module-shell" class="learn-module-shell">
                    ${renderLoadingState(item)}
                </div>
            </div>
        `;
    }

    function bind() {
        const root = document.getElementById('learn-module');

        if (!root) {
            return;
        }

        root.addEventListener('click', handleModuleClick);
        renderModule();
        void loadCourses();
    }

    async function loadCourses() {
        state.loading = true;
        renderModule();

        const { data, error } = await supabase
            .from('courses')
            .select('id, title, slug, description, cover_image_url, cover_storage_path, status, sections, loose_items, updated_at')
            .eq('status', 'published')
            .order('updated_at', { ascending: false });

        state.loading = false;

        if (error) {
            state.courses = [];
            state.tableReady = false;
            state.moduleNotice = {
                type: 'error',
                message: humanizeError(error.message),
            };
            renderModule();
            return;
        }

        state.courses = (data ?? []).map(normalizeCourse);
        state.tableReady = true;
        state.moduleNotice = null;

        if (!state.courses.some((course) => course.id === state.activeCourseId)) {
            state.activeCourseId = null;
            state.activeLessonKey = null;
        }

        renderModule();
        void hydrateCourseCovers();
    }

    function handleModuleClick(event) {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        const openCourseButton = target.closest('[data-learn-open-course]');

        if (openCourseButton) {
            const courseId = openCourseButton.dataset.learnOpenCourse;

            if (courseId) {
                openCourse(courseId);
            }

            return;
        }

        const openLessonButton = target.closest('[data-learn-open-lesson]');

        if (openLessonButton) {
            const lessonKey = openLessonButton.dataset.learnOpenLesson;

            if (lessonKey) {
                openLesson(lessonKey);
            }

            return;
        }

        const backToCatalogButton = target.closest('[data-learn-back-catalog]');

        if (backToCatalogButton) {
            state.activeCourseId = null;
            state.activeLessonKey = null;
            renderModule();
            return;
        }

        const backToCourseButton = target.closest('[data-learn-back-course]');

        if (backToCourseButton) {
            state.activeLessonKey = null;
            renderModule();
            return;
        }

        const jumpLessonButton = target.closest('[data-learn-jump-lesson]');

        if (jumpLessonButton) {
            const lessonKey = jumpLessonButton.dataset.learnJumpLesson;

            if (lessonKey) {
                openLesson(lessonKey);
            }

            return;
        }

        const retryButton = target.closest('[data-learn-retry-asset]');

        if (retryButton) {
            const lessonKey = retryButton.dataset.learnRetryAsset;

            if (lessonKey) {
                delete state.assetErrors[lessonKey];
                delete state.assetUrls[lessonKey];
                void ensureLessonAsset(lessonKey);
            }
        }
    }

    function openCourse(courseId) {
        const course = state.courses.find((item) => item.id === courseId);

        if (!course) {
            return;
        }

        state.activeCourseId = courseId;
        state.activeLessonKey = null;
        state.moduleNotice = null;
        renderModule();
        void ensureCourseCoverUrl(course);
    }

    function openLesson(lessonKey) {
        const course = getActiveCourse();

        if (!course || !getLessonByKey(course, lessonKey)) {
            return;
        }

        state.activeLessonKey = lessonKey;
        state.moduleNotice = null;
        renderModule();
        void ensureLessonAsset(lessonKey);
    }

    async function hydrateCourseCovers() {
        await Promise.allSettled(state.courses.map((course) => ensureCourseCoverUrl(course)));
    }

    async function ensureCourseCoverUrl(course) {
        if (!course || state.coverUrls[course.id] || state.coverLoadingIds[course.id]) {
            return;
        }

        const storagePath = String(course.cover_storage_path ?? '').trim() || extractManagedStoragePath(course.cover_image_url);
        const fallbackUrl = String(course.cover_image_url ?? '').trim();

        if (!storagePath && !fallbackUrl) {
            return;
        }

        state.coverLoadingIds[course.id] = true;

        try {
            state.coverUrls[course.id] = storagePath
                ? await createStorageSignedUrl(storagePath, 3600, fallbackUrl)
                : fallbackUrl;
        } catch (error) {
            console.error(error);
        } finally {
            delete state.coverLoadingIds[course.id];
            renderModule();
        }
    }

    async function ensureLessonAsset(lessonKey) {
        const course = getActiveCourse();

        if (!course || !lessonKey || state.assetUrls[lessonKey] || state.assetLoadingKey === lessonKey) {
            return;
        }

        const lesson = getLessonByKey(course, lessonKey);

        if (!lesson || !ASSET_TYPES.has(lesson.type)) {
            return;
        }

        state.assetLoadingKey = lessonKey;
        renderModule();

        try {
            state.assetUrls[lessonKey] = await createLessonAssetUrl(lesson);
            delete state.assetErrors[lessonKey];
        } catch (error) {
            state.assetErrors[lessonKey] = humanizeError(error instanceof Error ? error.message : String(error));
        } finally {
            if (state.assetLoadingKey === lessonKey) {
                state.assetLoadingKey = null;
            }

            renderModule();
        }
    }

    function renderModule() {
        const shell = document.getElementById('learn-module-shell');

        if (!shell) {
            return;
        }

        const activeCourse = getActiveCourse();

        shell.innerHTML = `
            <div class="learn-module-header">
                <div>
                    <h2>${escapeHtml(resolveModuleTitle(activeCourse))}</h2>
                    <p class="workspace-section-subtitle">
                        ${escapeHtml(resolveModuleSubtitle(activeCourse))}
                    </p>
                </div>

                ${renderHeaderActions(activeCourse)}
            </div>

            ${renderModuleNotice()}

            ${renderCurrentScreen(activeCourse)}
        `;
    }

    function resolveModuleTitle(activeCourse) {
        if (activeCourse) {
            return activeCourse.title;
        }

        return 'Aprender';
    }

    function resolveModuleSubtitle(activeCourse) {
        if (!activeCourse) {
            return 'Explora los cursos disponibles y abre el contenido del curso con una navegacion simple.';
        }

        if (state.activeLessonKey) {
            return 'Estas viendo una leccion individual. Puedes volver al curso completo en cualquier momento.';
        }

        return 'Consulta el contenido del curso y abre cualquier leccion con un solo clic.';
    }

    function renderHeaderActions(activeCourse) {
        if (!activeCourse) {
            return '';
        }

        if (state.activeLessonKey) {
            return `
                <button type="button" class="workspace-primary-button learn-back-button" data-learn-back-course="true">
                    <span class="material-symbols-rounded">arrow_back</span>
                    <span>Volver al curso</span>
                </button>
            `;
        }

        return `
            <button type="button" class="workspace-primary-button learn-back-button" data-learn-back-catalog="true">
                <span class="material-symbols-rounded">arrow_back</span>
                <span>Volver a cursos</span>
            </button>
        `;
    }

    function renderCurrentScreen(activeCourse) {
        if (state.loading) {
            return renderLoadingState();
        }

        if (state.tableReady === false) {
            return `
                <div class="learn-setup">
                    <strong>Falta terminar la configuracion de cursos.</strong>
                    <p>Vuelve a ejecutar <code>supabase/courses_schema.sql</code> y <code>supabase/user_files_storage_setup.sql</code> en Supabase para habilitar la lectura de cursos publicados y sus archivos.</p>
                </div>
            `;
        }

        if (!activeCourse) {
            return renderCatalog();
        }

        if (state.activeLessonKey) {
            return renderLessonScreen(activeCourse);
        }

        return renderCourseOverview(activeCourse);
    }

    function renderModuleNotice() {
        if (!state.moduleNotice) {
            return '';
        }

        return `
            <div class="learn-inline-notice learn-inline-notice--${state.moduleNotice.type}">
                ${escapeHtml(state.moduleNotice.message)}
            </div>
        `;
    }

    function renderCatalog() {
        if (state.courses.length === 0) {
            return `
                <div class="learn-empty-state">
                    <span class="material-symbols-rounded">school</span>
                    <h3>Aun no hay cursos publicados</h3>
                    <p>Cuando un administrador publique un curso desde la seccion de Cursos, aparecera aqui automaticamente.</p>
                </div>
            `;
        }

        return `
            <div class="learn-catalog-grid">
                ${state.courses.map((course, index) => renderCourseCard(course, index)).join('')}
            </div>
        `;
    }

    function renderCourseCard(course, index) {
        const meta = getCourseStats(course);
        const coverUrl = state.coverUrls[course.id] ?? '';

        return `
            <button type="button" class="learn-course-card" data-learn-open-course="${course.id}">
                ${coverUrl
                    ? `
                        <div class="learn-course-cover">
                            <img src="${coverUrl}" alt="${escapeHtml(course.title)}">
                        </div>
                    `
                    : renderCourseVisual(course, index, 'learn-course-visual')}

                <div class="learn-course-card-body">
                    <div class="learn-course-card-meta">
                        <span>${meta.totalLessons} lecciones</span>
                        <span>${meta.sectionLabel}</span>
                    </div>

                    <div class="learn-course-card-copy">
                        <h3>${escapeHtml(course.title)}</h3>
                        <p>${escapeHtml(course.description || 'Curso listo para consultarse desde el panel.')}</p>
                    </div>

                    <div class="learn-course-card-footer">
                        <span class="learn-course-card-link">
                            Ver curso
                            <span class="material-symbols-rounded">arrow_forward</span>
                        </span>
                    </div>
                </div>
            </button>
        `;
    }

    function renderCourseOverview(course) {
        const groups = buildCourseGroups(course);
        const stats = getCourseStats(course);
        const coverUrl = state.coverUrls[course.id] ?? '';

        return `
            <div class="learn-course-shell">
                <section class="learn-course-hero">
                    ${coverUrl
                        ? `
                            <div class="learn-course-cover learn-course-cover--hero">
                                <img src="${coverUrl}" alt="${escapeHtml(course.title)}">
                            </div>
                        `
                        : renderCourseVisual(course, 0, 'learn-course-hero-visual')}

                    <div class="learn-course-hero-copy">
                        <span class="learn-course-eyebrow">Curso disponible</span>
                        <h3>${escapeHtml(course.title)}</h3>
                        <p>${escapeHtml(course.description || 'Abre cualquier contenido del curso desde esta misma pantalla.')}</p>

                        <div class="learn-course-stat-grid">
                            <div class="learn-course-stat">
                                <strong>${stats.totalLessons}</strong>
                                <span>Lecciones</span>
                            </div>
                            <div class="learn-course-stat">
                                <strong>${stats.totalSections}</strong>
                                <span>${stats.totalSections === 1 ? 'Seccion' : 'Secciones'}</span>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="learn-outline-panel">
                    <div class="learn-outline-head">
                        <h4>Contenido del curso</h4>
                        <p>Elige una leccion para verla completa en la siguiente pantalla.</p>
                    </div>

                    <div class="learn-outline-groups">
                        ${groups.map((group) => renderCourseGroup(group)).join('')}
                    </div>
                </section>
            </div>
        `;
    }

    function renderCourseGroup(group) {
        return `
            <div class="learn-outline-group">
                <div class="learn-outline-group-head">
                    <strong>${escapeHtml(group.title)}</strong>
                    ${group.description ? `<p>${escapeHtml(group.description)}</p>` : ''}
                </div>

                <div class="learn-outline-items">
                    ${group.items.map((lesson) => {
                        const meta = CONTENT_META[lesson.type] ?? CONTENT_META.article;

                        return `
                            <button type="button" class="learn-outline-item" data-learn-open-lesson="${lesson.key}">
                                <span class="learn-outline-step">${lesson.stepNumber}</span>
                                <span class="learn-outline-copy">
                                    <strong>${escapeHtml(lesson.title)}</strong>
                                    <small>${escapeHtml(meta.label)}</small>
                                    ${lesson.description ? `<p>${escapeHtml(lesson.description)}</p>` : ''}
                                </span>
                                <span class="material-symbols-rounded learn-outline-arrow">arrow_forward</span>
                            </button>
                        `;
                    }).join('')}
                </div>
            </div>
        `;
    }

    function renderLessonScreen(course) {
        const lesson = getLessonByKey(course, state.activeLessonKey);

        if (!lesson) {
            return renderNoLessonState();
        }

        const meta = CONTENT_META[lesson.type] ?? CONTENT_META.article;
        const assetUrl = state.assetUrls[lesson.key] ?? '';
        const assetError = state.assetErrors[lesson.key] ?? '';
        const isAssetLoading = state.assetLoadingKey === lesson.key;
        const adjacent = getAdjacentLessons(course, lesson.key);

        return `
            <div class="learn-lesson-shell">
                <div class="learn-lesson-head">
                    <div>
                        <span class="learn-lesson-badge">${escapeHtml(meta.label)}</span>
                        <h3>${escapeHtml(lesson.title)}</h3>
                        <p>${escapeHtml(lesson.description || meta.description)}</p>
                    </div>
                </div>

                <div class="learn-lesson-body">
                    ${lesson.type === 'article'
                        ? renderArticleLesson(lesson)
                        : renderAssetLesson(lesson, assetUrl, assetError, isAssetLoading)}
                </div>

                <div class="learn-lesson-footer">
                    ${adjacent.previous
                        ? `
                            <button type="button" class="learn-adjacent-button" data-learn-jump-lesson="${adjacent.previous.key}">
                                <span class="material-symbols-rounded">arrow_back</span>
                                <span>${escapeHtml(adjacent.previous.title)}</span>
                            </button>
                        `
                        : '<span></span>'}

                    ${adjacent.next
                        ? `
                            <button type="button" class="learn-adjacent-button learn-adjacent-button--next" data-learn-jump-lesson="${adjacent.next.key}">
                                <span>${escapeHtml(adjacent.next.title)}</span>
                                <span class="material-symbols-rounded">arrow_forward</span>
                            </button>
                        `
                        : ''}
                </div>
            </div>
        `;
    }

    function renderArticleLesson(lesson) {
        const paragraphs = String(lesson.body ?? lesson.content ?? '')
            .split(/\n{2,}/)
            .map((block) => block.trim())
            .filter(Boolean);

        if (paragraphs.length === 0) {
            return `
                <div class="learn-lesson-empty">
                    <span class="material-symbols-rounded">article</span>
                    <p>Este articulo aun no tiene contenido cargado.</p>
                </div>
            `;
        }

        return `
            <article class="learn-article-body">
                ${paragraphs.map((paragraph) => `<p>${escapeHtml(paragraph).replace(/\n/g, '<br>')}</p>`).join('')}
            </article>
        `;
    }

    function renderAssetLesson(lesson, assetUrl, assetError, isAssetLoading) {
        if (isAssetLoading) {
            return `
                <div class="learn-asset-state">
                    <span class="material-symbols-rounded learn-spin">progress_activity</span>
                    <p>Preparando el contenido para verlo dentro del curso...</p>
                </div>
            `;
        }

        if (assetError) {
            return `
                <div class="learn-asset-state learn-asset-state--error">
                    <span class="material-symbols-rounded">error</span>
                    <p>${escapeHtml(assetError)}</p>
                    <button type="button" class="workspace-primary-button" data-learn-retry-asset="${lesson.key}">
                        <span class="material-symbols-rounded">refresh</span>
                        <span>Intentar de nuevo</span>
                    </button>
                </div>
            `;
        }

        if (!assetUrl) {
            return `
                <div class="learn-asset-state">
                    <span class="material-symbols-rounded">${CONTENT_META[lesson.type]?.icon ?? 'description'}</span>
                    <p>Abre esta leccion para cargar su contenido.</p>
                </div>
            `;
        }

        if (lesson.type === 'video') {
            return `
                <div class="learn-media-frame">
                    <video class="learn-media-player" src="${assetUrl}" controls playsinline preload="metadata"></video>
                </div>
            `;
        }

        if (lesson.type === 'audio') {
            return `
                <div class="learn-audio-card">
                    <audio class="learn-audio-player" src="${assetUrl}" controls preload="metadata"></audio>
                </div>
            `;
        }

        return `
            <div class="learn-pdf-viewer">
                <div class="learn-pdf-actions">
                    <a href="${assetUrl}" class="workspace-primary-button" target="_blank" rel="noopener noreferrer">
                        <span class="material-symbols-rounded">open_in_new</span>
                        <span>Abrir PDF</span>
                    </a>
                </div>
                <iframe class="learn-pdf-frame" src="${assetUrl}" title="${escapeHtml(lesson.title)}"></iframe>
            </div>
        `;
    }

    function renderNoLessonState() {
        return `
            <div class="learn-lesson-empty">
                <span class="material-symbols-rounded">school</span>
                <p>No encontramos esa leccion. Vuelve al curso para elegir otra.</p>
            </div>
        `;
    }

    function renderLoadingState(item = null) {
        return `
            <div class="learn-loading-state">
                <span class="material-symbols-rounded learn-spin">progress_activity</span>
                <h3>${escapeHtml(item?.section_title ?? item?.label ?? 'Aprender')}</h3>
                <p>Preparando los cursos disponibles para este usuario...</p>
            </div>
        `;
    }

    function normalizeCourse(course) {
        return {
            ...course,
            cover_image_url: String(course?.cover_image_url ?? '').trim(),
            cover_storage_path: String(course?.cover_storage_path ?? '').trim() || extractManagedStoragePath(course?.cover_image_url ?? ''),
            sections: normalizeSections(course?.sections),
            loose_items: normalizeItems(course?.loose_items),
        };
    }

    function normalizeSections(sections) {
        if (!Array.isArray(sections)) {
            return [];
        }

        return sections.map((section) => ({
            id: section?.id ?? crypto.randomUUID(),
            title: String(section?.title ?? '').trim(),
            description: String(section?.description ?? '').trim(),
            items: normalizeItems(section?.items),
        }));
    }

    function normalizeItems(items) {
        if (!Array.isArray(items)) {
            return [];
        }

        return items.map((item) => ({
            id: item?.id ?? crypto.randomUUID(),
            type: String(item?.type ?? 'article'),
            title: String(item?.title ?? '').trim(),
            description: String(item?.description ?? '').trim(),
            body: String(item?.body ?? '').trim(),
            storage_path: String(item?.storage_path ?? '').trim() || extractManagedStoragePath(item?.file_url ?? ''),
            file_url: String(item?.file_url ?? '').trim(),
            file_name: String(item?.file_name ?? '').trim(),
        }));
    }

    function getActiveCourse() {
        return state.courses.find((course) => course.id === state.activeCourseId) ?? null;
    }

    function getLessonByKey(course, lessonKey) {
        if (!course || !lessonKey) {
            return null;
        }

        const lessons = buildCourseGroups(course).flatMap((group) => group.items);
        return lessons.find((lesson) => lesson.key === lessonKey) ?? null;
    }

    function getAdjacentLessons(course, lessonKey) {
        const lessons = buildCourseGroups(course).flatMap((group) => group.items);
        const currentIndex = lessons.findIndex((lesson) => lesson.key === lessonKey);

        return {
            previous: currentIndex > 0 ? lessons[currentIndex - 1] : null,
            next: currentIndex !== -1 ? lessons[currentIndex + 1] ?? null : null,
        };
    }

    function buildCourseGroups(course) {
        if (!course) {
            return [];
        }

        let stepNumber = 1;
        const groups = [];

        if (Array.isArray(course.loose_items) && course.loose_items.length > 0) {
            groups.push({
                id: `${course.id}:principal`,
                title: course.sections.length > 0 ? 'Contenido principal' : 'Contenido del curso',
                description: course.sections.length > 0 ? 'Estas lecciones van primero.' : '',
                items: course.loose_items.map((item, index) => {
                    const lesson = createLessonDescriptor(item, `${course.id}:loose:${index}`, stepNumber);
                    stepNumber += 1;
                    return lesson;
                }),
            });
        }

        course.sections.forEach((section, sectionIndex) => {
            const items = (section.items ?? []).map((item, itemIndex) => {
                const lesson = createLessonDescriptor(item, `${course.id}:section:${sectionIndex}:${itemIndex}`, stepNumber);
                stepNumber += 1;
                return lesson;
            });

            if (items.length === 0) {
                return;
            }

            groups.push({
                id: section.id ?? `${course.id}:section:${sectionIndex}`,
                title: section.title || `Seccion ${sectionIndex + 1}`,
                description: section.description || '',
                items,
            });
        });

        return groups;
    }

    function createLessonDescriptor(item, key, stepNumber) {
        return {
            ...item,
            key,
            stepNumber,
        };
    }

    function getCourseStats(course) {
        const totalSections = Array.isArray(course.sections)
            ? course.sections.filter((section) => Array.isArray(section.items) && section.items.length > 0).length
            : 0;
        const totalLessons = buildCourseGroups(course).reduce((total, group) => total + group.items.length, 0);

        return {
            totalSections,
            totalLessons,
            sectionLabel: totalSections > 0 ? `${totalSections} secciones` : 'Contenido libre',
        };
    }

    function renderCourseVisual(course, index, className) {
        const palette = getCoursePalette(index);

        return `
            <div
                class="${className}"
                style="--learn-cover-start: ${palette.start}; --learn-cover-end: ${palette.end};"
                aria-hidden="true"
            >
            </div>
        `;
    }

    function getCoursePalette(index) {
        return FALLBACK_VISUALS[index % FALLBACK_VISUALS.length];
    }

    async function createLessonAssetUrl(lesson) {
        const storagePath = String(lesson.storage_path ?? '').trim() || extractManagedStoragePath(lesson.file_url);

        if (storagePath) {
            return await createStorageSignedUrl(storagePath, 3600, lesson.file_url);
        }

        return String(lesson.file_url ?? '').trim();
    }

    async function createStorageSignedUrl(storagePath, ttlSeconds, fallbackUrl = '') {
        const normalizedPath = String(storagePath ?? '').trim();

        if (!normalizedPath) {
            return String(fallbackUrl ?? '').trim();
        }

        const { data, error } = await supabase.storage
            .from(getCourseStorageBucketForPath(normalizedPath))
            .createSignedUrl(normalizedPath, ttlSeconds);

        if (error) {
            throw error;
        }

        return normalizeSignedUrl(data?.signedUrl ?? fallbackUrl);
    }

    function extractManagedStoragePath(url) {
        if (!url) {
            return null;
        }

        if (!String(url).includes('://') && !String(url).startsWith('/')) {
            return String(url);
        }

        try {
            const extracted = extractStoragePathFromUrl(url, [COURSE_STORAGE_BUCKET, COURSE_LEGACY_STORAGE_BUCKET]);

            return extracted?.path ?? null;
        } catch (error) {
            return null;
        }

        return null;
    }

    function getCourseStorageBucketForPath(path) {
        return getStorageBucketForScopedPath(path, STORAGE_SCOPES.courses, COURSE_LEGACY_STORAGE_BUCKET);
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

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    return {
        sectionId: LEARN_SECTION_ID,
        renderSection,
        bind,
    };
}
