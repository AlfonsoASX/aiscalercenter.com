<?php
declare(strict_types=1);

function ensureToolsSessionStarted(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('aiscaler_tools');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => isToolsHttpsRequest(),
    ]);
    session_start();
}

function isToolsHttpsRequest(): bool
{
    if (isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        return strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
    }

    return false;
}

function cleanupExpiredToolLaunches(): void
{
    $launches = $_SESSION['aiscaler_tool_launches'] ?? [];

    if (!is_array($launches)) {
        $_SESSION['aiscaler_tool_launches'] = [];
        return;
    }

    $now = time();

    $_SESSION['aiscaler_tool_launches'] = array_filter($launches, static function ($payload) use ($now): bool {
        if (!is_array($payload)) {
            return false;
        }

        $createdAt = (int) ($payload['created_at'] ?? 0);

        return $createdAt > 0 && ($now - $createdAt) <= 28800;
    });
}

function cleanupExpiredToolBrowsers(): void
{
    $browsers = $_SESSION['aiscaler_tool_browsers'] ?? [];

    if (!is_array($browsers)) {
        $_SESSION['aiscaler_tool_browsers'] = [];
        return;
    }

    $now = time();

    $_SESSION['aiscaler_tool_browsers'] = array_filter($browsers, static function ($payload) use ($now): bool {
        if (!is_array($payload)) {
            return false;
        }

        $createdAt = (int) ($payload['created_at'] ?? 0);

        return $createdAt > 0 && ($now - $createdAt) <= 28800;
    });
}

function rememberToolsServerAuth(array $payload): void
{
    $_SESSION['aiscaler_tools_auth'] = [
        'access_token' => (string) ($payload['access_token'] ?? ''),
        'user_id' => (string) ($payload['user_id'] ?? ''),
        'email' => (string) ($payload['email'] ?? ''),
        'created_at' => time(),
    ];
}

function getToolsServerAuth(): ?array
{
    $payload = $_SESSION['aiscaler_tools_auth'] ?? null;

    return is_array($payload) ? $payload : null;
}

function clearToolsServerAuth(): void
{
    unset($_SESSION['aiscaler_tools_auth']);
}

function rememberToolLaunch(string $launchToken, array $payload): void
{
    cleanupExpiredToolLaunches();

    if (!isset($_SESSION['aiscaler_tool_launches']) || !is_array($_SESSION['aiscaler_tool_launches'])) {
        $_SESSION['aiscaler_tool_launches'] = [];
    }

    $_SESSION['aiscaler_tool_launches'][$launchToken] = $payload;
}

function rememberToolBrowser(string $browserToken, array $payload): void
{
    cleanupExpiredToolBrowsers();

    if (!isset($_SESSION['aiscaler_tool_browsers']) || !is_array($_SESSION['aiscaler_tool_browsers'])) {
        $_SESSION['aiscaler_tool_browsers'] = [];
    }

    $_SESSION['aiscaler_tool_browsers'][$browserToken] = $payload;
}

function findToolLaunch(string $launchToken): ?array
{
    cleanupExpiredToolLaunches();

    $launches = $_SESSION['aiscaler_tool_launches'] ?? [];

    if (!is_array($launches)) {
        return null;
    }

    $payload = $launches[$launchToken] ?? null;

    return is_array($payload) ? $payload : null;
}

function findToolBrowser(string $browserToken): ?array
{
    cleanupExpiredToolBrowsers();

    $browsers = $_SESSION['aiscaler_tool_browsers'] ?? [];

    if (!is_array($browsers)) {
        return null;
    }

    $payload = $browsers[$browserToken] ?? null;

    return is_array($payload) ? $payload : null;
}

function resolveToolsAction(): string
{
    return trim((string) ($_GET['action'] ?? 'catalog'));
}

function readToolsJsonPayload(): array
{
    $rawInput = file_get_contents('php://input');

    if ($rawInput === false || trim($rawInput) === '') {
        return $_POST;
    }

    $decoded = json_decode($rawInput, true);

    return is_array($decoded) ? $decoded : [];
}

function resolveToolsBearerToken(): string
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authorizationHeader = '';

    foreach ($headers as $headerName => $headerValue) {
        if (strtolower((string) $headerName) === 'authorization') {
            $authorizationHeader = (string) $headerValue;
            break;
        }
    }

    if ($authorizationHeader === '' && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authorizationHeader = (string) $_SERVER['HTTP_AUTHORIZATION'];
    }

    if (preg_match('/Bearer\s+(.+)/i', $authorizationHeader, $matches) !== 1) {
        return '';
    }

    return trim((string) ($matches[1] ?? ''));
}

