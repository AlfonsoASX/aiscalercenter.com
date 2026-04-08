<?php
declare(strict_types=1);

use AiScaler\CustomerPipeline\CustomerPipelineRepository;

require_once __DIR__ . '/modules/customer-follow-up/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Metodo no permitido.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$publicKey = trim((string) ($_GET['key'] ?? $_SERVER['HTTP_X_AISCALER_LEAD_KEY'] ?? ''));
$payload = customerPipelineReadRequestPayload();
$repository = new CustomerPipelineRepository();

try {
    if ($publicKey === '') {
        throw new InvalidArgumentException('Falta la llave publica del proyecto.');
    }

    $createdLead = $repository->submitPublicLead($publicKey, $payload);

    echo json_encode([
        'success' => true,
        'message' => 'Lead registrado correctamente.',
        'data' => $createdLead,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => normalizeCustomerPipelineException($exception),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function customerPipelineReadRequestPayload(): array
{
    $rawInput = file_get_contents('php://input');

    if ($rawInput !== false && trim($rawInput) !== '') {
        $decoded = json_decode($rawInput, true);

        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return $_POST;
}

