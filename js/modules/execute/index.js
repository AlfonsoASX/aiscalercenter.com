import {
    EXECUTE_STORAGE_BUCKET,
    PLATFORM_DEFINITIONS,
    buildInitialProviderTarget,
    ensureTarget,
    getPlatformDefinition,
    getProviderGroupLabel,
    getVisibleFields,
    validateTargetDraft,
} from './platforms.js';
import {
    STORAGE_SCOPES,
    buildUserStoragePath,
    getStorageBucketForScopedPath,
} from '../../shared/storage.js';

export const EXECUTE_SECTION_ID = 'Ejecutar';

const EXECUTE_LEGACY_STORAGE_BUCKET = 'scheduled-post-assets';
const SEARCHABLE_POST_FIELDS = ['title', 'body', 'notes'];

export function createExecuteModule({
    supabase,
    getCurrentUser,
    getActiveProject,
    showNotice,
    humanizeError,
}) {
    const state = {
        loading: false,
        saving: false,
        setupRequired: null,
        moduleNotice: null,
        connections: [],
        posts: [],
        weekStart: startOfWeek(new Date()),
        search: '',
        composerOpen: false,
        draft: null,
        openProviders: {},
        signedAssetUrls: {},
    };

    function renderSection(item) {
        return `
            <div id="execute-module" class="workspace-section-card execute-module">
                <div id="execute-module-shell" class="execute-module-shell">
                    ${renderLoadingState(item)}
                </div>
            </div>
        `;
    }

    function bind() {
        const root = document.getElementById('execute-module');

        if (!root) {
            return;
        }

        root.addEventListener('click', handleRootClick);
        root.addEventListener('input', handleRootInput);
        root.addEventListener('change', handleRootChange);
        root.addEventListener('submit', handleRootSubmit);
        renderModule();
        void loadData();
    }

    async function loadData() {
        if (!getActiveProjectId()) {
            state.loading = false;
            state.posts = [];
            state.moduleNotice = {
                type: 'info',
                message: 'Selecciona un proyecto para ver y programar sus ejecuciones.',
            };
            renderModule();
            return;
        }

        state.loading = true;
        renderModule();

        const [connectionsResult, postsResult] = await Promise.allSettled([
            loadConnections(),
            loadPosts(),
        ]);

        state.loading = false;
        state.setupRequired = null;
        state.moduleNotice = null;

        if (connectionsResult.status === 'fulfilled') {
            state.connections = connectionsResult.value;
        } else {
            state.connections = [];
            state.moduleNotice = {
                type: 'error',
                message: resolveModuleErrorMessage(connectionsResult.reason),
            };
        }

        if (postsResult.status === 'fulfilled') {
            state.posts = postsResult.value;
        } else {
            const message = resolveModuleErrorMessage(postsResult.reason);

            if (isSchedulerSchemaMissing(postsResult.reason)) {
                state.setupRequired = {
                    type: 'schema',
                    message: 'Ejecuta supabase/scheduled_posts_schema.sql y supabase/user_files_storage_setup.sql en Supabase para habilitar Ejecutar.',
                };
            } else if (isSchedulerStorageMissing(postsResult.reason)) {
                state.setupRequired = {
                    type: 'storage',
                    message: 'Ejecuta supabase/user_files_storage_setup.sql en Supabase para habilitar el almacenamiento central de archivos.',
                };
            } else {
                state.moduleNotice = {
                    type: 'error',
                    message,
                };
            }

            state.posts = [];
        }

        renderModule();
    }

    async function loadConnections() {
        const { data, error } = await supabase
            .from('social_connections')
            .select('id, provider_key, display_name, connection_label, connection_status')
            .eq('connection_status', 'connected')
            .order('display_name', { ascending: true });

        if (error) {
            throw error;
        }

        return (data ?? [])
            .filter((item) => Boolean(getPlatformDefinition(item.provider_key)))
            .map((item) => {
                return {
                    id: String(item.id ?? ''),
                    provider_key: String(item.provider_key ?? ''),
                    display_name: String(item.display_name ?? item.connection_label ?? ''),
                    connection_label: String(item.connection_label ?? item.display_name ?? ''),
                    connection_status: String(item.connection_status ?? ''),
                };
            });
    }

    async function loadPosts() {
        const projectId = getActiveProjectId();

        if (!projectId) {
            return [];
        }

        const rangeStart = state.weekStart;
        const rangeEnd = addDays(rangeStart, 7);

        const { data, error } = await supabase
            .from('scheduled_posts')
            .select(`
                id,
                project_id,
                owner_user_id,
                title,
                body,
                notes,
                scheduled_at,
                timezone,
                status,
                auto_publish,
                preview_provider_key,
                asset_items,
                created_at,
                updated_at,
                scheduled_post_targets (
                    id,
                    social_connection_id,
                    provider_key,
                    connection_label,
                    publication_type,
                    config,
                    validation_snapshot
                )
            `)
            .eq('project_id', projectId)
            .gte('scheduled_at', rangeStart.toISOString())
            .lt('scheduled_at', rangeEnd.toISOString())
            .order('scheduled_at', { ascending: true });

        if (error) {
            throw error;
        }

        return (data ?? []).map(normalizePost);
    }

    function handleRootClick(event) {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        const createButton = target.closest('[data-execute-create]');

        if (createButton) {
            openComposer();
            return;
        }

        const navigationButton = target.closest('[data-execute-nav]');

        if (navigationButton) {
            navigateWeek(navigationButton.dataset.executeNav);
            return;
        }

        const cardButton = target.closest('[data-execute-open-post]');

        if (cardButton) {
            const postId = cardButton.dataset.executeOpenPost;

            if (postId) {
                openComposer(state.posts.find((post) => post.id === postId) ?? null);
            }

            return;
        }

        const closeComposerButton = target.closest('[data-execute-close-composer]');

        if (closeComposerButton) {
            closeComposer();
            return;
        }

        const toggleProviderButton = target.closest('[data-execute-toggle-provider]');

        if (toggleProviderButton) {
            const providerKey = toggleProviderButton.dataset.executeToggleProvider;

            if (providerKey) {
                state.openProviders[providerKey] = !state.openProviders[providerKey];
                renderModule();
            }

            return;
        }

        const addFilesButton = target.closest('[data-execute-add-files]');

        if (addFilesButton) {
            const input = document.getElementById('execute-assets-input');

            if (input instanceof HTMLInputElement) {
                input.click();
            }

            return;
        }

        const removeAssetButton = target.closest('[data-execute-remove-asset]');

        if (removeAssetButton) {
            const assetId = removeAssetButton.dataset.executeRemoveAsset;

            if (assetId) {
                removeDraftAsset(assetId);
            }

            return;
        }

        const previewButton = target.closest('[data-execute-pick-preview]');

        if (previewButton) {
            const providerKey = previewButton.dataset.executePickPreview;

            if (providerKey && state.draft) {
                state.draft.preview_provider_key = providerKey;
                renderModule();
            }

            return;
        }
    }

    function handleRootInput(event) {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (target.matches('[data-execute-search]')) {
            state.search = target.value;
            renderModule();
            return;
        }

        if (!state.draft) {
            return;
        }

        if (target.matches('[data-execute-global]')) {
            const key = target.dataset.executeGlobal;

            if (key) {
                state.draft[key] = target.type === 'checkbox' ? target.checked : target.value;
                renderModule();
            }

            return;
        }

        if (target.matches('[data-execute-target-field]')) {
            const providerKey = target.dataset.executeProviderKey;
            const fieldKey = target.dataset.executeTargetField;

            if (providerKey && fieldKey) {
                const providerDraft = findDraftTarget(providerKey);

                if (providerDraft) {
                    providerDraft.config[fieldKey] = target.value;
                    renderModule();
                }
            }

            return;
        }

        if (target.matches('[data-execute-publication-type]')) {
            const providerKey = target.dataset.executeProviderKey;

            if (providerKey) {
                const providerDraft = findDraftTarget(providerKey);

                if (providerDraft) {
                    const nextTarget = ensureTarget(
                        {
                            ...providerDraft,
                            publication_type: target.value,
                            config: providerDraft.config,
                        },
                        { provider_key: providerKey }
                    );

                    providerDraft.publication_type = nextTarget.publication_type;
                    providerDraft.config = nextTarget.config;
                    renderModule();
                }
            }
        }
    }

    function handleRootChange(event) {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (target.matches('[data-execute-connection-toggle]') && state.draft) {
            const providerKey = target.dataset.executeProviderKey;
            const connectionId = target.dataset.executeConnectionToggle;

            if (!providerKey || !connectionId) {
                return;
            }

            const providerDraft = findDraftTarget(providerKey);

            if (!providerDraft) {
                return;
            }

            const checked = Boolean(target.checked);

            if (checked && !providerDraft.connection_ids.includes(connectionId)) {
                providerDraft.connection_ids.push(connectionId);
            }

            if (!checked) {
                providerDraft.connection_ids = providerDraft.connection_ids.filter((id) => id !== connectionId);
            }

            if (!state.draft.preview_provider_key) {
                state.draft.preview_provider_key = providerKey;
            }

            renderModule();
            return;
        }

        if (target.matches('#execute-assets-input') && target instanceof HTMLInputElement) {
            const files = Array.from(target.files ?? []);
            addDraftAssets(files);
            target.value = '';
        }
    }

    function handleRootSubmit(event) {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || form.id !== 'execute-form') {
            return;
        }

        event.preventDefault();
        void saveDraft(form);
    }

    function navigateWeek(direction) {
        if (direction === 'today') {
            state.weekStart = startOfWeek(new Date());
        }

        if (direction === 'prev') {
            state.weekStart = addDays(state.weekStart, -7);
        }

        if (direction === 'next') {
            state.weekStart = addDays(state.weekStart, 7);
        }

        void refreshPostsOnly();
    }

    async function refreshPostsOnly() {
        try {
            state.loading = true;
            renderModule();
            state.posts = await loadPosts();
        } catch (error) {
            state.moduleNotice = {
                type: 'error',
                message: resolveModuleErrorMessage(error),
            };
        } finally {
            state.loading = false;
            renderModule();
        }
    }

    function openComposer(post = null) {
        const providerGroups = getConnectionGroups();

        state.draft = post
            ? buildDraftFromPost(post, providerGroups)
            : buildEmptyDraft(providerGroups);
        state.openProviders = Object.fromEntries(providerGroups.map((group, index) => [group.provider_key, index === 0]));
        state.composerOpen = true;

        void hydrateDraftAssetUrls();
        renderModule();
    }

    function closeComposer() {
        cleanupDraftPreviewUrls(state.draft);
        state.composerOpen = false;
        state.draft = null;
        renderModule();
    }

    function buildEmptyDraft(providerGroups) {
        const draftTargets = providerGroups.map((group) => {
            const target = buildInitialProviderTarget(group.provider_key);

            return {
                ...target,
                connection_ids: group.connections.length === 1 ? [group.connections[0].id] : [],
            };
        });

        return {
            id: '',
            title: '',
            body: '',
            notes: '',
            scheduled_at: toDateTimeLocalValue(nextHalfHour(new Date())),
            timezone: resolveLocalTimezone(),
            auto_publish: true,
            preview_provider_key: providerGroups[0]?.provider_key ?? '',
            assets: [],
            targets: draftTargets,
        };
    }

    function buildDraftFromPost(post, providerGroups) {
        const targetMap = new Map();

        for (const group of providerGroups) {
            targetMap.set(group.provider_key, {
                ...buildInitialProviderTarget(group.provider_key),
                connection_ids: [],
            });
        }

        for (const target of post.targets) {
            const current = targetMap.get(target.provider_key) ?? buildInitialProviderTarget(target.provider_key);

            current.provider_key = target.provider_key;
            current.publication_type = String(target.publication_type ?? current.publication_type);
            current.config = {
                ...current.config,
                ...(isPlainObject(target.config) ? target.config : {}),
            };
            current.connection_ids = uniqueValues([
                ...current.connection_ids,
                String(target.social_connection_id ?? ''),
            ].filter(Boolean));
            current.validation_snapshot = Array.isArray(target.validation_snapshot) ? target.validation_snapshot : [];

            targetMap.set(target.provider_key, current);
        }

        return {
            id: post.id,
            title: post.title,
            body: post.body,
            notes: post.notes,
            scheduled_at: toDateTimeLocalValue(new Date(post.scheduled_at)),
            timezone: post.timezone || resolveLocalTimezone(),
            auto_publish: Boolean(post.auto_publish),
            preview_provider_key: post.preview_provider_key || providerGroups[0]?.provider_key || '',
            assets: (Array.isArray(post.asset_items) ? post.asset_items : []).map((asset) => {
                return {
                    id: String(asset.id ?? generateId()),
                    file_name: String(asset.file_name ?? ''),
                    mime_type: String(asset.mime_type ?? ''),
                    size_bytes: Number(asset.size_bytes ?? 0),
                    storage_path: String(asset.storage_path ?? ''),
                    preview_url: '',
                    file: null,
                };
            }),
            targets: providerGroups.map((group) => {
                const current = targetMap.get(group.provider_key) ?? buildInitialProviderTarget(group.provider_key);
                return {
                    ...current,
                    provider_key: group.provider_key,
                };
            }),
        };
    }

    async function hydrateDraftAssetUrls() {
        if (!state.draft) {
            return;
        }

        await Promise.allSettled(state.draft.assets.map(async (asset) => {
            if (asset.storage_path && !state.signedAssetUrls[asset.storage_path]) {
                state.signedAssetUrls[asset.storage_path] = await createSignedUrl(asset.storage_path, 3600);
            }
        }));

        renderModule();
    }

    function addDraftAssets(files) {
        if (!state.draft || files.length === 0) {
            return;
        }

        const nextAssets = files.map((file) => {
            return {
                id: generateId(),
                file_name: file.name,
                mime_type: file.type || 'application/octet-stream',
                size_bytes: file.size,
                storage_path: '',
                preview_url: canPreviewFile(file.type) ? URL.createObjectURL(file) : '',
                file,
            };
        });

        state.draft.assets = [...state.draft.assets, ...nextAssets];
        renderModule();
    }

    function removeDraftAsset(assetId) {
        if (!state.draft) {
            return;
        }

        const asset = state.draft.assets.find((item) => item.id === assetId);

        if (asset?.preview_url?.startsWith('blob:')) {
            URL.revokeObjectURL(asset.preview_url);
        }

        state.draft.assets = state.draft.assets.filter((item) => item.id !== assetId);
        renderModule();
    }

    async function saveDraft(form) {
        if (!state.draft) {
            return;
        }

        const currentUser = getCurrentUser();
        const activeProjectId = getActiveProjectId();

        if (!currentUser?.id) {
            showNotice('error', 'No encontramos el usuario activo para programar contenido.');
            return;
        }

        if (!activeProjectId) {
            state.moduleNotice = {
                type: 'error',
                message: 'Selecciona un proyecto antes de programar contenido.',
            };
            renderModule();
            return;
        }

        const validation = validateDraft();

        if (validation.length > 0) {
            state.moduleNotice = {
                type: 'error',
                message: validation[0],
            };
            renderModule();
            return;
        }

        const submitButton = form.querySelector('button[type="submit"]');
        state.saving = true;
        setButtonBusy(submitButton, true, 'Programando...');

        try {
            const uploadedAssets = await ensureUploadedAssets(currentUser.id, state.draft.assets);
            const postPayload = {
                project_id: activeProjectId,
                owner_user_id: currentUser.id,
                title: String(state.draft.title ?? '').trim(),
                body: String(state.draft.body ?? '').trim(),
                notes: String(state.draft.notes ?? '').trim(),
                scheduled_at: new Date(state.draft.scheduled_at).toISOString(),
                timezone: String(state.draft.timezone ?? resolveLocalTimezone()),
                status: 'scheduled',
                auto_publish: Boolean(state.draft.auto_publish),
                preview_provider_key: resolvePreviewProviderKey(state.draft),
                asset_items: uploadedAssets.map(stripDraftAssetForStorage),
            };

            const postId = await savePostRecord(postPayload);
            await replaceTargetRows(postId, currentUser.id, state.draft.targets, uploadedAssets);

            state.moduleNotice = {
                type: 'success',
                message: 'Publicacion programada correctamente.',
            };
            cleanupDraftPreviewUrls(state.draft);
            state.composerOpen = false;
            state.draft = null;
            await refreshPostsOnly();
        } catch (error) {
            state.moduleNotice = {
                type: 'error',
                message: resolveModuleErrorMessage(error),
            };
            renderModule();
        } finally {
            state.saving = false;
            setButtonBusy(submitButton, false);
        }
    }

    function validateDraft() {
        if (!state.draft) {
            return ['No hay una publicacion lista para guardar.'];
        }

        if (!state.draft.scheduled_at) {
            return ['Selecciona la fecha y hora de publicacion.'];
        }

        const selectedTargets = state.draft.targets.filter((target) => target.connection_ids.length > 0);

        if (selectedTargets.length === 0) {
            return ['Selecciona al menos una red social conectada.'];
        }

        const errors = [];

        for (const target of selectedTargets) {
            const providerErrors = validateTargetDraft({
                target,
                body: state.draft.body,
                notes: state.draft.notes,
                assets: state.draft.assets,
            });

            target.validation_snapshot = providerErrors;

            if (providerErrors.length > 0) {
                state.openProviders[target.provider_key] = true;
                errors.push(...providerErrors);
            }
        }

        return errors;
    }

    async function ensureUploadedAssets(userId, assets) {
        const uploaded = [];

        for (const asset of assets) {
            if (asset.storage_path) {
                uploaded.push(asset);
                continue;
            }

            if (!(asset.file instanceof File)) {
                uploaded.push(asset);
                continue;
            }

            const storagePath = await uploadAsset(userId, asset.file);
            uploaded.push({
                ...asset,
                storage_path: storagePath,
                preview_url: asset.preview_url || '',
            });
        }

        if (state.draft) {
            state.draft.assets = uploaded;
        }

        return uploaded;
    }

    async function uploadAsset(userId, file) {
        const filePath = buildUserStoragePath(
            userId,
            STORAGE_SCOPES.execute,
            `${Date.now()}-${sanitizeFileName(file.name)}`
        );
        const { error } = await supabase.storage
            .from(EXECUTE_STORAGE_BUCKET)
            .upload(filePath, file, {
                cacheControl: '3600',
                upsert: false,
            });

        if (error) {
            throw error;
        }

        return filePath;
    }

    async function savePostRecord(payload) {
        if (state.draft?.id) {
            const { error } = await supabase
                .from('scheduled_posts')
                .update(payload)
                .eq('id', state.draft.id);

            if (error) {
                throw error;
            }

            return state.draft.id;
        }

        const { data, error } = await supabase
            .from('scheduled_posts')
            .insert(payload)
            .select('id')
            .single();

        if (error) {
            throw error;
        }

        return String(data?.id ?? '');
    }

    async function replaceTargetRows(postId, userId, targets, assets) {
        const { error: deleteError } = await supabase
            .from('scheduled_post_targets')
            .delete()
            .eq('post_id', postId);

        if (deleteError) {
            throw deleteError;
        }

        const rows = targets
            .filter((target) => target.connection_ids.length > 0)
            .flatMap((target) => {
                return target.connection_ids.map((connectionId) => {
                    const connection = state.connections.find((item) => item.id === connectionId);

                    return {
                        post_id: postId,
                        owner_user_id: userId,
                        social_connection_id: connectionId,
                        provider_key: target.provider_key,
                        connection_label: connection?.display_name ?? connection?.connection_label ?? getPlatformDefinition(target.provider_key)?.label ?? target.provider_key,
                        publication_type: target.publication_type,
                        config: {
                            ...(isPlainObject(target.config) ? target.config : {}),
                            inherited_body: String(state.draft?.body ?? ''),
                            inherited_notes: String(state.draft?.notes ?? ''),
                            asset_count: assets.length,
                        },
                        validation_snapshot: Array.isArray(target.validation_snapshot) ? target.validation_snapshot : [],
                    };
                });
            });

        if (rows.length === 0) {
            return;
        }

        const { error } = await supabase
            .from('scheduled_post_targets')
            .insert(rows);

        if (error) {
            throw error;
        }
    }

    function renderModule() {
        const shell = document.getElementById('execute-module-shell');

        if (!shell) {
            return;
        }

        shell.innerHTML = `
            <div class="execute-header">
                <div>
                    <h2>${escapeHtml(getActiveProject?.()?.name ? `Ejecutar en ${getActiveProject().name}` : 'Ejecutar')}</h2>
                    <p class="workspace-section-subtitle">
                        Programa contenido con base en tus redes sociales conectadas y valida los campos minimos por plataforma antes de guardar.
                    </p>
                </div>

                <button type="button" class="workspace-primary-button" data-execute-create ${state.connections.length === 0 ? 'disabled' : ''}>
                    <span class="material-symbols-rounded">add</span>
                    <span>Crear publicacion</span>
                </button>
            </div>

            ${renderNotice()}
            ${renderSetupState()}
            ${renderToolbar()}
            ${state.loading ? renderLoadingState() : renderCalendar()}
            ${state.composerOpen ? renderComposer() : ''}
        `;
    }

    function renderNotice() {
        if (!state.moduleNotice) {
            return '';
        }

        return `
            <div class="execute-inline-notice execute-inline-notice--${escapeHtml(state.moduleNotice.type)}">
                ${escapeHtml(state.moduleNotice.message)}
            </div>
        `;
    }

    function renderSetupState() {
        if (!state.setupRequired) {
            return '';
        }

        return `
            <div class="execute-setup-card">
                <strong>Falta preparar Ejecutar en Supabase.</strong>
                <p>${escapeHtml(state.setupRequired.message)}</p>
            </div>
        `;
    }

    function renderToolbar() {
        return `
            <div class="execute-toolbar">
                <div class="execute-search-shell">
                    <span class="material-symbols-rounded">search</span>
                    <input type="search" class="execute-search-input" placeholder="Buscar publicaciones" value="${escapeHtml(state.search)}" data-execute-search>
                </div>

                <div class="execute-toolbar-actions">
                    <button type="button" class="execute-nav-button" data-execute-nav="today">Esta semana</button>
                    <button type="button" class="execute-nav-button execute-nav-button--icon" data-execute-nav="prev" aria-label="Semana anterior">
                        <span class="material-symbols-rounded">chevron_left</span>
                    </button>
                    <div class="execute-week-pill">${escapeHtml(formatWeekRange(state.weekStart))}</div>
                    <button type="button" class="execute-nav-button execute-nav-button--icon" data-execute-nav="next" aria-label="Semana siguiente">
                        <span class="material-symbols-rounded">chevron_right</span>
                    </button>
                </div>
            </div>
        `;
    }

    function renderCalendar() {
        const posts = filterPosts(state.posts, state.search);
        const days = Array.from({ length: 7 }, (_, index) => addDays(state.weekStart, index));

        if (state.connections.length === 0 && posts.length === 0) {
            return `
                <div class="execute-empty-state">
                    <span class="material-symbols-rounded">share</span>
                    <p>Primero conecta al menos una red social en Conecta para poder programar contenido.</p>
                </div>
            `;
        }

        return `
            <div class="execute-calendar">
                ${days.map((day) => {
                    const dayPosts = posts.filter((post) => isSameDay(post.scheduled_at, day));
                    return renderDayColumn(day, dayPosts);
                }).join('')}
            </div>
        `;
    }

    function renderDayColumn(day, posts) {
        return `
            <section class="execute-day-column">
                <header class="execute-day-header">
                    <strong>${escapeHtml(formatDayLabel(day))}</strong>
                    <span>${escapeHtml(formatDayDate(day))}</span>
                </header>

                <div class="execute-day-body">
                    ${posts.length === 0
                        ? `<div class="execute-day-empty">Sin publicaciones</div>`
                        : posts.map((post) => renderPostCard(post)).join('')}
                </div>
            </section>
        `;
    }

    function renderPostCard(post) {
        const previewProvider = getPlatformDefinition(post.preview_provider_key) ?? getPlatformDefinition(post.targets[0]?.provider_key ?? '');
        const targetsLabel = post.targets.map((target) => getPlatformDefinition(target.provider_key)?.label ?? target.provider_key).join(', ');

        return `
            <button type="button" class="execute-post-card" data-execute-open-post="${escapeHtml(post.id)}">
                <div class="execute-post-card-top">
                    <span class="execute-post-time">${escapeHtml(formatTime(post.scheduled_at))}</span>
                    <span class="execute-post-status execute-post-status--${escapeHtml(post.status)}">${escapeHtml(humanizePostStatus(post.status))}</span>
                </div>
                <strong>${escapeHtml(post.title || truncate(post.body, 72) || 'Publicacion programada')}</strong>
                <p>${escapeHtml(truncate(post.body, 120) || 'Sin texto principal.')}</p>
                <div class="execute-post-meta">
                    <span>${escapeHtml(previewProvider?.label ?? 'Sin red')}</span>
                    <span>${escapeHtml(targetsLabel || 'Sin destinos')}</span>
                </div>
            </button>
        `;
    }

    function renderComposer() {
        if (!state.draft) {
            return '';
        }

        const providerGroups = getConnectionGroups();
        const previewProvider = getPlatformDefinition(resolvePreviewProviderKey(state.draft));

        return `
            <div class="execute-modal-shell">
                <div class="execute-modal-backdrop" data-execute-close-composer="true"></div>
                <div class="execute-modal-panel">
                    <div class="execute-modal-header">
                        <div>
                            <h3>${state.draft.id ? 'Editar publicacion' : 'Crear nueva publicacion'}</h3>
                            <p class="workspace-section-subtitle">
                                Detectamos tus redes conectadas y validamos lo necesario para cada una antes de programar.
                            </p>
                        </div>

                        <button type="button" class="workspace-icon-button" data-execute-close-composer="true" aria-label="Cerrar">
                            <span class="material-symbols-rounded">close</span>
                        </button>
                    </div>

                    <form id="execute-form" class="execute-form">
                        <div class="execute-composer-layout">
                            <div class="execute-composer-main">
                                <div class="execute-provider-pills">
                                    ${providerGroups.map((group) => renderProviderPill(group)).join('')}
                                </div>

                                <div class="execute-composer-card">
                                    <div class="execute-form-grid">
                                        <label class="execute-field execute-field--full">
                                            <span>Titulo interno</span>
                                            <input type="text" class="execute-input" value="${escapeHtml(state.draft.title)}" data-execute-global="title" placeholder="Nombre interno para identificar esta publicacion">
                                        </label>

                                        <label class="execute-field execute-field--full">
                                            <span>Texto principal</span>
                                            <textarea class="execute-textarea execute-textarea--xl" data-execute-global="body" placeholder="Escribe el copy base que se reutilizara por red social">${escapeHtml(state.draft.body)}</textarea>
                                        </label>

                                        <label class="execute-field execute-field--full">
                                            <span>Notas</span>
                                            <textarea class="execute-textarea" data-execute-global="notes" placeholder="Notas internas para el equipo o para adaptar la publicacion">${escapeHtml(state.draft.notes)}</textarea>
                                        </label>

                                        <label class="execute-field">
                                            <span>Fecha y hora</span>
                                            <input type="datetime-local" class="execute-input" value="${escapeHtml(state.draft.scheduled_at)}" data-execute-global="scheduled_at">
                                        </label>

                                        <label class="execute-field execute-field--toggle">
                                            <span>Auto publicar</span>
                                            <input type="checkbox" ${state.draft.auto_publish ? 'checked' : ''} data-execute-global="auto_publish">
                                        </label>
                                    </div>

                                    <div class="execute-assets-card">
                                        <div class="execute-assets-head">
                                            <div>
                                                <h4>Archivos</h4>
                                                <p>Los archivos se guardan en Supabase Storage para publicarlos despues en cada red social.</p>
                                            </div>
                                            <button type="button" class="execute-secondary-button" data-execute-add-files="true">
                                                <span class="material-symbols-rounded">upload</span>
                                                <span>Agregar archivos</span>
                                            </button>
                                        </div>

                                        <input id="execute-assets-input" type="file" class="hidden" multiple accept="image/*,video/*,audio/*,application/pdf">

                                        <div class="execute-assets-grid">
                                            ${state.draft.assets.length === 0
                                                ? `<div class="execute-assets-empty">Aun no has agregado archivos.</div>`
                                                : state.draft.assets.map((asset) => renderDraftAsset(asset)).join('')}
                                        </div>
                                    </div>
                                </div>

                                <div class="execute-accordion-list">
                                    ${providerGroups.map((group) => renderProviderAccordion(group)).join('')}
                                </div>
                            </div>

                            <aside class="execute-preview-panel">
                                <div class="execute-preview-card">
                                    <div class="execute-preview-head">
                                        <strong>${escapeHtml(previewProvider?.label ?? 'Vista previa')}</strong>
                                        <span>${escapeHtml(formatPreviewSchedule(state.draft.scheduled_at))}</span>
                                    </div>
                                    ${renderPreviewMedia()}
                                    <div class="execute-preview-body">
                                        <p>${escapeHtml(resolvePreviewText())}</p>
                                        <div class="execute-preview-meta">
                                            <span>${escapeHtml(resolvePreviewPublicationType())}</span>
                                            <span>${escapeHtml(resolvePreviewConnectionsLabel())}</span>
                                        </div>
                                    </div>
                                </div>
                            </aside>
                        </div>

                        <div class="execute-modal-footer">
                            <button type="button" class="execute-secondary-button" data-execute-close-composer="true">Cancelar</button>
                            <button type="submit" class="workspace-primary-button">
                                <span class="material-symbols-rounded">event_available</span>
                                <span>${state.draft.id ? 'Guardar cambios' : 'Programar'}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        `;
    }

    function renderProviderPill(group) {
        const definition = getPlatformDefinition(group.provider_key);
        const target = findDraftTarget(group.provider_key);
        const selectedCount = target?.connection_ids.length ?? 0;
        const isActive = resolvePreviewProviderKey(state.draft) === group.provider_key;

        return `
            <button type="button" class="execute-provider-pill ${isActive ? 'is-active' : ''}" data-execute-pick-preview="${escapeHtml(group.provider_key)}">
                <span class="material-symbols-rounded">${escapeHtml(definition?.icon ?? 'share')}</span>
                <span>${escapeHtml(definition?.label ?? group.provider_key)}</span>
                <small>${selectedCount}</small>
            </button>
        `;
    }

    function renderDraftAsset(asset) {
        return `
            <article class="execute-asset-card">
                <div class="execute-asset-preview">
                    ${renderAssetThumbnail(asset)}
                </div>
                <div class="execute-asset-copy">
                    <strong>${escapeHtml(asset.file_name)}</strong>
                    <small>${escapeHtml(formatBytes(asset.size_bytes))}</small>
                </div>
                <button type="button" class="workspace-icon-button" data-execute-remove-asset="${escapeHtml(asset.id)}" aria-label="Quitar archivo">
                    <span class="material-symbols-rounded">delete</span>
                </button>
            </article>
        `;
    }

    function renderProviderAccordion(group) {
        const definition = getPlatformDefinition(group.provider_key);
        const target = findDraftTarget(group.provider_key);
        const isOpen = Boolean(state.openProviders[group.provider_key]);
        const selectedCount = target?.connection_ids.length ?? 0;
        const validationCount = Array.isArray(target?.validation_snapshot) ? target.validation_snapshot.length : 0;
        const visibleFields = getVisibleFields(group.provider_key, target?.publication_type ?? definition?.publicationTypes?.[0]?.value ?? 'post');

        return `
            <section class="execute-accordion ${isOpen ? 'is-open' : ''}">
                <button type="button" class="execute-accordion-trigger" data-execute-toggle-provider="${escapeHtml(group.provider_key)}">
                    <div>
                        <strong>${escapeHtml(getProviderGroupLabel(group.provider_key))}</strong>
                        <small>${selectedCount} conexiones seleccionadas${validationCount > 0 ? ` · ${validationCount} validaciones pendientes` : ''}</small>
                    </div>
                    <span class="material-symbols-rounded">expand_more</span>
                </button>

                <div class="execute-accordion-panel">
                    <div class="execute-connection-toggle-grid">
                        ${group.connections.map((connection) => {
                            const checked = Boolean(target?.connection_ids.includes(connection.id));
                            return `
                                <label class="execute-connection-toggle ${checked ? 'is-selected' : ''}">
                                    <input type="checkbox" ${checked ? 'checked' : ''} data-execute-provider-key="${escapeHtml(group.provider_key)}" data-execute-connection-toggle="${escapeHtml(connection.id)}">
                                    <span>${escapeHtml(connection.display_name || connection.connection_label || definition?.label || group.provider_key)}</span>
                                </label>
                            `;
                        }).join('')}
                    </div>

                    <div class="execute-form-grid execute-form-grid--compact">
                        <label class="execute-field">
                            <span>Tipo de publicacion</span>
                            <select class="execute-select" data-execute-provider-key="${escapeHtml(group.provider_key)}" data-execute-publication-type="true">
                                ${(definition?.publicationTypes ?? []).map((option) => {
                                    const selected = option.value === target?.publication_type;
                                    return `<option value="${escapeHtml(option.value)}" ${selected ? 'selected' : ''}>${escapeHtml(option.label)}</option>`;
                                }).join('')}
                            </select>
                        </label>
                    </div>

                    <div class="execute-platform-fields">
                        ${visibleFields.map((field) => renderProviderField(group.provider_key, field, target?.config?.[field.key] ?? '')).join('')}
                    </div>

                    ${validationCount > 0
                        ? `
                            <div class="execute-validation-list">
                                ${target.validation_snapshot.map((message) => `<p>${escapeHtml(message)}</p>`).join('')}
                            </div>
                        `
                        : ''}
                </div>
            </section>
        `;
    }

    function renderProviderField(providerKey, field, value) {
        const fieldClasses = field.type === 'textarea' ? 'execute-textarea' : 'execute-input';

        if (field.type === 'select') {
            return `
                <label class="execute-field">
                    <span>${escapeHtml(field.label)}</span>
                    <select class="execute-select" data-execute-provider-key="${escapeHtml(providerKey)}" data-execute-target-field="${escapeHtml(field.key)}">
                        ${(field.options ?? []).map((option) => {
                            const selected = String(option.value) === String(value);
                            return `<option value="${escapeHtml(option.value)}" ${selected ? 'selected' : ''}>${escapeHtml(option.label)}</option>`;
                        }).join('')}
                    </select>
                </label>
            `;
        }

        if (field.type === 'textarea') {
            return `
                <label class="execute-field execute-field--full">
                    <span>${escapeHtml(field.label)}</span>
                    <textarea class="${fieldClasses}" data-execute-provider-key="${escapeHtml(providerKey)}" data-execute-target-field="${escapeHtml(field.key)}" placeholder="${escapeHtml(field.placeholder ?? '')}">${escapeHtml(value)}</textarea>
                </label>
            `;
        }

        return `
            <label class="execute-field">
                <span>${escapeHtml(field.label)}</span>
                <input
                    type="${escapeHtml(field.type ?? 'text')}"
                    class="${fieldClasses}"
                    value="${escapeHtml(value)}"
                    data-execute-provider-key="${escapeHtml(providerKey)}"
                    data-execute-target-field="${escapeHtml(field.key)}"
                    placeholder="${escapeHtml(field.placeholder ?? '')}"
                    ${field.maxLength ? `maxlength="${Number(field.maxLength)}"` : ''}
                >
            </label>
        `;
    }

    function renderPreviewMedia() {
        const asset = state.draft?.assets?.[0] ?? null;

        if (!asset) {
            return `<div class="execute-preview-placeholder">Agrega una imagen, video, audio o PDF para ver una previsualizacion.</div>`;
        }

        const assetUrl = resolveAssetPreviewUrl(asset);
        const mimeType = String(asset.mime_type ?? '').toLowerCase();

        if (mimeType.startsWith('image/') && assetUrl) {
            return `<img src="${escapeHtml(assetUrl)}" alt="" class="execute-preview-media execute-preview-media--image">`;
        }

        if (mimeType.startsWith('video/') && assetUrl) {
            return `<video src="${escapeHtml(assetUrl)}" class="execute-preview-media execute-preview-media--video" controls muted playsinline></video>`;
        }

        if (mimeType.startsWith('audio/') && assetUrl) {
            return `<div class="execute-preview-placeholder"><audio controls src="${escapeHtml(assetUrl)}"></audio></div>`;
        }

        return `<div class="execute-preview-placeholder">Archivo listo para publicarse: ${escapeHtml(asset.file_name)}</div>`;
    }

    function resolvePreviewText() {
        if (!state.draft) {
            return '';
        }

        const previewProviderKey = resolvePreviewProviderKey(state.draft);
        const target = findDraftTarget(previewProviderKey);
        const providerText = target
            ? pickFirstFilledValue(
                target.config.caption,
                target.config.summary,
                target.config.headline,
                target.config.title,
                state.draft.body
            )
            : state.draft.body;

        return truncate(providerText || 'Tu vista previa aparecera aqui.', 220);
    }

    function resolvePreviewPublicationType() {
        if (!state.draft) {
            return '';
        }

        const target = findDraftTarget(resolvePreviewProviderKey(state.draft));
        const definition = getPlatformDefinition(target?.provider_key ?? '');
        const type = definition?.publicationTypes?.find((option) => option.value === target?.publication_type);

        return type?.label ?? 'Publicacion';
    }

    function resolvePreviewConnectionsLabel() {
        if (!state.draft) {
            return '';
        }

        const target = findDraftTarget(resolvePreviewProviderKey(state.draft));

        if (!target || target.connection_ids.length === 0) {
            return 'Sin cuentas seleccionadas';
        }

        return `${target.connection_ids.length} cuenta(s)`;
    }

    function findDraftTarget(providerKey) {
        return state.draft?.targets.find((target) => target.provider_key === providerKey) ?? null;
    }

    function getConnectionGroups() {
        const groups = new Map();

        state.connections.forEach((connection) => {
            if (!groups.has(connection.provider_key)) {
                groups.set(connection.provider_key, {
                    provider_key: connection.provider_key,
                    connections: [],
                });
            }

            groups.get(connection.provider_key).connections.push(connection);
        });

        return Array.from(groups.values());
    }

    function resolvePreviewProviderKey(draft) {
        if (!draft) {
            return '';
        }

        if (draft.preview_provider_key) {
            return draft.preview_provider_key;
        }

        const firstSelected = draft.targets.find((target) => target.connection_ids.length > 0);

        return firstSelected?.provider_key ?? draft.targets[0]?.provider_key ?? '';
    }

    function filterPosts(posts, search) {
        const normalizedSearch = String(search ?? '').trim().toLowerCase();

        if (!normalizedSearch) {
            return posts;
        }

        return posts.filter((post) => {
            const plainValues = SEARCHABLE_POST_FIELDS.map((field) => {
                return String(post[field] ?? '').toLowerCase();
            });
            const targetValues = post.targets.map((target) => {
                return [
                    target.provider_key,
                    target.connection_label,
                    target.publication_type,
                ].join(' ').toLowerCase();
            });

            return [...plainValues, ...targetValues].some((value) => value.includes(normalizedSearch));
        });
    }

    async function createSignedUrl(path, ttlSeconds) {
        const normalizedPath = String(path ?? '').trim();

        if (!normalizedPath) {
            return '';
        }

        const { data, error } = await supabase.storage
            .from(getExecuteStorageBucketForPath(normalizedPath))
            .createSignedUrl(normalizedPath, ttlSeconds);

        if (error) {
            throw error;
        }

        return toAbsoluteStorageUrl(data?.signedUrl ?? '');
    }

    function resolveAssetPreviewUrl(asset) {
        if (asset.preview_url) {
            return asset.preview_url;
        }

        if (asset.storage_path && state.signedAssetUrls[asset.storage_path]) {
            return state.signedAssetUrls[asset.storage_path];
        }

        return '';
    }

    function renderAssetThumbnail(asset) {
        const mimeType = String(asset?.mime_type ?? '').toLowerCase();
        const assetUrl = resolveAssetPreviewUrl(asset);

        if (mimeType.startsWith('image/') && assetUrl) {
            return `<img src="${escapeHtml(assetUrl)}" alt="" class="execute-asset-thumb">`;
        }

        if (mimeType.startsWith('video/') && assetUrl) {
            return `<video src="${escapeHtml(assetUrl)}" class="execute-asset-thumb" muted playsinline></video>`;
        }

        if (mimeType.startsWith('audio/')) {
            return '<span class="material-symbols-rounded">audio_file</span>';
        }

        if (mimeType === 'application/pdf') {
            return '<span class="material-symbols-rounded">picture_as_pdf</span>';
        }

        return '<span class="material-symbols-rounded">draft</span>';
    }

    function resolveModuleErrorMessage(error) {
        const message = error?.message ?? error?.error_description ?? 'No fue posible cargar Ejecutar.';
        return typeof humanizeError === 'function' ? humanizeError(message) : message;
    }

    function getActiveProjectId() {
        return String(getActiveProject?.()?.id ?? '').trim();
    }

    return {
        sectionId: EXECUTE_SECTION_ID,
        renderSection,
        bind,
    };
}

