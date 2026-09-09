<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/account_session.php';
require_once __DIR__ . '/../lib/billing.php';

ensureAccountSessionStarted();
header('Content-Type: application/json; charset=UTF-8');

try {
    [$accessToken, $user] = requireStoredAuthenticatedAccount();
    $action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? 'status'));

    if ($action === 'status') {
        sendBillingJson([
            'success' => true,
            'data' => billingResolveAccountState($accessToken, (string) ($user['id'] ?? '')),
        ]);
    }

    if ($action === 'checkout') {
        $session = billingCreateCheckoutSession(
            $user,
            appAccountUrl('suscripcion', true) . '?billing=success',
            appAccountUrl('suscripcion', true) . '?billing=cancel'
        );

        sendBillingJson([
            'success' => true,
            'data' => [
                'url' => (string) ($session['url'] ?? ''),
                'id' => (string) ($session['id'] ?? ''),
            ],
        ]);
    }

    if ($action === 'portal') {
        $subscription = billingResolveSubscriptionRow($accessToken, (string) ($user['id'] ?? ''));
        $customerId = trim((string) ($subscription['provider_customer_id'] ?? ''));

        if ($customerId === '') {
            throw new RuntimeException('Tu cuenta aun no tiene un cliente de Stripe sincronizado.');
        }

        $session = billingCreatePortalSession(
            $customerId,
            appAccountUrl('facturacion', true)
        );

        sendBillingJson([
            'success' => true,
            'data' => [
                'url' => (string) ($session['url'] ?? ''),
                'id' => (string) ($session['id'] ?? ''),
            ],
        ]);
    }

    sendBillingJson([
        'success' => false,
        'message' => 'Accion no soportada.',
    ], 400);
} catch (Throwable $exception) {
    sendBillingJson([
        'success' => false,
        'message' => billingNormalizeStripeException($exception),
    ], 500);
}

function sendBillingJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
