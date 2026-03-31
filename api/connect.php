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

header('Content-Type: application/json; charset=UTF-8');

try {
    $token = resolveBearerToken();

    if ($token === '') {
        sendJson([
            'success' => false,
            'message' => 'Debes iniciar sesion para administrar conexiones sociales.',
        ], 401);
    }

    $user = authenticateConnectRequest($token);
    $config = require __DIR__ . '/../config/connect.php';

    if (!is_array($config)) {
        throw new RuntimeException('La configuracion de Conecta no es valida.');
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
    $action = resolveAction();

    if ($action === 'bootstrap') {
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

    $payload = readJsonPayload();

    if ($action === 'save') {
        $providerKey = trim((string) ($payload['provider_key'] ?? ''));
        $definition = $service->find($providerKey);

        if (!$definition) {
            throw new InvalidArgumentException('Selecciona una red social valida para guardar la conexion.');
        }

        $displayName = trim((string) ($payload['display_name'] ?? ''));
        $handle = trim((string) ($payload['handle'] ?? ''));
        $externalId = trim((string) ($payload['external_id'] ?? ''));
        $assetUrl = trim((string) ($payload['asset_url'] ?? ''));
        $notes = trim((string) ($payload['notes'] ?? ''));

        if ($displayName === '') {
            throw new InvalidArgumentException('Escribe un nombre descriptivo para este activo digital.');
        }

        if ($handle === '' && $externalId === '' && $assetUrl === '') {
            throw new InvalidArgumentException('Agrega al menos un identificador, handle o URL del activo.');
        }

        $saved = $repository->save($token, [
            'id' => trim((string) ($payload['id'] ?? '')),
            'owner_user_id' => (string) ($user['id'] ?? ''),
            'provider_key' => $definition['key'],
            'platform_label' => $definition['platform'],
            'connection_label' => $definition['label'],
            'display_name' => $displayName,
            'handle' => $handle,
            'external_id' => $externalId,
            'asset_url' => $assetUrl,
            'notes' => $notes,
            'connection_status' => 'pending_auth',
        ]);

        sendJson([
            'success' => true,
            'message' => 'Activo digital guardado correctamente.',
            'data' => $saved,
        ]);
    }

    if ($action === 'delete') {
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
    $message = $exception->getMessage();
    $normalized = strtolower($message);

    if ((str_contains($normalized, 'pgrst205') || str_contains($normalized, 'could not find the table')) && str_contains($normalized, 'social_connections')) {
        sendJson([
            'success' => false,
            'setup_required' => true,
            'message' => 'La tabla social_connections aun no existe. Ejecuta supabase/social_connections_schema.sql en Supabase.',
        ], 500);
    }

    sendJson([
        'success' => false,
        'message' => $message,
    ], 500);
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
    } catch (Throwable $exception) {
        sendJson([
            'success' => false,
            'message' => 'No se pudo validar la sesion actual.',
        ], 401);
    }

    $data = $response['data'] ?? null;

    if (!is_array($data) || !isset($data['id'])) {
        sendJson([
            'success' => false,
            'message' => 'La sesion actual ya no es valida.',
        ], 401);
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
