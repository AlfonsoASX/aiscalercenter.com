<?php
declare(strict_types=1);

require_once __DIR__ . '/app_routing.php';
require_once __DIR__ . '/supabase_api.php';

function billingConfig(): array
{
    $config = require __DIR__ . '/../config/stripe.php';

    return is_array($config) ? $config : [];
}

function billingReady(): bool
{
    $config = billingConfig();

    return trim((string) ($config['secret_key'] ?? '')) !== ''
        && trim((string) ($config['price_id'] ?? '')) !== ''
        && trim((string) ($config['webhook_secret'] ?? '')) !== '';
}

function billingPlanLabel(): string
{
    return trim((string) (billingConfig()['plan_label'] ?? 'Ecosistema ASX')) ?: 'Ecosistema ASX';
}

function billingWritableStatuses(): array
{
    return ['active', 'trialing'];
}

function billingReadOnlyStatuses(): array
{
    return ['past_due', 'unpaid', 'canceled', 'incomplete_expired', 'inactive'];
}

function billingResolveSubscriptionRow(string $accessToken, string $userId): ?array
{
    try {
        $response = supabaseRestRequest(
            'GET',
            'billing_subscriptions?select=*&user_id=eq.' . rawurlencode(trim($userId)) . '&order=updated_at.desc&limit=1',
            [],
            $accessToken
        );
    } catch (Throwable $exception) {
        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'billing_subscriptions') || str_contains($message, 'pgrst205')) {
            return null;
        }

        throw $exception;
    }

    $rows = is_array($response['data'] ?? null) ? $response['data'] : [];

    return is_array($rows[0] ?? null) ? $rows[0] : null;
}

function billingResolveAccountState(string $accessToken, string $userId): array
{
    $row = billingResolveSubscriptionRow($accessToken, $userId);
    $status = strtolower(trim((string) ($row['status'] ?? 'inactive')));
    $isWritable = in_array($status, billingWritableStatuses(), true);

    if ($status === '') {
        $status = 'inactive';
    }

    return [
        'subscription' => $row,
        'status' => $status,
        'read_only' => !$isWritable,
        'is_active' => $isWritable,
        'message' => $isWritable
            ? 'Tu suscripcion esta activa.'
            : 'Tu cuenta esta en modo lectura. Reactiva tu suscripcion para volver a editar.',
    ];
}

function billingNormalizeStripeException(Throwable $exception): string
{
    $message = trim($exception->getMessage());

    if ($message !== '') {
        return $message;
    }

    return 'Ocurrio un error inesperado al conectar con Stripe.';
}

function stripeRequest(string $method, string $path, array $payload = []): array
{
    $config = billingConfig();
    $secretKey = trim((string) ($config['secret_key'] ?? ''));

    if ($secretKey === '') {
        throw new RuntimeException('Completa la configuracion de Stripe antes de usar facturacion.');
    }

    $curl = curl_init('https://api.stripe.com/v1/' . ltrim($path, '/'));

    if ($curl === false) {
        throw new RuntimeException('No se pudo inicializar cURL para Stripe.');
    }

    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($curl, CURLOPT_USERPWD, $secretKey . ':');
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Accept: application/json']);

    if ($payload !== []) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($payload, '', '&', PHP_QUERY_RFC3986));
    }

    $responseBody = curl_exec($curl);

    if ($responseBody === false) {
        $errorMessage = curl_error($curl);
        closeCurlHandle($curl);
        throw new RuntimeException('Error al llamar la API de Stripe: ' . $errorMessage);
    }

    $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    closeCurlHandle($curl);

    $decoded = $responseBody !== '' ? json_decode($responseBody, true) : null;

    if ($statusCode >= 400) {
        $message = is_array($decoded)
            ? json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : $responseBody;

        throw new RuntimeException('Stripe respondio con error HTTP ' . $statusCode . ': ' . $message);
    }

    return is_array($decoded) ? $decoded : [];
}

function billingCreateCheckoutSession(array $user, string $successUrl, string $cancelUrl): array
{
    $config = billingConfig();
    $userId = trim((string) ($user['id'] ?? ''));
    $email = trim((string) ($user['email'] ?? ''));

    if ($userId === '' || $email === '') {
        throw new RuntimeException('No encontramos una cuenta valida para crear la suscripcion.');
    }

    return stripeRequest('POST', 'checkout/sessions', [
        'mode' => 'subscription',
        'success_url' => $successUrl,
        'cancel_url' => $cancelUrl,
        'line_items[0][price]' => trim((string) ($config['price_id'] ?? '')),
        'line_items[0][quantity]' => '1',
        'customer_email' => $email,
        'allow_promotion_codes' => 'true',
        'locale' => 'es-419',
        'metadata[user_id]' => $userId,
        'metadata[plan_key]' => trim((string) ($config['plan_key'] ?? 'ecosistema_asx')),
        'metadata[email]' => $email,
        'subscription_data[metadata][user_id]' => $userId,
        'subscription_data[metadata][plan_key]' => trim((string) ($config['plan_key'] ?? 'ecosistema_asx')),
        'subscription_data[metadata][email]' => $email,
    ]);
}

