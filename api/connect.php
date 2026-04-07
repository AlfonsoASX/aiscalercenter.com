<?php
declare(strict_types=1);

use AiScaler\Connect\ConnectService;
use AiScaler\Connect\Providers\FacebookPage\FacebookPageProvider;
use AiScaler\Connect\Providers\FacebookProfile\FacebookProfileProvider;
use AiScaler\Connect\Providers\GoogleBusinessProfile\GoogleBusinessProfileProvider;
use AiScaler\Connect\Providers\Instagram\InstagramProvider;
use AiScaler\Connect\Providers\LinkedInCompany\LinkedInCompanyProvider;
use AiScaler\Connect\Providers\LinkedInProfile\LinkedInProfileProvider;
use AiScaler\Connect\Providers\YouTubeChannel\YouTubeChannelProvider;
use AiScaler\Connect\SocialConnectionRepository;

require_once __DIR__ . '/../lib/supabase_api.php';
require_once __DIR__ . '/../modules/connect/bootstrap.php';

ensureConnectSessionStarted();

$config = require __DIR__ . '/../config/connect.php';

if (!is_array($config)) {
    http_response_code(500);
    exit('La configuracion de Conecta no es valida.');
}

$providerConfig = is_array($config['providers'] ?? null) ? $config['providers'] : [];
$service = new ConnectService([
    new FacebookPageProvider((array) ($providerConfig['facebook_page'] ?? [])),
    new FacebookProfileProvider((array) ($providerConfig['facebook_profile'] ?? [])),
    new YouTubeChannelProvider((array) ($providerConfig['youtube_channel'] ?? [])),
    new LinkedInProfileProvider((array) ($providerConfig['linkedin_profile'] ?? [])),
    new LinkedInCompanyProvider((array) ($providerConfig['linkedin_company'] ?? [])),
    new InstagramProvider((array) ($providerConfig['instagram'] ?? [])),
    new GoogleBusinessProfileProvider((array) ($providerConfig['google_business_profile'] ?? [])),
]);
$repository = new SocialConnectionRepository();

if (isOauthCallbackRequest()) {
    handleOauthCallback($service, $repository, $providerConfig);
}

header('Content-Type: application/json; charset=UTF-8');

try {
    $action = resolveAction();

    if ($action === 'bootstrap') {
        [$token, $user] = requireAuthenticatedUser();

        sendJson([
            'success' => true,
            'data' => [
                'catalog' => $service->catalog(),
                'connections' => $repository->list($token),
                'user' => [
                    'id' => (string) ($user['id'] ?? ''),
                    'email' => (string) ($user['email'] ?? ''),
                ],
            ],
        ]);
    }

    if ($action === 'start_oauth') {
        [$token, $user] = requireAuthenticatedUser();
        $payload = readJsonPayload();
        $providerKey = trim((string) ($payload['provider_key'] ?? ''));
        $definition = $service->find($providerKey);
        $oauth = is_array($providerConfig[$providerKey]['oauth'] ?? null) ? $providerConfig[$providerKey]['oauth'] : [];

        if (!$definition) {
            throw new InvalidArgumentException('Selecciona una red social valida.');
        }

        if (!($definition['oauth_ready'] ?? false)) {
            throw new InvalidArgumentException('Completa las credenciales OAuth de esta red social antes de conectarla.');
        }

        $state = bin2hex(random_bytes(24));
        rememberOauthState($state, [
            'provider_key' => $providerKey,
            'access_token' => $token,
            'user_id' => (string) ($user['id'] ?? ''),
            'return_url' => buildPanelReturnUrl(),
            'created_at' => time(),
        ]);

        sendJson([
            'success' => true,
            'data' => [
                'authorization_url' => buildAuthorizationUrl($oauth, $state),
            ],
        ]);
    }

    if ($action === 'delete') {
        [$token] = requireAuthenticatedUser();
        $payload = readJsonPayload();
        $id = trim((string) ($payload['id'] ?? ''));

        if ($id === '') {
            throw new InvalidArgumentException('No encontramos la conexion que intentas borrar.');
        }

        $existing = $repository->find($token, $id);

        if (!$existing) {
            throw new InvalidArgumentException('La conexion ya no existe o no te pertenece.');
        }

        $repository->delete($token, $id);

        sendJson([
            'success' => true,
            'message' => 'Activo digital eliminado correctamente.',
        ]);
    }

    sendJson([
        'success' => false,
        'message' => 'Accion no soportada.',
    ], 400);
} catch (InvalidArgumentException $exception) {
    sendJson([
        'success' => false,
        'message' => $exception->getMessage(),
    ], 422);
} catch (Throwable $exception) {
    $message = normalizeConnectExceptionMessage($exception);

    sendJson([
        'success' => false,
        'setup_required' => isMissingSocialConnectionsTable($exception),
        'message' => $message,
    ], 500);
}

