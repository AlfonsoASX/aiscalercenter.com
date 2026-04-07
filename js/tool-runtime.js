import {
    authConfig,
    getCurrentSession,
    humanizeAuthError,
    supabase,
} from './supabase-auth.js';
import { resolvePanelToolModule } from './modules/tools/runtime.js';

const runtimePayload = window.AISCALER_TOOL_PAYLOAD ?? null;
const runtimeState = {
    currentUser: null,
};

void bootToolRuntime();

async function bootToolRuntime() {
    consumeRuntimeFlash();

    if (!runtimePayload || typeof runtimePayload !== 'object') {
        showToolNotice('error', 'No encontramos la configuracion de la herramienta solicitada.');
        return;
    }

    const moduleKey = String(runtimePayload.panel_module_key ?? '').trim();
    const mount = document.getElementById('tool-runtime-mount');

    if (mount) {
        mount.innerHTML = '<div class="tool-runtime-loader"><span class="material-symbols-rounded">progress_activity</span><p>Abriendo herramienta...</p></div>';
    }

    const [session, resolvedModule] = await Promise.all([
        getCurrentSession(),
        resolvePanelToolModule(moduleKey, {
            getAccessToken: async () => {
                const currentSession = await getCurrentSession();
                return currentSession?.access_token ?? '';
            },
            supabase,
            getCurrentUser: () => runtimeState.currentUser,
            showNotice: (type, message) => showToolNotice(type, message),
            humanizeError: (message) => humanizeToolError(message),
        }),
    ]);

    if (!session?.user) {
        window.sessionStorage.setItem('aiscaler_flash', 'Inicia sesion para abrir la herramienta.');
        window.sessionStorage.setItem('aiscaler_flash_type', 'error');
        window.location.href = authConfig?.loginUrl ?? 'index.php?view=login';
        return;
    }

    if (!resolvedModule) {
        showToolNotice('error', 'Esta herramienta aun no tiene un runtime interno configurado.');
        return;
    }

    runtimeState.currentUser = session.user;
    hydrateRuntimeUser(session.user);

    if (!mount) {
        return;
    }

    mount.innerHTML = resolvedModule.renderSection({
        id: String(runtimePayload.slug ?? moduleKey),
        label: String(runtimePayload.title ?? 'Herramienta'),
        section_title: String(runtimePayload.title ?? 'Herramienta'),
    });
    resolvedModule.bind();
    startEmbedHeightSync();
}

function hydrateRuntimeUser(user) {
    const userName = document.getElementById('tool-user-name');
    const displayName = String(user?.user_metadata?.full_name ?? '').trim() || String(user?.email ?? 'Usuario');

    if (userName) {
        userName.textContent = displayName;
    }
}

function showToolNotice(type, message) {
    const notice = document.getElementById('tool-notice');

    if (!notice) {
        return;
    }

    notice.className = `tool-runtime-notice tool-runtime-notice--${escapeToken(type)}`;
    notice.textContent = String(message ?? '');
    notice.classList.remove('hidden');
}

function consumeRuntimeFlash() {
    const flash = window.sessionStorage.getItem('aiscaler_flash');
    const type = window.sessionStorage.getItem('aiscaler_flash_type') ?? 'info';

    if (!flash) {
        return;
    }

    window.sessionStorage.removeItem('aiscaler_flash');
    window.sessionStorage.removeItem('aiscaler_flash_type');
    showToolNotice(type, flash);
}

function humanizeToolError(message) {
    const normalized = String(message ?? '').toLowerCase();

    if (normalized.includes('scheduled_posts')) {
        return 'La herramienta aun no tiene listas sus tablas o buckets en Supabase.';
    }

    return humanizeAuthError(message);
}

function escapeToken(value) {
    return String(value ?? '').replace(/[^a-z0-9_-]/gi, '') || 'info';
}

function startEmbedHeightSync() {
    if (window.parent === window) {
        return;
    }

    const postHeight = () => {
        const height = Math.max(
            document.body?.scrollHeight ?? 0,
            document.documentElement?.scrollHeight ?? 0,
            document.body?.offsetHeight ?? 0,
            document.documentElement?.offsetHeight ?? 0,
        );

        window.parent.postMessage({
            type: 'aiscaler-tool-height',
            height,
        }, window.location.origin);
    };

    postHeight();

    if (typeof ResizeObserver === 'function') {
        const observer = new ResizeObserver(() => {
            postHeight();
        });

        if (document.body) {
            observer.observe(document.body);
        }
    } else {
        window.setInterval(postHeight, 800);
    }

    window.addEventListener('load', postHeight);
    window.addEventListener('resize', postHeight);
}
