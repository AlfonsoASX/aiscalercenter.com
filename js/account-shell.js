import {
    authConfig,
    getCurrentSession,
    observeSupabaseAuth,
    signOutUser,
    syncServerSessions,
    clearServerSessions,
} from './supabase-auth.js';

const pageView = document.body.dataset.view ?? '';

if (pageView === 'private-app' || pageView === 'account') {
    initAccountShell();
}

function initAccountShell() {
    document.addEventListener('click', handleShellClick);
    void bootAccountShell();
    observeSupabaseAuth((event, session) => {
        if ((event === 'SIGNED_IN' || event === 'TOKEN_REFRESHED' || event === 'USER_UPDATED') && session) {
            void syncServerSessions(session.access_token);
            return;
        }

        if (event === 'SIGNED_OUT') {
            void clearServerSessions().finally(() => {
                window.location.href = authConfig.loginUrl;
            });
        }
    });
}

async function bootAccountShell() {
    const session = await getCurrentSession();

    if (session?.access_token) {
        await syncServerSessions(session.access_token);
    }
}

async function handleShellClick(event) {
    const target = event.target;

    if (!(target instanceof HTMLElement)) {
        return;
    }

    const logoutButton = target.closest('[data-account-logout]');

    if (logoutButton instanceof HTMLElement) {
        event.preventDefault();
        await handleLogout();
        return;
    }

    const billingButton = target.closest('[data-billing-action]');

    if (billingButton instanceof HTMLElement) {
        event.preventDefault();
        await handleBillingAction(billingButton);
    }
}

async function handleLogout() {
    try {
        await signOutUser();
    } finally {
        await clearServerSessions();
        window.location.href = authConfig.loginUrl;
    }
}

async function handleBillingAction(button) {
    const action = String(button.dataset.billingAction || '').trim();

    if (action === '') {
        return;
    }

    const originalLabel = button.innerHTML;
    button.setAttribute('disabled', 'disabled');
    button.innerHTML = action === 'checkout' ? 'Abriendo checkout...' : 'Abriendo portal...';

    try {
        const response = await fetch(`api/billing.php?action=${encodeURIComponent(action)}`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
            },
        });
        const payload = await response.json().catch(() => ({
            success: false,
            message: 'No fue posible interpretar la respuesta de facturacion.',
        }));

        if (!response.ok || payload.success !== true) {
            throw new Error(String(payload.message ?? 'No fue posible abrir la facturacion.'));
        }

        const redirectUrl = String(payload.data?.url ?? '').trim();

        if (redirectUrl === '') {
            throw new Error('Facturacion no devolvio una URL valida.');
        }

        window.location.href = redirectUrl;
    } catch (error) {
        window.alert(error instanceof Error ? error.message : 'No fue posible abrir facturacion.');
    } finally {
        button.removeAttribute('disabled');
        button.innerHTML = originalLabel;
    }
}
