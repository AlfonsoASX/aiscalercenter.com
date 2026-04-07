<?php
declare(strict_types=1);

require_once __DIR__ . '/modules/tools/bootstrap.php';

ensureToolsSessionStarted();
cleanupExpiredToolLaunches();

$launchToken = trim((string) ($_GET['launch'] ?? ''));
$launchPayload = $launchToken !== '' ? findToolLaunch($launchToken) : null;
$tool = is_array($launchPayload['tool'] ?? null) ? $launchPayload['tool'] : null;

if (!$tool) {
    header('Content-Type: text/html; charset=UTF-8');
    http_response_code(404);
    echo '<div class="tools-catalog-notice tools-catalog-notice--error">La sesion de la herramienta expiro. Vuelve a abrirla desde el panel.</div>';
    exit;
}

$workspaceRoot = realpath(__DIR__) ?: __DIR__;
$appFolder = trim((string) ($tool['app_folder'] ?? ''), '/');

if ($appFolder === '' || !isSafeRelativePath($appFolder)) {
    header('Content-Type: text/html; charset=UTF-8');
    http_response_code(404);
    echo '<div class="tools-catalog-notice tools-catalog-notice--error">La herramienta no tiene una ruta valida.</div>';
    exit;
}

$appDirectory = realpath($workspaceRoot . DIRECTORY_SEPARATOR . $appFolder);
$partialPath = $appDirectory !== false ? realpath($appDirectory . DIRECTORY_SEPARATOR . 'partial.php') : false;

if (
    $appDirectory === false
    || !str_starts_with($appDirectory, $workspaceRoot . DIRECTORY_SEPARATOR)
    || $partialPath === false
    || !str_starts_with($partialPath, $appDirectory . DIRECTORY_SEPARATOR)
) {
    header('Content-Type: text/html; charset=UTF-8');
    http_response_code(404);
    echo '<div class="tools-catalog-notice tools-catalog-notice--error">La herramienta no tiene vista parcial disponible.</div>';
    exit;
}

$toolRuntimeContext = [
    'launch_token' => $launchToken,
    'slug' => (string) ($tool['slug'] ?? ''),
    'title' => (string) ($tool['title'] ?? ''),
    'description' => (string) ($tool['description'] ?? ''),
    'tutorial_youtube_url' => (string) ($tool['tutorial_youtube_url'] ?? ''),
    'return_url' => (string) ($tool['return_url'] ?? buildToolsPanelUrl()),
    'partial_mode' => true,
    'embed_mode' => false,
];

header('Content-Type: text/html; charset=UTF-8');
require $partialPath;
exit;