function authenticateToolsRequest(string $token): array
{
    if ($token === '') {
        throw new RuntimeException('Debes iniciar sesion para usar las herramientas.');
    }

    try {
        $response = supabaseAuthRequest('GET', 'user', [], $token);
    } catch (Throwable) {
        throw new RuntimeException('No se pudo validar la sesion actual.');
    }

    $data = $response['data'] ?? null;

    if (!is_array($data) || !isset($data['id'])) {
        throw new RuntimeException('La sesion actual ya no es valida.');
    }

    return $data;
}

function requireAuthenticatedToolsUser(): array
{
    $token = resolveToolsBearerToken();

    return [$token, authenticateToolsRequest($token)];
}

function requireStoredToolsServerUser(): array
{
    $serverAuth = getToolsServerAuth();
    $token = trim((string) ($serverAuth['access_token'] ?? ''));

    if ($token === '') {
        throw new RuntimeException('No encontramos la sesion protegida de la herramienta. Vuelve a abrirla desde el panel.');
    }

    return [$token, authenticateToolsRequest($token)];
}

function isToolsAdminUser(array $user): bool
{
    $email = strtolower(trim((string) ($user['email'] ?? '')));
    $panelConfig = require __DIR__ . '/../../config/panel.php';
    $bootstrapAdmins = array_map('strtolower', array_map('trim', (array) ($panelConfig['bootstrap_admins'] ?? [])));

    if ($email !== '' && in_array($email, $bootstrapAdmins, true)) {
        return true;
    }

    $appMetadata = is_array($user['app_metadata'] ?? null) ? $user['app_metadata'] : [];
    $role = (string) ($appMetadata['role'] ?? '');
    $roles = is_array($appMetadata['roles'] ?? null) ? $appMetadata['roles'] : [];

    return $role === 'admin' || in_array('admin', $roles, true);
}

function sendToolsJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function isMissingToolsTable(Throwable $exception): bool
{
    return false;
}

function normalizeToolsExceptionMessage(Throwable $exception): string
{
    return $exception->getMessage();
}

function findToolCategory(array $categories, string $categoryKey): ?array
{
    foreach ($categories as $category) {
        if (!is_array($category)) {
            continue;
        }

        if ((string) ($category['key'] ?? '') === $categoryKey) {
            return $category;
        }
    }

    return null;
}

function sanitizeToolForCatalog(array $tool): array
{
    return [
        'id' => (string) ($tool['id'] ?? ''),
        'slug' => (string) ($tool['slug'] ?? ''),
        'category_key' => (string) ($tool['category_key'] ?? ''),
        'title' => (string) ($tool['title'] ?? ''),
        'description' => (string) ($tool['description'] ?? ''),
        'image_url' => (string) ($tool['image_url'] ?? ''),
        'tutorial_youtube_url' => (string) ($tool['tutorial_youtube_url'] ?? ''),
        'sort_order' => (int) ($tool['sort_order'] ?? 0),
    ];
}

function toolsWorkspaceRoot(): string
{
    return realpath(__DIR__ . '/../..') ?: dirname(__DIR__, 2);
}

function toolsAppsRoot(): string
{
    return toolsWorkspaceRoot() . DIRECTORY_SEPARATOR . 'apps';
}

function listToolCategories(): array
{
    $panelConfig = require __DIR__ . '/../../config/panel.php';
    $items = (array) ($panelConfig['menus']['admin'] ?? $panelConfig['menus']['regular'] ?? []);
    $categories = [];

    foreach ($items as $index => $item) {
        if (!is_array($item)) {
            continue;
        }

        $categoryKey = trim((string) ($item['tool_category_key'] ?? ''));

        if ($categoryKey === '' || isset($categories[$categoryKey])) {
            continue;
        }

        $categories[$categoryKey] = [
            'key' => $categoryKey,
            'label' => (string) ($item['section_title'] ?? $item['label'] ?? ucfirst($categoryKey)),
            'description' => (string) ($item['description'] ?? ''),
            'sort_order' => $index * 10,
        ];
    }

    foreach (listAppToolDefinitions() as $tool) {
        $categoryKey = trim((string) ($tool['category_key'] ?? ''));

        if ($categoryKey === '' || isset($categories[$categoryKey])) {
            continue;
        }

        $categories[$categoryKey] = [
            'key' => $categoryKey,
            'label' => humanizeToolCategoryKey($categoryKey),
            'description' => '',
            'sort_order' => 1000 + count($categories),
        ];
    }

    usort($categories, static function (array $left, array $right): int {
        return ((int) ($left['sort_order'] ?? 0)) <=> ((int) ($right['sort_order'] ?? 0));
    });

    return array_values($categories);
}

