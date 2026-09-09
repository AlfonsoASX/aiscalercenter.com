<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/billing.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        throw new RuntimeException('Metodo no permitido.');
    }

    $payload = file_get_contents('php://input');

    if ($payload === false || trim($payload) === '') {
        throw new RuntimeException('No llego payload del webhook.');
    }

    $signature = trim((string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''));
    billingVerifyWebhookSignature($payload, $signature);
    $event = json_decode($payload, true);

    if (!is_array($event)) {
        throw new RuntimeException('No fue posible interpretar el evento de Stripe.');
    }

    $eventType = trim((string) ($event['type'] ?? ''));

    if (
        str_starts_with($eventType, 'customer.subscription.')
        || $eventType === 'checkout.session.completed'
    ) {
        $subscriptionEvent = $event;

        if ($eventType === 'checkout.session.completed') {
            $object = is_array($event['data']['object'] ?? null) ? $event['data']['object'] : [];
            $sessionMetadata = is_array($object['metadata'] ?? null) ? $object['metadata'] : [];

            if (trim((string) ($object['subscription'] ?? '')) !== '') {
                try {
                    $subscriptionObject = stripeRequest(
                        'GET',
                        'subscriptions/' . rawurlencode((string) ($object['subscription'] ?? ''))
                    );
                } catch (Throwable) {
                    $subscriptionObject = [];
                }

                if ($subscriptionObject !== []) {
                    $subscriptionMetadata = is_array($subscriptionObject['metadata'] ?? null) ? $subscriptionObject['metadata'] : [];
                    $subscriptionObject['metadata'] = array_merge($sessionMetadata, $subscriptionMetadata);
                    $subscriptionObject['customer_email'] = (string) ($object['customer_details']['email'] ?? '');
                    $subscriptionEvent['data']['object'] = $subscriptionObject;
                } else {
                    $subscriptionEvent['data']['object'] = [
                        'id' => (string) ($object['subscription'] ?? ''),
                        'customer' => (string) ($object['customer'] ?? ''),
                        'status' => 'active',
                        'current_period_end' => time() + 2592000,
                        'cancel_at_period_end' => false,
                        'customer_email' => (string) ($object['customer_details']['email'] ?? ''),
                        'metadata' => $sessionMetadata,
                    ];
                }
            }
        }

        billingHandleStripeSubscriptionEvent($subscriptionEvent);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Webhook procesado correctamente.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => billingNormalizeStripeException($exception),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
