<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/supabase_api.php';
require_once __DIR__ . '/../modules/tools/bootstrap.php';

ensureToolsSessionStarted();
cleanupExpiredToolLaunches();

header('Content-Type: application/json; charset=UTF-8');

try {
    $action = resolveToolsAction();

    if ($action === 'catalog') {
        [, $user] = requireAuthenticatedToolsUser();
        $categoryKey = trim((string) ($_GET['category_key'] ?? ''));

        if ($categoryKey === '') {
            throw new InvalidArgumentException('Selecciona una categoria de herramientas.');
        }

        $tools = array_map('sanitizeToolForCatalog', listAppToolsByCategory($categoryKey, $user));

        sendToolsJson([
            'success' => true,
            'data' => [
                'category' => [
                    'key' => $categoryKey,
                ],
                'tools' => array_values($tools),
            ],
        ]);
    }

    if ($action === 'browse') {
        [$token, $user] = requireAuthenticatedToolsUser();
        $payload = readToolsJsonPayload();
        $categoryKey = trim((string) ($payload['category_key'] ?? $_GET['category_key'] ?? ''));
        $sectionId = trim((string) ($payload['section_id'] ?? $_GET['section_id'] ?? ''));

        if ($categoryKey === '') {
            throw new InvalidArgumentException('Selecciona una categoria de herramientas.');
        }

        $browserToken = bin2hex(random_bytes(24));

        rememberToolBrowser($browserToken, [
            'access_token' => $token,
            'user_id' => (string) ($user['id'] ?? ''),
            'category_key' => $categoryKey,
            'section_id' => $sectionId,
            'created_at' => time(),
        ]);

        sendToolsJson([
            'success' => true,
            'data' => [
                'browser_url' => buildToolsBrowserUrl($browserToken),
            ],
        ]);
    }

    if ($action === 'admin_bootstrap') {
        sendToolsJson([
            'success' => false,
            'message' => 'El CRUD de herramientas fue reemplazado por archivos apps/*/tool.php.',
        ], 410);
    }

    if ($action === 'save') {
        sendToolsJson([
            'success' => false,
            'message' => 'Las herramientas ya no se guardan por API. Edita el archivo tool.php dentro de la carpeta de cada app.',
        ], 410);
    }

    if ($action === 'delete') {
        sendToolsJson([
            'success' => false,
            'message' => 'Las herramientas ya no se eliminan por API. Quita o desactiva el tool.php de la app correspondiente.',
        ], 410);
    }

    if ($action === 'launch') {
        [$token, $user] = requireAuthenticatedToolsUser();
        $payload = readToolsJsonPayload();
        $slug = trim((string) ($payload['slug'] ?? ''));
        $sectionId = trim((string) ($payload['section_id'] ?? ''));
        $projectId = trim((string) ($payload['project_id'] ?? ''));
        $projectName = trim((string) ($payload['project_name'] ?? ''));
        $projectLogoUrl = trim((string) ($payload['project_logo_url'] ?? ''));

        if ($slug === '') {
            throw new InvalidArgumentException('Selecciona una herramienta valida.');
        }

        if (isRetiredToolSlug($slug)) {
            throw new InvalidArgumentException('La herramienta solicitada ya no esta disponible.');
        }

        $tool = findAppToolBySlug($slug, $user);

        if (!$tool) {
            throw new InvalidArgumentException('La herramienta solicitada ya no esta disponible.');
        }

        $privateConfig = getToolLaunchConfig((string) ($tool['slug'] ?? ''));

        if (!is_array($privateConfig)) {
            throw new RuntimeException('La herramienta no tiene configurada su ruta protegida en PHP.');
        }

        $returnUrl = buildToolsPanelUrl($sectionId);
        $launchToken = bin2hex(random_bytes(24));

        rememberToolLaunch($launchToken, [
            'tool' => sanitizeToolForLaunch(mergeToolWithPrivateConfig($tool, $privateConfig), $returnUrl),
            'user_id' => (string) ($user['id'] ?? ''),
            'access_token' => $token,
            'project' => [
                'id' => $projectId,
                'name' => $projectName,
                'logo_url' => $projectLogoUrl,
            ],
            'user' => [
                'email' => (string) ($user['email'] ?? ''),
                'display_name' => trim((string) ($user['user_metadata']['full_name'] ?? '')) ?: (string) ($user['email'] ?? 'Usuario'),
                'role' => isToolsAdminUser($user) ? 'admin' : 'regular',
            ],
            'created_at' => time(),
        ]);

        sendToolsJson([
            'success' => true,
            'data' => [
                'launch_token' => $launchToken,
                'launch_url' => buildToolsLaunchUrl($launchToken),
                'launch_mode' => (string) ($privateConfig['launch_mode'] ?? 'php_folder'),
                'panel_module_key' => (string) ($privateConfig['panel_module_key'] ?? ''),
                'tool' => sanitizeToolForCatalog($tool),
            ],
        ]);
    }

    sendToolsJson([
        'success' => false,
        'message' => 'Accion no soportada.',
    ], 400);
} catch (InvalidArgumentException $exception) {
    sendToolsJson([
        'success' => false,
        'message' => $exception->getMessage(),
    ], 422);
} catch (RuntimeException $exception) {
    $status = str_contains(strtolower($exception->getMessage()), 'administrador') ? 403 : 401;

    sendToolsJson([
        'success' => false,
        'message' => normalizeToolsExceptionMessage($exception),
        'setup_required' => isMissingToolsTable($exception),
    ], $status);
} catch (Throwable $exception) {
    sendToolsJson([
        'success' => false,
        'message' => normalizeToolsExceptionMessage($exception),
        'setup_required' => isMissingToolsTable($exception),
    ], 500);
}