function humanizeToolCategoryKey(string $categoryKey): string
{
    $normalized = trim(str_replace(['-', '_'], ' ', $categoryKey));

    if ($normalized === '') {
        return 'Herramientas';
    }

    return ucwords($normalized);
}

function listAppToolDefinitions(): array
{
    static $tools = null;

    if (is_array($tools)) {
        return $tools;
    }

    $appsRoot = toolsAppsRoot();

    if (!is_dir($appsRoot)) {
        $tools = [];
        return $tools;
    }

    $metadataFiles = glob($appsRoot . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'tool.php') ?: [];
    $indexedTools = [];

    foreach ($metadataFiles as $metadataFile) {
        if (!is_file($metadataFile)) {
            continue;
        }

        $appDirectory = dirname($metadataFile);

        try {
            $definition = require $metadataFile;
        } catch (Throwable) {
            continue;
        }

        if (!is_array($definition)) {
            continue;
        }

        $tool = normalizeAppToolDefinition($definition, $appDirectory);

        if (!is_array($tool) || !(bool) ($tool['is_active'] ?? true) || isRetiredToolSlug((string) ($tool['slug'] ?? ''))) {
            continue;
        }

        $indexedTools[(string) $tool['slug']] = $tool;
    }

    $tools = array_values($indexedTools);

    usort($tools, static function (array $left, array $right): int {
        $leftCategory = (string) ($left['category_key'] ?? '');
        $rightCategory = (string) ($right['category_key'] ?? '');

        if ($leftCategory !== $rightCategory) {
            return strcmp($leftCategory, $rightCategory);
        }

        $leftOrder = (int) ($left['sort_order'] ?? 0);
        $rightOrder = (int) ($right['sort_order'] ?? 0);

        if ($leftOrder === $rightOrder) {
            return strcmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        }

        return $leftOrder <=> $rightOrder;
    });

    return $tools;
}

