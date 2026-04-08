<?php
declare(strict_types=1);

require_once __DIR__ . '/ToolRepository.php';

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

function requireAdminToolsUser(): array
{
    [$token, $user] = requireAuthenticatedToolsUser();

    if (!isToolsAdminUser($user)) {
        throw new RuntimeException('Solo un administrador puede gestionar herramientas.');
    }

    return [$token, $user];
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
    $message = strtolower($exception->getMessage());

    return str_contains($message, 'tool_categories') || str_contains($message, 'tools');
}

function normalizeToolsExceptionMessage(Throwable $exception): string
{
    if (isMissingToolsTable($exception)) {
        return 'La estructura de herramientas aun no existe. Ejecuta supabase/tools_schema.sql en Supabase.';
    }

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

function builtinToolDefinitions(): array
{
    return [
        [
            'id' => '',
            'slug' => 'investigar-google',
            'category_key' => 'investigar',
            'title' => 'Google',
            'description' => 'Consulta terminos relacionados y senales de busqueda desde Google.',
            'image_url' => '',
            'tutorial_youtube_url' => '',
            'sort_order' => 10,
            'is_active' => true,
            'admin_only' => false,
        ],
        [
            'id' => '',
            'slug' => 'investigar-youtube',
            'category_key' => 'investigar',
            'title' => 'YouTube',
            'description' => 'Consulta senales y terminos relacionados desde YouTube.',
            'image_url' => '',
            'tutorial_youtube_url' => '',
            'sort_order' => 20,
            'is_active' => true,
            'admin_only' => false,
        ],
        [
            'id' => '',
            'slug' => 'investigar-mercado-libre',
            'category_key' => 'investigar',
            'title' => 'Mercado Libre',
            'description' => 'Consulta senales de demanda y terminos relacionados desde Mercado Libre.',
            'image_url' => '',
            'tutorial_youtube_url' => '',
            'sort_order' => 30,
            'is_active' => true,
            'admin_only' => false,
        ],
        [
            'id' => '',
            'slug' => 'investigar-amazon',
            'category_key' => 'investigar',
            'title' => 'Amazon',
            'description' => 'Consulta senales y terminos relacionados desde Amazon.',
            'image_url' => '',
            'tutorial_youtube_url' => '',
            'sort_order' => 40,
            'is_active' => true,
            'admin_only' => false,
        ],
        [
            'id' => '',
            'slug' => 'generador-formularios',
            'category_key' => 'disenar',
            'title' => 'Generador de formularios',
            'description' => 'Crea formularios publicos, compartelos sin login y guarda sus respuestas como JSON.',
            'image_url' => '',
            'tutorial_youtube_url' => '',
            'sort_order' => 10,
            'is_active' => true,
            'admin_only' => false,
        ],
        [
            'id' => '',
            'slug' => 'creador-landing-pages',
            'category_key' => 'disenar',
            'title' => 'Creador de landing pages',
            'description' => 'Construye landing pages visuales con bloques editables, vista en vivo y publicacion sin login.',
            'image_url' => '',
            'tutorial_youtube_url' => '',
            'sort_order' => 20,
            'is_active' => true,
            'admin_only' => false,
        ],
        [
            'id' => '',
            'slug' => 'seguimiento-clientes',
            'category_key' => 'ejecutar',
            'title' => 'Seguimiento de Clientes',
            'description' => 'Gestiona prospectos con un tablero Kanban, panel lateral y entrada automatica de leads por webhook.',
            'image_url' => '',
            'tutorial_youtube_url' => '',
            'sort_order' => 20,
            'is_active' => true,
            'admin_only' => false,
        ],
        [
            'id' => '',
            'slug' => 'creacion-bots-whatsapp',
            'category_key' => 'ejecutar',
            'title' => 'Creacion de bots de WhatsApp',
            'description' => 'Configura bots conversacionales, administra la bandeja humana y prepara plantillas para seguimiento comercial.',
            'image_url' => '',
            'tutorial_youtube_url' => '',
            'sort_order' => 30,
            'is_active' => true,
            'admin_only' => false,
        ],
    ];
}

function listBuiltinToolsByCategory(string $categoryKey): array
{
    $normalizedCategoryKey = trim($categoryKey);

    if ($normalizedCategoryKey === '') {
        return [];
    }

    return array_values(array_filter(builtinToolDefinitions(), static function (array $tool) use ($normalizedCategoryKey): bool {
        return (string) ($tool['category_key'] ?? '') === $normalizedCategoryKey;
    }));
}

function findBuiltinToolBySlug(string $slug): ?array
{
    $normalizedSlug = trim($slug);

    if ($normalizedSlug === '') {
        return null;
    }

    foreach (builtinToolDefinitions() as $tool) {
        if ((string) ($tool['slug'] ?? '') === $normalizedSlug) {
            return $tool;
        }
    }

    return null;
}

function sanitizeToolForAdmin(array $tool): array
{
    return [
        'id' => (string) ($tool['id'] ?? ''),
        'category_key' => (string) ($tool['category_key'] ?? ''),
        'slug' => (string) ($tool['slug'] ?? ''),
        'title' => (string) ($tool['title'] ?? ''),
        'description' => (string) ($tool['description'] ?? ''),
        'image_url' => (string) ($tool['image_url'] ?? ''),
        'tutorial_youtube_url' => (string) ($tool['tutorial_youtube_url'] ?? ''),
        'launch_mode' => (string) ($tool['launch_mode'] ?? 'php_folder'),
        'panel_module_key' => (string) ($tool['panel_module_key'] ?? ''),
        'app_folder' => (string) ($tool['app_folder'] ?? ''),
        'entry_file' => (string) ($tool['entry_file'] ?? 'index.php'),
        'sort_order' => (int) ($tool['sort_order'] ?? 0),
        'is_active' => (bool) ($tool['is_active'] ?? true),
        'admin_only' => (bool) ($tool['admin_only'] ?? false),
    ];
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

function extractPrivateToolConfig(array $payload): array
{
    $config = [
        'launch_mode' => (string) ($payload['launch_mode'] ?? 'php_folder'),
        'panel_module_key' => (string) ($payload['panel_module_key'] ?? ''),
        'app_folder' => (string) ($payload['app_folder'] ?? ''),
        'entry_file' => (string) ($payload['entry_file'] ?? 'index.php'),
    ];

    if (array_key_exists('hide_sidebar', $payload)) {
        $config['hide_sidebar'] = filter_var($payload['hide_sidebar'], FILTER_VALIDATE_BOOL);
    }

    return $config;
}

function sanitizeToolPayloadForDatabase(array $payload): array
{
    $databasePayload = $payload;

    unset(
        $databasePayload['launch_mode'],
        $databasePayload['panel_module_key'],
        $databasePayload['app_folder'],
        $databasePayload['entry_file'],
        $databasePayload['hide_sidebar']
    );

    return $databasePayload;
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

function validateToolPayload(array $payload): array
{
    $title = trim((string) ($payload['title'] ?? ''));
    $slug = normalizeToolSlug((string) ($payload['slug'] ?? ''));
    $categoryKey = trim((string) ($payload['category_key'] ?? ''));
    $description = trim((string) ($payload['description'] ?? ''));
    $imageUrl = trim((string) ($payload['image_url'] ?? ''));
    $youtubeUrl = trim((string) ($payload['tutorial_youtube_url'] ?? ''));
    $launchMode = trim((string) ($payload['launch_mode'] ?? 'php_folder'));
    $panelModuleKey = trim((string) ($payload['panel_module_key'] ?? ''));
    $appFolder = trim((string) ($payload['app_folder'] ?? ''));
    $entryFile = trim((string) ($payload['entry_file'] ?? 'index.php'));

    if ($title === '') {
        throw new InvalidArgumentException('El titulo de la herramienta es obligatorio.');
    }

    if ($slug === '') {
        throw new InvalidArgumentException('La herramienta necesita un slug valido.');
    }

    if ($categoryKey === '') {
        throw new InvalidArgumentException('Selecciona en que categoria se mostrara la herramienta.');
    }

    if (!in_array($launchMode, ['php_folder', 'panel_module'], true)) {
        throw new InvalidArgumentException('Selecciona un modo de apertura valido.');
    }

    if ($launchMode === 'panel_module' && $panelModuleKey === '') {
        throw new InvalidArgumentException('Indica la clave interna del modulo del panel.');
    }

    if ($launchMode === 'php_folder' && $appFolder === '') {
        throw new InvalidArgumentException('Indica la carpeta protegida donde vive la herramienta.');
    }

    if ($youtubeUrl !== '' && filter_var($youtubeUrl, FILTER_VALIDATE_URL) === false) {
        throw new InvalidArgumentException('El tutorial debe ser una URL valida.');
    }

    if ($imageUrl !== '' && !isSafeToolImageUrl($imageUrl)) {
        throw new InvalidArgumentException('La imagen de la herramienta debe ser una URL valida o una ruta local segura.');
    }

    if ($appFolder !== '' && !isSafeRelativePath($appFolder)) {
        throw new InvalidArgumentException('La carpeta de la herramienta no tiene un formato valido.');
    }

    if ($entryFile !== '' && !isSafeRelativePath($entryFile)) {
        throw new InvalidArgumentException('El archivo de entrada no tiene un formato valido.');
    }

    $normalized = [
        'category_key' => $categoryKey,
        'slug' => $slug,
        'title' => $title,
        'description' => $description,
        'image_url' => $imageUrl,
        'tutorial_youtube_url' => $youtubeUrl,
        'launch_mode' => $launchMode,
        'panel_module_key' => $panelModuleKey,
        'app_folder' => trim($appFolder, '/'),
        'entry_file' => $entryFile === '' ? 'index.php' : ltrim($entryFile, '/'),
        'sort_order' => (int) ($payload['sort_order'] ?? 0),
        'is_active' => filter_var($payload['is_active'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
        'admin_only' => filter_var($payload['admin_only'] ?? false, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
    ];

    $id = trim((string) ($payload['id'] ?? ''));

    if ($id !== '') {
        $normalized['id'] = $id;
    }

    return $normalized;
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

function toolLaunchRegistryPath(): string
{
    return __DIR__ . '/../../storage/tool_launch_configs.php';
}

function readToolLaunchRegistry(): array
{
    $path = toolLaunchRegistryPath();

    if (!is_file($path)) {
        return [];
    }

    $registry = require $path;

    return is_array($registry) ? $registry : [];
}

function saveToolLaunchRegistry(array $registry): void
{
    $path = toolLaunchRegistryPath();
    $directory = dirname($path);

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('No fue posible preparar el directorio del registro privado de herramientas.');
    }

    $php = "<?php\n";
    $php .= "declare(strict_types=1);\n\n";
    $php .= 'return ' . var_export($registry, true) . ";\n";

    if (file_put_contents($path, $php, LOCK_EX) === false) {
        throw new RuntimeException('No fue posible guardar el registro privado de herramientas.');
    }
}

function getToolLaunchConfig(string $slug): ?array
{
    $registry = readToolLaunchRegistry();
    $payload = $registry[$slug] ?? null;

    return is_array($payload) ? $payload : null;
}

function saveToolLaunchConfig(string $slug, array $config, ?string $previousSlug = null): void
{
    $registry = readToolLaunchRegistry();

    if ($previousSlug !== null && $previousSlug !== '' && $previousSlug !== $slug) {
        unset($registry[$previousSlug]);
    }

    $registry[$slug] = [
        'launch_mode' => (string) ($config['launch_mode'] ?? 'php_folder'),
        'panel_module_key' => (string) ($config['panel_module_key'] ?? ''),
        'app_folder' => (string) ($config['app_folder'] ?? ''),
        'entry_file' => (string) ($config['entry_file'] ?? 'index.php'),
        'hide_sidebar' => array_key_exists('hide_sidebar', $config)
            ? (bool) $config['hide_sidebar']
            : (bool) ($registry[$slug]['hide_sidebar'] ?? false),
    ];

    saveToolLaunchRegistry($registry);
}

function deleteToolLaunchConfig(string $slug): void
{
    $registry = readToolLaunchRegistry();

    if (!array_key_exists($slug, $registry)) {
        return;
    }

    unset($registry[$slug]);
    saveToolLaunchRegistry($registry);
}