function billingCreatePortalSession(string $customerId, string $returnUrl): array
{
    if (trim($customerId) === '') {
        throw new RuntimeException('No encontramos un cliente de Stripe asociado a tu cuenta.');
    }

    return stripeRequest('POST', 'billing_portal/sessions', [
        'customer' => trim($customerId),
        'return_url' => $returnUrl,
    ]);
}

function billingVerifyWebhookSignature(string $payload, string $signatureHeader): void
{
    $secret = trim((string) (billingConfig()['webhook_secret'] ?? ''));

    if ($secret === '') {
        throw new RuntimeException('Completa webhook_secret en config/stripe.php.');
    }

    if ($signatureHeader === '') {
        throw new RuntimeException('No llego la firma del webhook de Stripe.');
    }

    $timestamp = '';
    $signatures = [];

    foreach (explode(',', $signatureHeader) as $part) {
        [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

        if ($key === 't') {
            $timestamp = $value;
        } elseif ($key === 'v1' && $value !== '') {
            $signatures[] = $value;
        }
    }

    if ($timestamp === '' || $signatures === []) {
        throw new RuntimeException('La firma del webhook de Stripe no es valida.');
    }

    $signedPayload = $timestamp . '.' . $payload;
    $expected = hash_hmac('sha256', $signedPayload, $secret);

    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) {
            return;
        }
    }

    throw new RuntimeException('No fue posible verificar la firma del webhook de Stripe.');
}

function billingHandleStripeSubscriptionEvent(array $event): void
{
    $eventId = trim((string) ($event['id'] ?? ''));
    $eventType = trim((string) ($event['type'] ?? ''));
    $object = is_array($event['data']['object'] ?? null) ? $event['data']['object'] : [];
    $customerId = trim((string) ($object['customer'] ?? ''));
    $subscriptionId = trim((string) ($object['id'] ?? ''));
    $status = trim((string) ($object['status'] ?? 'inactive')) ?: 'inactive';
    $currentPeriodEnd = (int) ($object['current_period_end'] ?? 0);
    $cancelAtPeriodEnd = filter_var($object['cancel_at_period_end'] ?? false, FILTER_VALIDATE_BOOL);
    $metadata = is_array($object['metadata'] ?? null) ? $object['metadata'] : [];
    $userId = trim((string) ($metadata['user_id'] ?? ''));
    $email = trim((string) ($object['customer_email'] ?? $metadata['email'] ?? ''));

    if ($eventId === '' || $eventType === '') {
        throw new RuntimeException('El evento de Stripe no tiene identificadores suficientes.');
    }

    supabaseServiceRestRequest('POST', 'billing_events', [
        'provider' => 'stripe',
        'provider_event_id' => $eventId,
        'event_type' => $eventType,
        'payload' => $event,
    ], ['Prefer: resolution=ignore-duplicates,return=minimal']);

    if ($customerId === '' || $subscriptionId === '') {
        return;
    }

    $customerPayload = [
        'user_id' => $userId !== '' ? $userId : null,
        'email' => $email,
        'provider' => 'stripe',
        'provider_customer_id' => $customerId,
        'metadata' => $metadata,
    ];

    supabaseServiceRestRequest(
        'POST',
        'billing_customers',
        $customerPayload,
        ['Prefer: resolution=merge-duplicates,return=representation']
    );

    supabaseServiceRestRequest(
        'POST',
        'billing_subscriptions',
        [
            'user_id' => $userId !== '' ? $userId : null,
            'provider' => 'stripe',
            'provider_customer_id' => $customerId,
            'provider_subscription_id' => $subscriptionId,
            'plan_key' => trim((string) (billingConfig()['plan_key'] ?? 'ecosistema_asx')),
            'status' => $status,
            'current_period_end' => $currentPeriodEnd > 0 ? gmdate('c', $currentPeriodEnd) : null,
            'cancel_at_period_end' => $cancelAtPeriodEnd,
            'metadata' => $event,
        ],
        ['Prefer: resolution=merge-duplicates,return=representation']
    );
}