function normalizeAppToolDefinition(array $definition, string $appDirectory): ?array
{
    $workspaceRoot = toolsWorkspaceRoot();
    $realAppDirectory = realpath($appDirectory) ?: $appDirectory;
    $normalizedWorkspaceRoot = rtrim(str_replace('\\', '/', $workspaceRoot), '/');
    $normalizedAppDirectory = rtrim(str_replace('\\', '/', $realAppDirectory), '/');

    if (!str_starts_with($normalizedAppDirectory, $normalizedWorkspaceRoot . '/')) {
        return null;
    }

    $relativeAppFolder = ltrim(substr($normalizedAppDirectory, strlen($normalizedWorkspaceRoot)), '/');
    $slug = normalizeToolSlug((string) ($definition['slug'] ?? basename($normalizedAppDirectory)));
    $categoryKey = trim((string) ($definition['category_key'] ?? ''));
    $title = trim((string) ($definition['title'] ?? ''));

    if ($slug === '' || $categoryKey === '' || $title === '') {
        return null;
    }

    $launchMode = trim((string) ($definition['launch_mode'] ?? 'php_folder'));

    if (!in_array($launchMode, ['php_folder', 'panel_module'], true)) {
        $launchMode = 'php_folder';
    }

    $panelModuleKey = trim((string) ($definition['panel_module_key'] ?? ''));
    $appFolder = trim((string) ($definition['app_folder'] ?? $relativeAppFolder), '/');
    $entryFile = ltrim(trim((string) ($definition['entry_file'] ?? 'index.php')), '/');

    if ($entryFile === '') {
        $entryFile = 'index.php';
    }

    if ($launchMode === 'panel_module' && $panelModuleKey === '') {
        return null;
    }

    if ($appFolder !== '' && !isSafeRelativePath($appFolder)) {
        return null;
    }

    if (!isSafeRelativePath($entryFile)) {
        return null;
    }

    if ($launchMode === 'php_folder' && ($appFolder === '' || !is_file($workspaceRoot . DIRECTORY_SEPARATOR . $appFolder . DIRECTORY_SEPARATOR . $entryFile))) {
        return null;
    }

    $imageUrl = trim((string) ($definition['image_url'] ?? ''));
    $youtubeUrl = trim((string) ($definition['tutorial_youtube_url'] ?? ''));

    if ($imageUrl !== '' && !isSafeToolImageUrl($imageUrl)) {
        $imageUrl = '';
    }

    if ($youtubeUrl !== '' && filter_var($youtubeUrl, FILTER_VALIDATE_URL) === false) {
        $youtubeUrl = '';
    }

    return [
        'id' => (string) ($definition['id'] ?? ''),
        'slug' => $slug,
        'category_key' => $categoryKey,
        'title' => $title,
        'description' => trim((string) ($definition['description'] ?? '')),
        'image_url' => $imageUrl,
        'tutorial_youtube_url' => $youtubeUrl,
        'sort_order' => (int) ($definition['sort_order'] ?? 0),
        'is_active' => filter_var($definition['is_active'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
        'admin_only' => filter_var($definition['admin_only'] ?? false, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
        'launch_mode' => $launchMode,
        'panel_module_key' => $panelModuleKey,
        'app_folder' => $appFolder,
        'entry_file' => $entryFile,
        'hide_sidebar' => filter_var($definition['hide_sidebar'] ?? false, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
    ];
}

function listAppToolsByCategory(string $categoryKey, ?array $user = null): array
{
    $normalizedCategoryKey = trim($categoryKey);

    if ($normalizedCategoryKey === '') {
        return [];
    }

    return array_values(array_filter(listAppToolDefinitions(), static function (array $tool) use ($normalizedCategoryKey, $user): bool {
        if ((string) ($tool['category_key'] ?? '') !== $normalizedCategoryKey) {
            return false;
        }

        if ((bool) ($tool['admin_only'] ?? false) && is_array($user) && !isToolsAdminUser($user)) {
            return false;
        }

        return true;
    }));
}

function findAppToolBySlug(string $slug, ?array $user = null): ?array
{
    $normalizedSlug = trim($slug);

    if ($normalizedSlug === '') {
        return null;
    }

    foreach (listAppToolDefinitions() as $tool) {
        if ((string) ($tool['slug'] ?? '') !== $normalizedSlug) {
            continue;
        }

        if ((bool) ($tool['admin_only'] ?? false) && is_array($user) && !isToolsAdminUser($user)) {
            return null;
        }

        return $tool;
    }

    return null;
}

function retiredToolSlugs(): array
{
    return [
        'validar-mercado',
        'investigar-amazon',
    ];
}

function isRetiredToolSlug(string $slug): bool
{
    return in_array(trim($slug), retiredToolSlugs(), true);
}

function sanitizeToolForLaunch(array $tool, string $returnUrl): array
{
    return [
        'id' => (string) ($tool['id'] ?? ''),
        'slug' => (string) ($tool['slug'] ?? ''),
        'category_key' => (string) ($tool['category_key'] ?? ''),
        'title' => (string) ($tool['title'] ?? ''),
        'description' => (string) ($tool['description'] ?? ''),
        'image_url' => (string) ($tool['image_url'] ?? ''),
        'tutorial_youtube_url' => (string) ($tool['tutorial_youtube_url'] ?? ''),
        'launch_mode' => (string) ($tool['launch_mode'] ?? 'php_folder'),
        'panel_module_key' => (string) ($tool['panel_module_key'] ?? ''),
        'app_folder' => (string) ($tool['app_folder'] ?? ''),
        'entry_file' => (string) ($tool['entry_file'] ?? 'index.php'),
        'hide_sidebar' => (bool) ($tool['hide_sidebar'] ?? false),
        'return_url' => $returnUrl,
    ];
}

function mergeToolWithPrivateConfig(array $tool, ?array $privateConfig): array
{
    return [
        ...$tool,
        'launch_mode' => (string) ($privateConfig['launch_mode'] ?? 'php_folder'),
        'panel_module_key' => (string) ($privateConfig['panel_module_key'] ?? ''),
        'app_folder' => (string) ($privateConfig['app_folder'] ?? ''),
        'entry_file' => (string) ($privateConfig['entry_file'] ?? 'index.php'),
        'hide_sidebar' => (bool) ($privateConfig['hide_sidebar'] ?? false),
    ];
}

function normalizeToolSlug(string $value): string
{
    $normalized = strtolower(trim($value));
    $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized) ?: $normalized;
    $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '';
    $normalized = trim($normalized, '-');

    return $normalized;
}

function isSafeRelativePath(string $value): bool
{
    $trimmed = trim($value);

    if ($trimmed === '' || str_contains($trimmed, '..') || str_starts_with($trimmed, '/')) {
        return false;
    }

    return preg_match('/^[A-Za-z0-9._\\/-]+$/', $trimmed) === 1;
}

function isSafeToolImageUrl(string $value): bool
{
    $trimmed = trim($value);

    if ($trimmed === '') {
        return true;
    }

    if (preg_match('/[\\x00-\\x1F\\x7F]/', $trimmed) === 1) {
        return false;
    }

    if (filter_var($trimmed, FILTER_VALIDATE_URL) !== false) {
        $scheme = strtolower((string) (parse_url($trimmed, PHP_URL_SCHEME) ?? ''));

        return in_array($scheme, ['http', 'https'], true);
    }

    if (str_starts_with($trimmed, '//') || str_contains($trimmed, '..')) {
        return false;
    }

    return preg_match('/^\\/?[A-Za-z0-9._~\\/-]+(?:\\?[A-Za-z0-9._~\\/%=&-]+)?$/', $trimmed) === 1;
}

function buildToolsLaunchUrl(string $launchToken): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/api/tools.php'));
    $scriptDirectory = rtrim(dirname($scriptName), '/');
    $basePath = preg_replace('#/api$#', '', $scriptDirectory) ?: '';

    return ($basePath === '' ? '' : $basePath) . '/tool.php?launch=' . rawurlencode($launchToken);
}

function buildToolsBrowserUrl(string $browserToken): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/api/tools.php'));
    $scriptDirectory = rtrim(dirname($scriptName), '/');
    $basePath = preg_replace('#/api$#', '', $scriptDirectory) ?: '';

    return ($basePath === '' ? '' : $basePath) . '/tools-browser.php?browse=' . rawurlencode($browserToken);
}

function buildToolsPanelUrl(?string $sectionId = null): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/api/tools.php'));
    $scriptDirectory = rtrim(dirname($scriptName), '/');
    $basePath = preg_replace('#/api$#', '', $scriptDirectory) ?: '';
    $panelUrl = ($basePath === '' ? '' : $basePath) . '/index.php?view=app';
    $panelConfig = require __DIR__ . '/../../config/panel.php';
    $validIds = [];

    foreach ((array) ($panelConfig['menus']['admin'] ?? []) as $item) {
        if (is_array($item) && isset($item['id'])) {
            $validIds[] = (string) $item['id'];
        }
    }

    $validIds[] = (string) (($panelConfig['dashboard']['id'] ?? 'inicio'));
    $validIds[] = (string) (($panelConfig['account_section']['id'] ?? 'configuracion'));
    $candidate = trim((string) ($sectionId ?? ''));

    if ($candidate === '' || !in_array($candidate, $validIds, true)) {
        $candidate = (string) (($panelConfig['dashboard']['id'] ?? 'inicio'));
    }

    return $panelUrl . '#' . $candidate;
}

function redirectToolsWithClientFlash(string $targetUrl, string $message, string $type = 'error'): never
{
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $safeUrl = json_encode($targetUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $safeMessage = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $safeType = json_encode($type, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Redirigiendo...</title></head><body>';
    echo '<script>';
    echo "try { sessionStorage.setItem('aiscaler_flash', {$safeMessage}); sessionStorage.setItem('aiscaler_flash_type', {$safeType}); } catch (error) {}";
    echo "window.location.replace({$safeUrl});";
    echo '</script>';
    echo '<p>Redirigiendo al panel...</p>';
    echo '</body></html>';
    exit;
}

function getToolLaunchConfig(string $slug): ?array
{
    $tool = findAppToolBySlug($slug);

    if (!is_array($tool)) {
        return null;
    }

    return [
        'launch_mode' => (string) ($tool['launch_mode'] ?? 'php_folder'),
        'panel_module_key' => (string) ($tool['panel_module_key'] ?? ''),
        'app_folder' => (string) ($tool['app_folder'] ?? ''),
        'entry_file' => (string) ($tool['entry_file'] ?? 'index.php'),
        'hide_sidebar' => (bool) ($tool['hide_sidebar'] ?? false),
    ];
}
