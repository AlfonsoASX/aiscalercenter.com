<?php
declare(strict_types=1);

require_once __DIR__ . '/modules/tools/bootstrap.php';

ensureToolsSessionStarted();
cleanupExpiredToolLaunches();

$launchToken = trim((string) ($_GET['launch'] ?? ''));
$launchPayload = $launchToken !== '' ? findToolLaunch($launchToken) : null;
$tool = is_array($launchPayload['tool'] ?? null) ? $launchPayload['tool'] : null;

if (!$tool) {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'La sesion de la herramienta expiro. Vuelve a abrirla desde el panel.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$workspaceRoot = realpath(__DIR__) ?: __DIR__;
$appFolder = trim((string) ($tool['app_folder'] ?? ''), '/');

if ($appFolder === '' || !isSafeRelativePath($appFolder)) {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'La herramienta no tiene una ruta valida.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$appDirectory = realpath($workspaceRoot . DIRECTORY_SEPARATOR . $appFolder);
$apiPath = $appDirectory !== false ? realpath($appDirectory . DIRECTORY_SEPARATOR . 'api.php') : false;

if (
    $appDirectory === false
    || !str_starts_with($appDirectory, $workspaceRoot . DIRECTORY_SEPARATOR)
    || $apiPath === false
    || !str_starts_with($apiPath, $appDirectory . DIRECTORY_SEPARATOR)
) {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'No fue posible resolver la API protegida de la herramienta.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$toolRuntimeContext = [
    'launch_token' => $launchToken,
    'slug' => (string) ($tool['slug'] ?? ''),
    'title' => (string) ($tool['title'] ?? ''),
    'description' => (string) ($tool['description'] ?? ''),
    'tutorial_youtube_url' => (string) ($tool['tutorial_youtube_url'] ?? ''),
    'return_url' => (string) ($tool['return_url'] ?? buildToolsPanelUrl()),
    'embed_mode' => false,
    'access_token' => (string) ($launchPayload['access_token'] ?? ''),
    'user_id' => (string) ($launchPayload['user_id'] ?? ''),
    'user_email' => (string) (($launchPayload['user']['email'] ?? null) ?: ''),
    'project' => is_array($launchPayload['project'] ?? null) ? $launchPayload['project'] : [],
];

require $apiPath;
exit;
