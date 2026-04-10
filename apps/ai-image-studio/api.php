<?php
declare(strict_types=1);

require_once __DIR__ . '/../../modules/ai-images/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

$toolContext = is_array($toolRuntimeContext ?? null) ? $toolRuntimeContext : [];
$accessToken = trim((string) ($toolContext['access_token'] ?? ''));
$userId = trim((string) ($toolContext['user_id'] ?? ''));
$project = is_array($toolContext['project'] ?? null) ? $toolContext['project'] : [];
$activeProjectId = trim((string) ($project['id'] ?? ''));
$action = trim((string) ($_GET['action'] ?? ''));

try {
    if ($accessToken === '' || $userId === '') {
        throw new RuntimeException('No encontramos la sesion segura del generador.');
    }

    if ($activeProjectId === '') {
        throw new RuntimeException('Selecciona un proyecto antes de usar esta herramienta.');
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        throw new RuntimeException('Metodo no permitido.');
    }

    if ($action !== 'generate') {
        throw new RuntimeException('Accion no soportada por el generador.');
    }

    $payload = json_decode((string) file_get_contents('php://input'), true);
    $payload = is_array($payload) ? $payload : [];
    $prompt = trim((string) ($payload['prompt'] ?? ''));

    if ($prompt === '') {
        throw new RuntimeException('Describe la imagen que quieres generar.');
    }

    if (!aiImagesProviderReady()) {
        throw new RuntimeException('Completa config/ai_images.php para conectar un proveedor real de imagenes IA desde el servidor.');
    }

    throw new RuntimeException('La integracion del proveedor de imagenes aun no se ha implementado en esta iteracion. La herramienta y el endpoint seguro ya quedaron preparados.');
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => normalizeAiImagesException($exception),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
