<?php
declare(strict_types=1);

use AiScaler\Research\Http\HttpClient;
use AiScaler\Research\Providers\Google\GoogleProvider;
use AiScaler\Research\Support\TextAnalyzer;

require_once __DIR__ . '/../../lib/supabase_api.php';
require_once __DIR__ . '/../../modules/research/bootstrap.php';
require_once __DIR__ . '/../../modules/tools/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    sendJson([
        'success' => false,
        'message' => 'Metodo no permitido.',
    ], 405);
}

try {
    [, $user] = requireStoredToolsServerUser();
    $payload = readJsonPayload();
    $idea = trim((string) ($payload['idea'] ?? ''));
    $limit = max(4, min(12, (int) ($payload['limit'] ?? 10)));

    if ($idea === '') {
        throw new InvalidArgumentException('Escribe una idea para investigar.');
    }

    $config = require __DIR__ . '/../../config/research.php';
    $providerConfig = is_array($config['providers']['google'] ?? null) ? $config['providers']['google'] : [];
    $provider = new GoogleProvider($providerConfig, new HttpClient((int) ($config['http_timeout'] ?? 12)), new TextAnalyzer());
    $result = $provider->analyze($idea, $limit);

    sendJson([
        'success' => true,
        'message' => 'Investigacion generada correctamente.',
        'user' => [
            'email' => (string) ($user['email'] ?? ''),
        ],
        'data' => [
            'query' => $idea,
            'limit' => $limit,
            'provider_key' => 'google',
            'providers' => [$result],
        ],
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

function sendJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