function normalizePost(row) {
    return {
        id: String(row?.id ?? ''),
        project_id: String(row?.project_id ?? ''),
        title: String(row?.title ?? ''),
        body: String(row?.body ?? ''),
        notes: String(row?.notes ?? ''),
        scheduled_at: String(row?.scheduled_at ?? ''),
        timezone: String(row?.timezone ?? ''),
        status: String(row?.status ?? 'scheduled'),
        auto_publish: Boolean(row?.auto_publish),
        preview_provider_key: String(row?.preview_provider_key ?? ''),
        asset_items: Array.isArray(row?.asset_items) ? row.asset_items : [],
        created_at: String(row?.created_at ?? ''),
        updated_at: String(row?.updated_at ?? ''),
        targets: Array.isArray(row?.scheduled_post_targets) ? row.scheduled_post_targets.map((target) => {
            return {
                id: String(target?.id ?? ''),
                social_connection_id: String(target?.social_connection_id ?? ''),
                provider_key: String(target?.provider_key ?? ''),
                connection_label: String(target?.connection_label ?? ''),
                publication_type: String(target?.publication_type ?? 'post'),
                config: isPlainObject(target?.config) ? target.config : {},
                validation_snapshot: Array.isArray(target?.validation_snapshot) ? target.validation_snapshot : [],
            };
        }) : [],
    };
}

