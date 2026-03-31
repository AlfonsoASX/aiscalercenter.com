<?php
declare(strict_types=1);

use AiScaler\Research\Http\HttpClient;
use AiScaler\Research\Providers\Amazon\AmazonProvider;
use AiScaler\Research\Providers\Google\GoogleProvider;
use AiScaler\Research\Providers\MercadoLibre\MercadoLibreProvider;
use AiScaler\Research\Providers\YouTube\YouTubeProvider;
use AiScaler\Research\ResearchService;
use AiScaler\Research\Support\TextAnalyzer;

require_once __DIR__ . '/../lib/supabase_api.php';
require_once __DIR__ . '/../modules/research/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    sendJson([
        'success' => false,
        'message' => 'Metodo no permitido.',
    ], 405);
}

try {
    $user = authenticateRequest();
    $payload = readJsonPayload();
    $idea = trim((string) ($payload['idea'] ?? ''));
    $limit = max(4, min(12, (int) ($payload['limit'] ?? 10)));

    $config = require __DIR__ . '/../config/research.php';

    if (!is_array($config)) {
        throw new RuntimeException('La configuracion de investigacion no es valida.');
    }

    $providerConfig = is_array($config['providers'] ?? null) ? $config['providers'] : [];
    $httpClient = new HttpClient((int) ($config['http_timeout'] ?? 12));
    $textAnalyzer = new TextAnalyzer();
    $service = new ResearchService([
        new GoogleProvider((array) ($providerConfig['google'] ?? []), $httpClient, $textAnalyzer),
        new YouTubeProvider((array) ($providerConfig['youtube'] ?? []), $httpClient, $textAnalyzer),
        new MercadoLibreProvider((array) ($providerConfig['mercado_libre'] ?? []), $httpClient, $textAnalyzer),
        new AmazonProvider((array) ($providerConfig['amazon'] ?? []), $httpClient, $textAnalyzer),
    ], (int) ($config['default_limit'] ?? 10));

    $result = $service->analyze($idea, $limit);

    sendJson([
        'success' => true,
        'message' => 'Investigacion generada correctamente.',
        'user' => [
            'email' => (string) ($user['email'] ?? ''),
        ],
        'data' => $result,
    ]);
} catch (InvalidArgumentException $exception) {
    sendJson([
        'success' => false,
        'message' => $exception->getMessage(),
    ], 422);
} catch (Throwable $exception) {
    sendJson([
        'success' => false,
        'message' => $exception->getMessage(),
    ], 500);
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

function authenticateRequest(): array
{
    $token = resolveBearerToken();

    if ($token === '') {
        sendJson([
            'success' => false,
            'message' => 'Debes iniciar sesion para usar Investigar.',
        ], 401);
    }

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
