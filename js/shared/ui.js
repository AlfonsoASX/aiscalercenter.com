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
        success: 'workspace-notice mb-4 border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm font-semibold text-emerald-900 dark:text-emerald-100',
        error: 'workspace-notice mb-4 border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm font-semibold text-red-900 dark:text-red-100',
        info: 'workspace-notice mb-4 border border-sky-500/20 bg-sky-500/10 px-4 py-3 text-sm font-semibold text-sky-900 dark:text-sky-100',
    };
    const authPalettes = {
        success: 'mb-6 rounded-2xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-sm font-semibold text-emerald-100',
        error: 'mb-6 rounded-2xl border border-red-400/30 bg-red-500/10 px-4 py-3 text-sm font-semibold text-red-100',
        info: 'mb-6 rounded-2xl border border-sky-400/30 bg-sky-500/10 px-4 py-3 text-sm font-semibold text-sky-100',
    };
    const palettes = view === 'app' ? appPalettes : authPalettes;

    notice.className = palettes[type] ?? palettes.info;
    notice.textContent = message;
    notice.classList.remove('hidden');
}

export function setFlash(message) {
    sessionStorage.setItem('aiscaler_flash', message);
}

export function consumeFlash(view) {
    const flash = sessionStorage.getItem('aiscaler_flash');

    if (!flash) {
        return;
    }

    sessionStorage.removeItem('aiscaler_flash');
    showNotice(view, 'success', flash);
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

export function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
