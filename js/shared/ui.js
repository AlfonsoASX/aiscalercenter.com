let noticeBindingsReady = false;

export function bindForm(formId, handler) {
    const form = document.getElementById(formId);

    if (!form) {
        return;
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        void handler(form);
    });
}

export function setButtonBusy(button, isBusy, loadingText = 'Procesando...') {
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

export function showNotice(view, type, message) {
    const notice = view === 'app'
        ? document.getElementById('app-notice')
        : document.getElementById('auth-notice');

    if (!notice) {
        return;
    }

    const appPalettes = {
        success: 'workspace-notice workspace-notice-shell mb-4 border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm font-semibold text-emerald-900 dark:text-emerald-100',
        error: 'workspace-notice workspace-notice-shell mb-4 border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm font-semibold text-red-900 dark:text-red-100',
        info: 'workspace-notice workspace-notice-shell mb-4 border border-sky-500/20 bg-sky-500/10 px-4 py-3 text-sm font-semibold text-sky-900 dark:text-sky-100',
    };
    const authPalettes = {
        success: 'workspace-notice-shell mb-6 rounded-2xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-sm font-semibold text-emerald-100',
        error: 'workspace-notice-shell mb-6 rounded-2xl border border-red-400/30 bg-red-500/10 px-4 py-3 text-sm font-semibold text-red-100',
        info: 'workspace-notice-shell mb-6 rounded-2xl border border-sky-400/30 bg-sky-500/10 px-4 py-3 text-sm font-semibold text-sky-100',
    };
    const palettes = view === 'app' ? appPalettes : authPalettes;
    const normalizedMessage = describeErrorMessage(message, 'Ocurrio un problema inesperado.');

    ensureNoticeBindings();
    notice.className = palettes[type] ?? palettes.info;
    notice.dataset.dismissibleNotice = 'true';
    notice.innerHTML = buildNoticeMarkup(normalizedMessage);
    notice.classList.remove('hidden');
}

export function hideNotice(view) {
    const notice = view === 'app'
        ? document.getElementById('app-notice')
        : document.getElementById('auth-notice');

    clearNoticeElement(notice);
}

export function setFlash(message, type = 'success') {
    sessionStorage.setItem('aiscaler_flash', message);
    sessionStorage.setItem('aiscaler_flash_type', type);
}

export function consumeFlash(view) {
    const flash = sessionStorage.getItem('aiscaler_flash');
    const type = sessionStorage.getItem('aiscaler_flash_type') ?? 'success';

    if (!flash) {
        return;
    }

    sessionStorage.removeItem('aiscaler_flash');
    sessionStorage.removeItem('aiscaler_flash_type');
    showNotice(view, type, flash);
}

function ensureNoticeBindings() {
    if (noticeBindingsReady || typeof document === 'undefined') {
        return;
    }

    noticeBindingsReady = true;
    document.addEventListener('click', handleNoticeInteraction);
}

function handleNoticeInteraction(event) {
    const target = event.target;

    if (!(target instanceof HTMLElement)) {
        return;
    }

    const dismissButton = target.closest('[data-notice-dismiss="true"]');

    if (dismissButton instanceof HTMLElement) {
        clearNoticeElement(dismissButton.closest('[data-dismissible-notice="true"]'));
        return;
    }

    getVisibleNotices().forEach((notice) => {
        if (!notice.contains(target)) {
            clearNoticeElement(notice);
        }
    });
}

function getVisibleNotices() {
    return ['app-notice', 'auth-notice']
        .map((id) => document.getElementById(id))
        .filter((notice) => notice instanceof HTMLElement && !notice.classList.contains('hidden'));
}

function clearNoticeElement(notice) {
    if (!(notice instanceof HTMLElement)) {
        return;
    }

    notice.classList.add('hidden');
    notice.innerHTML = '';
    delete notice.dataset.dismissibleNotice;
}

function buildNoticeMarkup(message) {
    return `
        <div class="workspace-dismissible-notice-content" data-dismissible-notice="true">
            <button type="button" class="workspace-notice-dismiss" data-notice-dismiss="true" aria-label="Cerrar notificacion">
                <span class="material-symbols-rounded">close</span>
            </button>
            <span class="workspace-notice-message">${escapeHtml(message)}</span>
        </div>
    `;
}

export function cleanupAuthHash() {
    if (!window.location.hash) {
        return;
    }

    if (
        window.location.hash.includes('access_token')
        || window.location.hash.includes('refresh_token')
        || window.location.hash.includes('type=')
    ) {
        window.history.replaceState({}, document.title, window.location.pathname + window.location.search);
    }
}

export function normalizeEmail(value) {
    return String(value ?? '').trim().toLowerCase();
}

export function describeErrorMessage(error, fallback = '') {
    if (error instanceof Error) {
        return String(error.message ?? fallback).trim() || fallback;
    }

    if (typeof error === 'string') {
        return error.trim() || fallback;
    }

    if (error && typeof error === 'object') {
        const candidate = [
            error.message,
            error.msg,
            error.error_description,
            error.description,
            error.details,
            error.hint,
            error.code,
        ].find((value) => typeof value === 'string' && value.trim() !== '');

        if (typeof candidate === 'string' && candidate.trim() !== '') {
            return candidate.trim();
        }

        try {
            return JSON.stringify(error);
        } catch (jsonError) {
            return fallback || '[error]';
        }
    }

    return fallback;
}

export function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
