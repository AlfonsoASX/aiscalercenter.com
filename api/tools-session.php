<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/supabase_api.php';
require_once __DIR__ . '/../modules/tools/bootstrap.php';

ensureToolsSessionStarted();
header('Content-Type: application/json; charset=UTF-8');

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

try {
    if ($method === 'DELETE') {
        clearToolsServerAuth();
        sendToolsJson([
            'success' => true,
            'message' => 'Sesion PHP de herramientas limpiada.',
        ]);
    }

    if ($method !== 'POST') {
        sendToolsJson([
            'success' => false,
            'message' => 'Metodo no permitido.',
        ], 405);
    }

    [$token, $user] = requireAuthenticatedToolsUser();

    rememberToolsServerAuth([
        'access_token' => $token,
        'user_id' => (string) ($user['id'] ?? ''),
        'email' => (string) ($user['email'] ?? ''),
    ]);

    sendToolsJson([
        'success' => true,
        'message' => 'Sesion PHP de herramientas lista.',
    ]);
} catch (Throwable $exception) {
    sendToolsJson([
        'success' => false,
        'message' => normalizeToolsExceptionMessage($exception),
    ], 500);
}
