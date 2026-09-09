<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/account_session.php';
require_once __DIR__ . '/../modules/tools/bootstrap.php';

ensureAccountSessionStarted();
ensureToolsSessionStarted();
header('Content-Type: application/json; charset=UTF-8');

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

try {
    if ($method === 'DELETE') {
        clearAccountServerAuth();
        clearToolsServerAuth();
        sendAccountSessionJson([
            'success' => true,
            'message' => 'Sesion PHP de cuenta limpiada.',
        ]);
    }

    if ($method !== 'POST') {
        sendAccountSessionJson([
            'success' => false,
            'message' => 'Metodo no permitido.',
        ], 405);
    }

    [$token, $user] = requireAuthenticatedAccountFromBearer();

    rememberAccountServerAuth([
        'access_token' => $token,
        'user_id' => (string) ($user['id'] ?? ''),
        'email' => (string) ($user['email'] ?? ''),
    ]);
    rememberToolsServerAuth([
        'access_token' => $token,
        'user_id' => (string) ($user['id'] ?? ''),
        'email' => (string) ($user['email'] ?? ''),
    ]);

    sendAccountSessionJson([
        'success' => true,
        'message' => 'Sesion PHP de cuenta lista.',
    ]);
} catch (Throwable $exception) {
    sendAccountSessionJson([
        'success' => false,
        'message' => normalizeAccountException($exception),
    ], 500);
}

function sendAccountSessionJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