function handleOauthCallback(
    ConnectService $service,
    SocialConnectionRepository $repository,
    array $providerConfig
): never {
    try {
        cleanupExpiredOauthStates();

        if (isset($_GET['error'])) {
            $errorDescription = trim((string) ($_GET['error_description'] ?? $_GET['error']));
            throw new RuntimeException($errorDescription !== ''
                ? 'No se otorgaron los permisos solicitados: ' . $errorDescription
                : 'No se otorgaron los permisos solicitados.');
        }

        $state = trim((string) ($_GET['state'] ?? ''));

        if ($state === '') {
            throw new RuntimeException('No se pudo validar la sesion de conexion.');
        }

        $oauthState = consumeOauthState($state);

        if (!is_array($oauthState)) {
            throw new RuntimeException('La sesion de conexion expiro. Intenta conectar de nuevo.');
        }

        $providerKey = trim((string) ($oauthState['provider_key'] ?? ''));
        $definition = $service->find($providerKey);
        $returnUrl = trim((string) ($oauthState['return_url'] ?? buildPanelReturnUrl()));

        if (!$definition) {
            throw new RuntimeException('La red social solicitada ya no esta disponible.');
        }

        if (!($definition['oauth_ready'] ?? false)) {
            throw new RuntimeException('Completa las credenciales OAuth de esta red social antes de conectarla.');
        }

        $code = trim((string) ($_GET['code'] ?? ''));

        if ($code === '') {
            throw new RuntimeException('El proveedor no devolvio un codigo de autorizacion valido.');
        }

        $repository->save((string) ($oauthState['access_token'] ?? ''), [
            'owner_user_id' => (string) ($oauthState['user_id'] ?? ''),
            'provider_key' => (string) ($definition['key'] ?? $providerKey),
            'platform_label' => (string) ($definition['platform'] ?? ''),
            'connection_label' => (string) ($definition['label'] ?? ''),
            'display_name' => buildConnectionDisplayName($definition),
            'handle' => '',
            'external_id' => buildConnectionReference((string) ($definition['key'] ?? $providerKey), $code, $state),
            'asset_url' => '',
            'notes' => buildConnectionNote($definition),
            'connection_status' => 'connected',
        ]);

        redirectWithClientFlash(
            $returnUrl,
            'Conexion completada correctamente para ' . (string) ($definition['label'] ?? 'la red social') . '.',
            'success'
        );
    } catch (Throwable $exception) {
        redirectWithClientFlash(
            buildPanelReturnUrl(),
            normalizeConnectExceptionMessage($exception),
            'error'
        );
    }
}

function requireAuthenticatedUser(): array
{
    $token = resolveBearerToken();

    if ($token === '') {
        throw new RuntimeException('Debes iniciar sesion para administrar conexiones sociales.');
    }

    return [$token, authenticateConnectRequest($token)];
}

function buildAuthorizationUrl(array $oauth, string $state): string
{
    $authUrl = trim((string) ($oauth['auth_url'] ?? ''));
    $clientId = trim((string) ($oauth['client_id'] ?? ''));
    $redirectUri = trim((string) ($oauth['redirect_uri'] ?? ''));

    if (
        $authUrl === ''
        || $clientId === ''
        || $redirectUri === ''
        || str_starts_with($clientId, 'tu_')
        || str_starts_with($redirectUri, 'https://tu-dominio.com')
    ) {
        throw new RuntimeException('Completa la URL de retorno y el client ID de esta integracion antes de conectarla.');
    }

    $scopes = $oauth['scopes'] ?? ($oauth['scope'] ?? []);

    if (is_string($scopes)) {
        $scopes = trim($scopes) !== '' ? [trim($scopes)] : [];
    }

    $scopeSeparator = trim((string) ($oauth['scope_separator'] ?? ' '));
    $query = [
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => trim((string) ($oauth['response_type'] ?? 'code')),
        'state' => $state,
    ];

    if (is_array($scopes) && $scopes !== []) {
        $query['scope'] = implode($scopeSeparator !== '' ? $scopeSeparator : ' ', array_filter(array_map('trim', $scopes)));
    }

    foreach ((array) ($oauth['query'] ?? []) as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }

        $query[(string) $key] = (string) $value;
    }

    return $authUrl . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