function renderLoadingState(item = null) {
    return `
        <div class="execute-loading-state">
            <span class="material-symbols-rounded execute-spin">progress_activity</span>
            <h3>${escapeHtml(item?.section_title ?? item?.label ?? 'Ejecutar')}</h3>
            <p>Preparando el calendario y tus redes conectadas...</p>
        </div>
    `;
}

function isSchedulerSchemaMissing(error) {
    const message = String(error?.message ?? '').toLowerCase();
    return (message.includes('pgrst205') || message.includes('could not find the table'))
        && (message.includes('scheduled_posts') || message.includes('scheduled_post_targets'));
}

function isSchedulerStorageMissing(error) {
    const message = String(error?.message ?? '').toLowerCase();
    return message.includes('bucket') && (message.includes(EXECUTE_STORAGE_BUCKET) || message.includes(EXECUTE_LEGACY_STORAGE_BUCKET));
}

function getExecuteStorageBucketForPath(path) {
    return getStorageBucketForScopedPath(path, STORAGE_SCOPES.execute, EXECUTE_LEGACY_STORAGE_BUCKET);
}

function sanitizeFileName(name) {
    return String(name ?? 'archivo')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-zA-Z0-9.\-_]+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '')
        .toLowerCase();
}

function stripDraftAssetForStorage(asset) {
    return {
        id: String(asset.id ?? generateId()),
        file_name: String(asset.file_name ?? ''),
        mime_type: String(asset.mime_type ?? ''),
        size_bytes: Number(asset.size_bytes ?? 0),
        storage_path: String(asset.storage_path ?? ''),
    };
}

