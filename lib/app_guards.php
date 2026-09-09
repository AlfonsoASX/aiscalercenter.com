<?php
declare(strict_types=1);

require_once __DIR__ . '/account_session.php';
require_once __DIR__ . '/billing.php';
require_once __DIR__ . '/private_apps.php';

function requireAuthenticatedAccount(): array
{
    return requireStoredAuthenticatedAccount();
}

function requireAppAccess(string $appKey): array
{
    $app = findPrivateApp($appKey);

    if (!is_array($app)) {
        throw new RuntimeException('La app privada solicitada no existe.');
    }

    [$accessToken, $user] = requireAuthenticatedAccount();
    $billingState = billingResolveAccountState($accessToken, (string) ($user['id'] ?? ''));

    return [$accessToken, $user, $app, $billingState];
}

function requireWriteAccess(string $appKey, bool $json = false): array
{
    [$accessToken, $user, $app, $billingState] = requireAppAccess($appKey);
    $requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $isMutating = !in_array($requestMethod, ['GET', 'HEAD'], true);

    if ($isMutating && (bool) ($billingState['read_only'] ?? true)) {
        if ($json) {
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code(402);
            echo json_encode([
                'success' => false,
                'message' => 'Tu cuenta esta en modo lectura. Reactiva tu suscripcion para volver a editar.',
                'read_only' => true,
                'app_key' => (string) ($app['key'] ?? $appKey),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        throw new RuntimeException('Tu cuenta esta en modo lectura. Reactiva tu suscripcion para volver a editar.');
    }

    return [$accessToken, $user, $app, $billingState];
}