function buildPanelReturnUrl(): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/api/connect.php'));
    $scriptDirectory = rtrim(dirname($scriptName), '/');
    $basePath = preg_replace('#/api$#', '', $scriptDirectory) ?: '';

    return ($basePath === '' ? '' : $basePath) . '/index.php?view=app#Conecta';
}

function buildConnectionDisplayName(array $definition): string
{
    $label = trim((string) ($definition['label'] ?? 'Cuenta conectada'));

    return $label . ' - ' . date('d/m/Y H:i');
}

function buildConnectionReference(string $providerKey, string $code, string $state): string
{
    return $providerKey . '_' . substr(hash('sha256', $code . ':' . $state), 0, 18);
}

function buildConnectionNote(array $definition): string
{
    $label = trim((string) ($definition['label'] ?? 'red social'));

    return 'Conexion OAuth completada para ' . $label . ' el ' . date('Y-m-d H:i:s') . '.';
}

function normalizeConnectExceptionMessage(Throwable $exception): string
{
    if (isMissingSocialConnectionsTable($exception)) {
        return 'La tabla social_connections aun no existe. Ejecuta supabase/social_connections_schema.sql en Supabase.';
    }

    return $exception->getMessage();
}

function isMissingSocialConnectionsTable(Throwable $exception): bool
{
    $message = strtolower($exception->getMessage());

    return (str_contains($message, 'pgrst205') || str_contains($message, 'could not find the table'))
        && str_contains($message, 'social_connections');
}

function isOauthCallbackRequest(): bool
{
    return isset($_GET['callback']);
}

function rememberOauthState(string $state, array $payload): void
{
    cleanupExpiredOauthStates();

    if (!isset($_SESSION['aiscaler_connect_oauth']) || !is_array($_SESSION['aiscaler_connect_oauth'])) {
        $_SESSION['aiscaler_connect_oauth'] = [];
    }

    $_SESSION['aiscaler_connect_oauth'][$state] = $payload;
}

function consumeOauthState(string $state): ?array
{
    $states = $_SESSION['aiscaler_connect_oauth'] ?? [];

    if (!is_array($states) || !isset($states[$state]) || !is_array($states[$state])) {
        return null;
    }

    $payload = $states[$state];
    unset($_SESSION['aiscaler_connect_oauth'][$state]);

    return $payload;
}

function cleanupExpiredOauthStates(): void
{
    $states = $_SESSION['aiscaler_connect_oauth'] ?? [];

    if (!is_array($states)) {
        $_SESSION['aiscaler_connect_oauth'] = [];
        return;
    }

    $now = time();

    $_SESSION['aiscaler_connect_oauth'] = array_filter($states, static function ($payload) use ($now): bool {
        if (!is_array($payload)) {
            return false;
        }

        $createdAt = (int) ($payload['created_at'] ?? 0);

        return $createdAt > 0 && ($now - $createdAt) <= 900;
    });
}

function ensureConnectSessionStarted(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('aiscaler_connect');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => isHttpsRequest(),
    ]);
    session_start();
}

function isHttpsRequest(): bool
{
    if (isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        return strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
    }

    return false;
}

function redirectWithClientFlash(string $targetUrl, string $message, string $type = 'success'): never
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

function resolveAction(): string
{
    return trim((string) ($_GET['action'] ?? 'bootstrap'));
}

function readJsonPayload(): array
{
    $rawInput = file_get_contents('php://input');

    if ($rawInput === false || trim($rawInput) === '') {
        return $_POST;
    }

    $decoded = json_decode($rawInput, true);

    return is_array($decoded) ? $decoded : [];
}

function authenticateConnectRequest(string $token): array
{
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

function resolveBearerToken(): string
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

function sendJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
