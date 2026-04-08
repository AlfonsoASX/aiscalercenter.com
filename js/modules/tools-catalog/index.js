import { resolvePanelToolModule } from '../tools/runtime.js';

export function createToolsCatalogModule({
    getAccessToken,
    supabase,
    getCurrentUser,
    getActiveProject,
    humanizeError,
}) {
    const state = {
        sections: new Map(),
    };

    function renderSection(item) {
        return `
            <div
                class="workspace-section-card tools-catalog-module"
                data-tools-catalog-root="true"
                data-section-id="${escapeHtml(String(item.id ?? ''))}"
                data-category-key="${escapeHtml(String(item.tool_category_key ?? ''))}"
            >
                <div class="tools-catalog-shell">
                    ${renderLoadingState(item)}
                </div>
            </div>
        `;
    }

    function bind() {
        document.querySelectorAll('[data-tools-catalog-root="true"]').forEach((root) => {
            if (!(root instanceof HTMLElement) || root.dataset.toolsCatalogBound === 'true') {
                return;
            }

            root.dataset.toolsCatalogBound = 'true';
            ensureSectionState(root);
            root.addEventListener('click', handleRootClick);
            renderRoot(root);
        });
    }

    async function ensureLoaded(sectionId, options = {}) {
        const root = findRoot(sectionId);
        const section = root ? ensureSectionState(root) : null;
        const force = options.force === true;

        if (!root || !section) {
            return;
        }

        const activeProject = getActiveProject?.() ?? null;
        const activeProjectId = String(activeProject?.id ?? '');
        const activeProjectName = String(activeProject?.name ?? '');
        const activeProjectLogoUrl = String(activeProject?.logo_url ?? '');

        if (section.projectId !== activeProjectId) {
            section.catalogHtml = '';
            section.loaded = false;
            section.activeTool = null;
            section.projectId = activeProjectId;
        }

        if (!force && section.catalogHtml && !section.notice) {
            section.activeTool = null;
            renderRoot(root);
            return;
        }

        section.loading = true;
        section.notice = null;
        section.setupRequired = false;
        section.activeTool = null;
        renderRoot(root);

        try {
            const response = await fetch(buildBrowserUrl(
                section.categoryKey,
                section.sectionId,
                activeProjectId,
                activeProjectName,
                activeProjectLogoUrl,
            ), {
                headers: {
                    Accept: 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const html = await response.text();

            if (!response.ok && html.trim() === '') {
                throw new Error('No fue posible cargar las herramientas de esta categoria.');
            }

            section.catalogHtml = html;
            section.loaded = true;
        } catch (error) {
            section.notice = {
                type: 'error',
                message: humanizeErrorMessage(error, humanizeError),
            };
        } finally {
            section.loading = false;
            renderRoot(root);
        }
    }

    function resetView(sectionId) {
        const root = findRoot(sectionId);
        const section = root ? ensureSectionState(root) : null;

        if (!root || !section) {
            return;
        }

        if (section.activeTool) {
            section.activeTool = null;
            section.notice = null;
            renderRoot(root);
            return;
        }

        void ensureLoaded(sectionId, { force: true });
    }

    function handleRootClick(event) {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        const root = target.closest('[data-tools-catalog-root="true"]');

        if (!(root instanceof HTMLElement)) {
            return;
        }

        const section = ensureSectionState(root);

        if (!section) {
            return;
        }

        const backTrigger = target.closest('[data-tools-back]');

        if (backTrigger) {
            event.preventDefault();
            section.activeTool = null;
            section.notice = null;
            renderRoot(root);
            return;
        }

        const refreshTrigger = target.closest('[data-tools-refresh]');

        if (refreshTrigger) {
            event.preventDefault();
            void ensureLoaded(section.sectionId, { force: true });
            return;
        }

        const openTrigger = target.closest('[data-tools-open-slug]');

        if (openTrigger instanceof HTMLElement) {
            event.preventDefault();
            const slug = String(openTrigger.dataset.toolsOpenSlug ?? '').trim();

            if (slug !== '') {
                void openTool(section.sectionId, slug);
            }
        }
    }

    async function openTool(sectionId, slug) {
        const root = findRoot(sectionId);
        const section = root ? ensureSectionState(root) : null;

        if (!root || !section) {
            return;
        }

        section.notice = null;
        section.activeTool = {
            slug,
            loading: true,
            title: '',
            tutorialUrl: '',
            moduleKey: '',
        };
        renderRoot(root);

        try {
            const accessToken = await getAccessToken();
            const activeProject = getActiveProject?.() ?? null;

            if (!accessToken) {
                throw new Error('Tu sesion actual ya no es valida. Vuelve a iniciar sesion.');
            }

            const response = await fetch('api/tools.php?action=launch', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${accessToken}`,
                },
                body: JSON.stringify({
                    slug,
                    section_id: sectionId,
                    project_id: String(activeProject?.id ?? ''),
                    project_name: String(activeProject?.name ?? ''),
                    project_logo_url: String(activeProject?.logo_url ?? ''),
                }),
            });

            const payload = await response.json().catch(() => {
                return {
                    success: false,
                    message: 'No fue posible interpretar la respuesta del servidor.',
                };
            });

            if (!response.ok || payload.success !== true) {
                throw new Error(String(payload.message ?? 'No fue posible abrir la herramienta.'));
            }

            const launchMode = String(payload.data?.launch_mode ?? 'php_folder').trim();
            const moduleKey = String(payload.data?.panel_module_key ?? '').trim();
            const tool = payload.data?.tool ?? {};

            if (launchMode !== 'panel_module') {
                const launchUrl = String(payload.data?.launch_url ?? '').trim();

                if (launchUrl === '') {
                    throw new Error('La herramienta no devolvio una ruta valida para abrirse.');
                }

                window.location.href = launchUrl;
                return;
            }

            const moduleInstance = await resolvePanelToolModule(moduleKey, {
                getAccessToken,
                supabase,
                getCurrentUser,
                getActiveProject,
                showNotice: (type, message) => showToolNotice(root, type, message),
                humanizeError,
            });

            if (!moduleInstance) {
                throw new Error('Esta herramienta interna aun no tiene un runtime configurado.');
            }

            section.activeTool = {
                slug,
                loading: false,
                title: String(tool.title ?? ''),
                tutorialUrl: String(tool.tutorial_youtube_url ?? ''),
                moduleKey,
            };
            renderRoot(root);

            const mount = root.querySelector('[data-tools-tool-mount="true"]');

            if (!(mount instanceof HTMLElement)) {
                throw new Error('No encontramos el contenedor donde debe abrirse la herramienta.');
            }

            mount.innerHTML = moduleInstance.renderSection({
                id: slug,
                label: String(tool.title ?? 'Herramienta'),
                section_title: String(tool.title ?? 'Herramienta'),
                project: getActiveProject?.() ?? null,
            });
            moduleInstance.bind();
        } catch (error) {
            section.activeTool = null;
            section.notice = {
                type: 'error',
                message: humanizeErrorMessage(error, humanizeError),
            };
            renderRoot(root);
        }
    }

    function renderRoot(root) {
        const shell = root.querySelector('.tools-catalog-shell');
        const section = ensureSectionState(root);

        if (!(shell instanceof HTMLElement) || !section) {
            return;
        }

        if (section.activeTool) {
            shell.innerHTML = renderToolShell(section.activeTool);
            return;
        }

        if (section.loading) {
            shell.innerHTML = renderLoadingState({});
            return;
        }

        if (section.catalogHtml) {
            shell.innerHTML = `
                ${section.notice ? renderNotice(section.notice) : ''}
                ${section.catalogHtml}
            `;
            return;
        }

        shell.innerHTML = `
            ${section.notice ? renderNotice(section.notice) : ''}
            ${section.setupRequired ? renderSetupState() : renderLoadingState({})}
        `;
    }

    function renderToolShell(activeTool) {
        const tutorialUrl = String(activeTool?.tutorialUrl ?? '').trim();

        return `
            <div class="tools-catalog-tool-shell">
                <div class="tools-catalog-tool-toolbar">
                    <button type="button" class="tools-catalog-back-button" data-tools-back="true">
                        <span class="material-symbols-rounded">arrow_back</span>
                        <span>Volver a herramientas</span>
                    </button>

                    <div class="tools-catalog-tool-actions">
                        ${tutorialUrl !== ''
                            ? `
                                <a class="tools-catalog-tutorial-link" href="${escapeHtml(tutorialUrl)}" target="_blank" rel="noreferrer noopener">
                                    <span class="material-symbols-rounded">smart_display</span>
                                    <span>Ver tutorial</span>
                                </a>
                            `
                            : ''}
                    </div>
                </div>

                <div class="tools-catalog-notice tools-catalog-notice--info" hidden></div>
                <div class="tools-catalog-tool-mount" data-tools-tool-mount="true">
                    ${activeTool?.loading ? renderToolLoadingState(activeTool.title) : ''}
                </div>
            </div>
        `;
    }

    function renderToolLoadingState(title) {
        return `
            <div class="tools-catalog-empty">
                <span class="material-symbols-rounded tools-catalog-spin">progress_activity</span>
                <h3>${escapeHtml(title || 'Abriendo herramienta')}</h3>
                <p>Preparando la herramienta para abrirla dentro del panel...</p>
            </div>
        `;
    }

    function renderNotice(notice) {
        return `
            <div class="tools-catalog-notice tools-catalog-notice--${escapeHtml(notice.type)}">
                ${escapeHtml(notice.message)}
            </div>
        `;
    }

    function renderSetupState() {
        return `
            <div class="tools-catalog-setup">
                <strong>Falta preparar la base de herramientas.</strong>
                <p>Ejecuta <code>supabase/tools_schema.sql</code> en Supabase para habilitar esta capa.</p>
            </div>
        `;
    }

    function renderLoadingState(item) {
        return `
            <div class="tools-catalog-empty">
                <span class="material-symbols-rounded tools-catalog-spin">progress_activity</span>
                <h3>${escapeHtml(item?.section_title ?? item?.label ?? 'Herramientas')}</h3>
                <p>Preparando las herramientas asignadas a esta categoria...</p>
            </div>
        `;
    }

    function ensureSectionState(root) {
        const sectionId = String(root.dataset.sectionId ?? '');

        if (!state.sections.has(sectionId)) {
            state.sections.set(sectionId, {
                sectionId,
                categoryKey: String(root.dataset.categoryKey ?? ''),
                loading: false,
                loaded: false,
                setupRequired: false,
                notice: null,
                catalogHtml: '',
                activeTool: null,
                projectId: String(getActiveProject?.()?.id ?? ''),
            });
        }

        return state.sections.get(sectionId) ?? null;
    }

    function findRoot(sectionId) {
        const root = document.querySelector(`[data-tools-catalog-root="true"][data-section-id="${escapeAttribute(sectionId)}"]`);
        return root instanceof HTMLElement ? root : null;
    }

    return {
        renderSection,
        bind,
        ensureLoaded,
        resetView,
        setProject,
    };

    function setProject(project) {
        const projectId = String(project?.id ?? '');

        state.sections.forEach((section) => {
            if (section.projectId === projectId) {
                return;
            }

            section.projectId = projectId;
            section.catalogHtml = '';
            section.loaded = false;
            section.activeTool = null;
        });
    }

}

function buildBrowserUrl(categoryKey, sectionId, projectId = '', projectName = '', projectLogoUrl = '') {
    const query = new URLSearchParams({
        category_key: String(categoryKey ?? ''),
        section_id: String(sectionId ?? ''),
        project_id: String(projectId ?? ''),
        project_name: String(projectName ?? ''),
        project_logo_url: String(projectLogoUrl ?? ''),
        partial: '1',
    });

    return `tools-browser.php?${query.toString()}`;
}

function showToolNotice(root, type, message) {
    const notice = root.querySelector('.tools-catalog-tool-shell .tools-catalog-notice');

    if (!(notice instanceof HTMLElement)) {
        return;
    }

    notice.className = `tools-catalog-notice tools-catalog-notice--${escapeToken(type)}`;
    notice.textContent = String(message ?? '');
    notice.hidden = false;
}

function humanizeErrorMessage(error, humanizeError) {
    const rawMessage = error instanceof Error ? error.message : 'No fue posible completar la operacion.';
    return typeof humanizeError === 'function' ? humanizeError(rawMessage) : rawMessage;
}

function escapeToken(value) {
    return String(value ?? '').replace(/[^a-z0-9_-]/gi, '') || 'info';
}


function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function escapeAttribute(value) {
    const raw = String(value ?? '');

    if (window.CSS && typeof window.CSS.escape === 'function') {
        return window.CSS.escape(raw);
    }

    return raw.replaceAll('"', '\\"');
}