function canPreviewFile(mimeType) {
    const value = String(mimeType ?? '').toLowerCase();
    return value.startsWith('image/') || value.startsWith('video/') || value.startsWith('audio/');
}

function cleanupDraftPreviewUrls(draft) {
    if (!draft || !Array.isArray(draft.assets)) {
        return;
    }

    draft.assets.forEach((asset) => {
        if (String(asset?.preview_url ?? '').startsWith('blob:')) {
            URL.revokeObjectURL(asset.preview_url);
        }
    });
}

function generateId() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `execute_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`;
}

function formatWeekRange(weekStart) {
    const weekEnd = addDays(weekStart, 6);
    const formatter = new Intl.DateTimeFormat('es-MX', {
        day: 'numeric',
        month: 'short',
    });

    return `${formatter.format(weekStart)} - ${formatter.format(weekEnd)}`;
}

function formatDayLabel(date) {
    return new Intl.DateTimeFormat('es-MX', { weekday: 'long' }).format(date);
}

function formatDayDate(date) {
    return new Intl.DateTimeFormat('es-MX', { day: 'numeric', month: 'short' }).format(date);
}

function formatTime(value) {
    if (!value) {
        return '--:--';
    }

    const date = new Date(value);

    return new Intl.DateTimeFormat('es-MX', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function formatPreviewSchedule(value) {
    if (!value) {
        return 'Sin fecha';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'Sin fecha';
    }

    return new Intl.DateTimeFormat('es-MX', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

function formatBytes(bytes) {
    const total = Number(bytes ?? 0);

    if (!Number.isFinite(total) || total <= 0) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    const exponent = Math.min(Math.floor(Math.log(total) / Math.log(1024)), units.length - 1);
    const value = total / (1024 ** exponent);

    return `${value.toFixed(value >= 10 || exponent === 0 ? 0 : 1)} ${units[exponent]}`;
}

function humanizePostStatus(status) {
    const key = String(status ?? 'scheduled');

    switch (key) {
    case 'draft':
        return 'Borrador';
    case 'publishing':
        return 'Publicando';
    case 'published':
        return 'Publicado';
    case 'failed':
        return 'Con error';
    case 'cancelled':
        return 'Cancelado';
    default:
        return 'Programado';
    }
}

function nextHalfHour(date) {
    const next = new Date(date);

    next.setSeconds(0, 0);

    const minutes = next.getMinutes();
    const remainder = minutes % 30;

    if (remainder === 0) {
        next.setMinutes(minutes + 30);
        return next;
    }

    next.setMinutes(minutes + (30 - remainder));
    return next;
}

function toDateTimeLocalValue(date) {
    const value = new Date(date);

    if (Number.isNaN(value.getTime())) {
        return '';
    }

    const year = value.getFullYear();
    const month = String(value.getMonth() + 1).padStart(2, '0');
    const day = String(value.getDate()).padStart(2, '0');
    const hours = String(value.getHours()).padStart(2, '0');
    const minutes = String(value.getMinutes()).padStart(2, '0');

    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

function resolveLocalTimezone() {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || 'America/Mexico_City';
}

function startOfWeek(date) {
    const next = new Date(date);
    next.setHours(0, 0, 0, 0);
    const day = next.getDay();
    const delta = day === 0 ? -6 : 1 - day;
    next.setDate(next.getDate() + delta);
    return next;
}

function addDays(date, amount) {
    const next = new Date(date);
    next.setDate(next.getDate() + amount);
    return next;
}

function isSameDay(dateValue, referenceDate) {
    const date = new Date(dateValue);

    return date.getFullYear() === referenceDate.getFullYear()
        && date.getMonth() === referenceDate.getMonth()
        && date.getDate() === referenceDate.getDate();
}

function toAbsoluteStorageUrl(signedUrl) {
    const value = String(signedUrl ?? '').trim();

    if (!value) {
        return '';
    }

    if (/^https?:\/\//i.test(value)) {
        return value;
    }

    const projectUrl = String(window.AISCALER_AUTH_CONFIG?.supabaseUrl ?? '').replace(/\/+$/, '');

    if (!projectUrl) {
        return value;
    }

    return `${projectUrl}/storage/v1/${value.replace(/^\/+/, '')}`;
}

function truncate(value, limit) {
    const normalized = String(value ?? '').trim();

    if (normalized.length <= limit) {
        return normalized;
    }

    return `${normalized.slice(0, Math.max(0, limit - 1)).trim()}…`;
}

function pickFirstFilledValue(...values) {
    return values.map((value) => String(value ?? '').trim()).find(Boolean) ?? '';
}

function uniqueValues(values) {
    return [...new Set(values)];
}

function isPlainObject(value) {
    return Boolean(value) && typeof value === 'object' && !Array.isArray(value);
}

function setButtonBusy(button, isBusy, busyText = 'Guardando...') {
    if (!(button instanceof HTMLButtonElement)) {
        return;
    }

    if (!button.dataset.defaultLabel) {
        button.dataset.defaultLabel = button.innerHTML;
    }

    button.disabled = isBusy;
    button.innerHTML = isBusy ? busyText : button.dataset.defaultLabel;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
