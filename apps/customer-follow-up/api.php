<?php
declare(strict_types=1);

use AiScaler\CustomerPipeline\CustomerPipelineRepository;
use AiScaler\WhatsAppBots\WhatsAppBotRepository;

require_once __DIR__ . '/../../modules/customer-follow-up/bootstrap.php';
require_once __DIR__ . '/../../modules/whatsapp-bots/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

$toolContext = is_array($toolRuntimeContext ?? null) ? $toolRuntimeContext : [];
$accessToken = trim((string) ($toolContext['access_token'] ?? ''));
$userId = trim((string) ($toolContext['user_id'] ?? ''));
$projectContext = is_array($toolContext['project'] ?? null) ? $toolContext['project'] : [];
$activeProjectId = trim((string) ($projectContext['id'] ?? ''));
$action = trim((string) ($_GET['action'] ?? ''));
$repository = new CustomerPipelineRepository();

try {
    if ($accessToken === '' || $userId === '') {
        throw new RuntimeException('No encontramos la sesion segura del tablero.');
    }

    if ($activeProjectId === '') {
        throw new RuntimeException('Selecciona un proyecto antes de usar Seguimiento de Clientes.');
    }

    $project = $repository->findProject($accessToken, $activeProjectId);

    if (!is_array($project)) {
        throw new RuntimeException('No encontramos el proyecto activo del tablero.');
    }

    if ($action === 'board') {
        $board = $repository->getBoard($accessToken, $activeProjectId);
        $settings = is_array($board['settings'] ?? null) ? $board['settings'] : null;
        $webhookKey = trim((string) (($settings['public_key'] ?? null) ?: ''));

        customerPipelineSendJson([
            'success' => true,
            'data' => [
                'project' => $project,
                'webhook' => [
                    'public_key' => $webhookKey,
                    'url' => $webhookKey !== '' ? customerPipelineWebhookUrl($webhookKey) : '',
                ],
                'stages' => is_array($board['stages'] ?? null) ? $board['stages'] : [],
                'leads' => is_array($board['leads'] ?? null) ? $board['leads'] : [],
            ],
        ]);
    }

    if ($action === 'save-lead') {
        customerPipelineAssertMethod('POST');

        $payload = customerPipelineReadJsonPayload();
        $board = $repository->getBoard($accessToken, $activeProjectId);
        $stages = is_array($board['stages'] ?? null) ? $board['stages'] : [];
        $leads = is_array($board['leads'] ?? null) ? $board['leads'] : [];
        $leadId = trim((string) ($payload['id'] ?? ''));
        $fullName = trim((string) ($payload['full_name'] ?? ''));
        $requestedStageId = trim((string) ($payload['stage_id'] ?? ''));
        $resolvedStageId = customerPipelineResolveStageId($stages, $requestedStageId);
        $existingLead = customerPipelineFindLead($leads, $leadId);
        $sortOrder = customerPipelineResolveSortOrder(
            $leadId,
            $resolvedStageId,
            $payload,
            $leads,
            $existingLead
        );

        if ($fullName === '') {
            throw new InvalidArgumentException('El lead necesita un nombre.');
        }

        $normalizedLead = [
            'project_id' => $activeProjectId,
            'stage_id' => $resolvedStageId,
            'full_name' => $fullName,
            'email' => trim((string) ($payload['email'] ?? '')),
            'phone' => trim((string) ($payload['phone'] ?? '')),
            'company_name' => trim((string) ($payload['company_name'] ?? '')),
            'source_label' => trim((string) ($payload['source_label'] ?? '')) ?: 'Manual',
            'source_type' => trim((string) ($payload['source_type'] ?? '')) ?: 'manual',
            'source_reference' => trim((string) ($payload['source_reference'] ?? '')),
            'currency_code' => 'MXN',
            'estimated_value' => customerPipelineNormalizeMoney($payload['estimated_value'] ?? 0),
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'lost_reason' => trim((string) ($payload['lost_reason'] ?? '')),
            'tags' => customerPipelineNormalizeStringArray($payload['tags'] ?? []),
            'metadata' => is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [],
            'sort_order' => $sortOrder,
        ];

        if ($leadId !== '') {
            $normalizedLead['id'] = $leadId;
        }

        $savedLead = $repository->saveLead($accessToken, $normalizedLead);

        customerPipelineSendJson([
            'success' => true,
            'message' => $leadId === '' ? 'Lead creado correctamente.' : 'Lead actualizado correctamente.',
            'data' => $savedLead,
        ]);
    }

    if ($action === 'move-lead') {
        customerPipelineAssertMethod('POST');

        $payload = customerPipelineReadJsonPayload();
        $board = $repository->getBoard($accessToken, $activeProjectId);
        $stages = is_array($board['stages'] ?? null) ? $board['stages'] : [];
        $leadId = trim((string) ($payload['lead_id'] ?? ''));
        $stageId = trim((string) ($payload['stage_id'] ?? ''));
        $sortOrder = (float) ($payload['sort_order'] ?? 0);
        $stage = customerPipelineFindStage($stages, $stageId);

        if ($leadId === '' || $stageId === '') {
            throw new InvalidArgumentException('No encontramos el lead o la etapa destino.');
        }

        $movedLead = $repository->moveLead(
            $accessToken,
            $leadId,
            $activeProjectId,
            $stageId,
            $sortOrder,
            array_key_exists('lost_reason', $payload) ? trim((string) ($payload['lost_reason'] ?? '')) : null
        );

        if (is_array($stage)) {
            try {
                $whatsAppBotRepository = new WhatsAppBotRepository();
                $whatsAppBotRepository->triggerFollowUpFromLeadStage(
                    $accessToken,
                    $activeProjectId,
                    $movedLead,
                    trim((string) ($stage['key'] ?? '')),
                    trim((string) ($stage['title'] ?? 'Etapa'))
                );
            } catch (Throwable) {
                // El movimiento del lead no debe fallar si la automatizacion de WhatsApp aun no esta configurada.
            }
        }

        customerPipelineSendJson([
            'success' => true,
            'message' => 'Lead movido correctamente.',
            'data' => $movedLead,
        ]);
    }

    throw new InvalidArgumentException('Accion no soportada por Seguimiento de Clientes.');
} catch (InvalidArgumentException $exception) {
    customerPipelineSendJson([
        'success' => false,
        'message' => $exception->getMessage(),
    ], 422);
} catch (Throwable $exception) {
    customerPipelineSendJson([
        'success' => false,
        'message' => normalizeCustomerPipelineException($exception),
    ], 400);
}

