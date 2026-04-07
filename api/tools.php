<?php
declare(strict_types=1);

use AiScaler\Tools\ToolRepository;

require_once __DIR__ . '/../lib/supabase_api.php';
require_once __DIR__ . '/../modules/tools/bootstrap.php';

ensureToolsSessionStarted();
cleanupExpiredToolLaunches();

header('Content-Type: application/json; charset=UTF-8');

$repository = new ToolRepository();

try {
    $action = resolveToolsAction();

    if ($action === 'catalog') {
        [$token] = requireAuthenticatedToolsUser();
        $categoryKey = trim((string) ($_GET['category_key'] ?? ''));

        if ($categoryKey === '') {
            throw new InvalidArgumentException('Selecciona una categoria de herramientas.');
        }

        $tools = array_map('sanitizeToolForCatalog', $repository->listTools($token, $categoryKey));

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
        [$token] = requireAdminToolsUser();
        $tools = array_map(static function (array $tool): array {
            return sanitizeToolForAdmin(mergeToolWithPrivateConfig(
                $tool,
                getToolLaunchConfig((string) ($tool['slug'] ?? ''))
            ));
        }, $repository->listTools($token));

        sendToolsJson([
            'success' => true,
            'data' => [
                'categories' => $repository->listCategories($token),
                'tools' => $tools,
            ],
        ]);
    }

    if ($action === 'save') {
        [$token] = requireAdminToolsUser();
        $payload = validateToolPayload(readToolsJsonPayload());
        $previousTool = isset($payload['id']) ? $repository->findById($token, (string) $payload['id']) : null;
        $savedTool = $repository->save($token, sanitizeToolPayloadForDatabase($payload));
        saveToolLaunchConfig(
            (string) ($savedTool['slug'] ?? ''),
            extractPrivateToolConfig($payload),
            is_array($previousTool) ? (string) ($previousTool['slug'] ?? '') : null
        );
        $savedTool = mergeToolWithPrivateConfig($savedTool, getToolLaunchConfig((string) ($savedTool['slug'] ?? '')));

        sendToolsJson([
            'success' => true,
            'message' => 'Herramienta guardada correctamente.',
            'data' => [
                'tool' => sanitizeToolForAdmin($savedTool),
            ],
        ]);
    }

    if ($action === 'delete') {
        [$token] = requireAdminToolsUser();
        $payload = readToolsJsonPayload();
        $id = trim((string) ($payload['id'] ?? ''));

        if ($id === '') {
            throw new InvalidArgumentException('No encontramos la herramienta que intentas borrar.');
        }

        $existing = $repository->findById($token, $id);

        if (!$existing) {
            throw new InvalidArgumentException('La herramienta ya no existe.');
        }

        $repository->delete($token, $id);
        deleteToolLaunchConfig((string) ($existing['slug'] ?? ''));

        sendToolsJson([
            'success' => true,
            'message' => 'Herramienta eliminada correctamente.',
        ]);
    }

    if ($action === 'launch') {
        [$token, $user] = requireAuthenticatedToolsUser();
        $payload = readToolsJsonPayload();
        $slug = trim((string) ($payload['slug'] ?? ''));
        $sectionId = trim((string) ($payload['section_id'] ?? ''));

        if ($slug === '') {
            throw new InvalidArgumentException('Selecciona una herramienta valida.');
        }

        $tool = $repository->findBySlug($token, $slug);

        if (!$tool) {
            $builtinTool = findBuiltinToolBySlug($slug);
            $tool = is_array($builtinTool) ? sanitizeToolForCatalog($builtinTool) : null;
        }

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
