<?php
declare(strict_types=1);

require_once __DIR__ . '/modules/tools/bootstrap.php';

ensureToolsSessionStarted();
cleanupExpiredToolLaunches();

$launchToken = trim((string) ($_GET['launch'] ?? ''));
$asset = trim((string) ($_GET['asset'] ?? ''));
$launchPayload = $launchToken !== '' ? findToolLaunch($launchToken) : null;
$tool = is_array($launchPayload['tool'] ?? null) ? $launchPayload['tool'] : null;

if (!$tool) {
    http_response_code(404);
    echo 'Asset no disponible.';
    exit;
}

if ($asset === '' || preg_match('/^[A-Za-z0-9._-]+$/', $asset) !== 1) {
    http_response_code(400);
    echo 'Asset invalido.';
    exit;
}

$workspaceRoot = realpath(__DIR__) ?: __DIR__;
$appFolder = trim((string) ($tool['app_folder'] ?? ''), '/');

if ($appFolder === '' || !isSafeRelativePath($appFolder)) {
    http_response_code(404);
    echo 'Asset no disponible.';
    exit;
}

$appDirectory = realpath($workspaceRoot . DIRECTORY_SEPARATOR . $appFolder);

if ($appDirectory === false || !str_starts_with($appDirectory, $workspaceRoot . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    echo 'Asset no disponible.';
    exit;
}

$assetPath = realpath($appDirectory . DIRECTORY_SEPARATOR . $asset);

if ($assetPath === false || !str_starts_with($assetPath, $appDirectory . DIRECTORY_SEPARATOR) || !is_file($assetPath)) {
    http_response_code(404);
    echo 'Asset no disponible.';
    exit;
}

$extension = strtolower(pathinfo($assetPath, PATHINFO_EXTENSION));
$contentType = match ($extension) {
    'css' => 'text/css; charset=UTF-8',
    'js' => 'application/javascript; charset=UTF-8',
    'json' => 'application/json; charset=UTF-8',
    'svg' => 'image/svg+xml',
    'png' => 'image/png',
    'jpg', 'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    default => 'application/octet-stream',
};

header('Content-Type: ' . $contentType);
header('Cache-Control: private, max-age=300');
readfile($assetPath);
exit;