function customerPipelineAssertMethod(string $method): void
{
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== strtoupper($method)) {
        throw new InvalidArgumentException('Metodo no permitido.');
    }
}

function customerPipelineReadJsonPayload(): array
{
    $rawInput = file_get_contents('php://input');

    if ($rawInput === false || trim($rawInput) === '') {
        return $_POST;
    }

    $decoded = json_decode($rawInput, true);

    return is_array($decoded) ? $decoded : [];
}

function customerPipelineResolveStageId(array $stages, string $requestedStageId): string
{
    foreach ($stages as $stage) {
        if (!is_array($stage)) {
            continue;
        }

        if (trim((string) ($stage['id'] ?? '')) === $requestedStageId && $requestedStageId !== '') {
            return $requestedStageId;
        }
    }

    return trim((string) ($stages[0]['id'] ?? ''));
}

function customerPipelineResolveSortOrder(
    string $leadId,
    string $stageId,
    array $payload,
    array $leads,
    ?array $existingLead = null
): float {
    if ($existingLead !== null && trim((string) ($existingLead['stage_id'] ?? '')) !== $stageId) {
        $leadId = '';
    }

    if ($leadId !== '' && isset($payload['sort_order']) && is_numeric($payload['sort_order'])) {
        return (float) $payload['sort_order'];
    }

    $minimum = null;

    foreach ($leads as $lead) {
        if (!is_array($lead) || trim((string) ($lead['stage_id'] ?? '')) !== $stageId) {
            continue;
        }

        $current = (float) ($lead['sort_order'] ?? 0);
        $minimum = $minimum === null ? $current : min($minimum, $current);
    }

    if ($minimum === null) {
        return 0;
    }

    return $minimum - 1024;
}

function customerPipelineFindLead(array $leads, string $leadId): ?array
{
    foreach ($leads as $lead) {
        if (!is_array($lead)) {
            continue;
        }

        if (trim((string) ($lead['id'] ?? '')) === $leadId && $leadId !== '') {
            return $lead;
        }
    }

    return null;
}

function customerPipelineFindStage(array $stages, string $stageId): ?array
{
    foreach ($stages as $stage) {
        if (!is_array($stage)) {
            continue;
        }

        if (trim((string) ($stage['id'] ?? '')) === $stageId && $stageId !== '') {
            return $stage;
        }
    }

    return null;
}

function customerPipelineNormalizeMoney(mixed $value): float
{
    $normalized = trim((string) $value);

    if ($normalized === '' || preg_match('/^-?[0-9]+(\.[0-9]+)?$/', $normalized) !== 1) {
        return 0;
    }

    return round((float) $normalized, 2);
}

function customerPipelineNormalizeStringArray(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }

    return array_values(array_filter(array_map(static function (mixed $item): string {
        return trim((string) $item);
    }, $value), static function (string $item): bool {
        return $item !== '';
    }));
}

function customerPipelineSendJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
