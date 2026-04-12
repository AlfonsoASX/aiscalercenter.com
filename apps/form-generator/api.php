<?php
declare(strict_types=1);

use AiScaler\Forms\FormRepository;

require_once __DIR__ . '/../../modules/forms/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

$toolContext = is_array($toolRuntimeContext ?? null) ? $toolRuntimeContext : [];
$accessToken = trim((string) ($toolContext['access_token'] ?? ''));
$userId = trim((string) ($toolContext['user_id'] ?? ''));
$projectContext = is_array($toolContext['project'] ?? null) ? $toolContext['project'] : [];
$activeProjectId = trim((string) ($projectContext['id'] ?? ''));
$action = trim((string) ($_GET['action'] ?? 'stats'));
$repository = new FormRepository();

try {
    if ($accessToken === '' || $userId === '') {
        throw new RuntimeException('No encontramos la sesion segura para abrir Formularios.');
    }

    if ($activeProjectId === '') {
        throw new RuntimeException('Selecciona un proyecto antes de revisar formularios.');
    }

    if ($action !== 'stats') {
        throw new RuntimeException('Accion no soportada por Formularios.');
    }

    $formId = trim((string) ($_GET['form_id'] ?? ''));

    if ($formId === '') {
        throw new InvalidArgumentException('No encontramos el formulario solicitado.');
    }

    $form = $repository->findForm($accessToken, $formId, $activeProjectId);

    if (!is_array($form)) {
        throw new RuntimeException('No encontramos el formulario solicitado.');
    }

    $responses = $repository->listFormResponses($accessToken, $formId, $activeProjectId);
    $sessions = $repository->listFormSessions($accessToken, $formId, $activeProjectId);
    $summary = formBuilderBuildInsightsSummary($form, $responses, $sessions);

    echo json_encode([
        'success' => true,
        'data' => [
            'form_id' => $formId,
            'summary' => $summary,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => normalizeFormBuilderException($exception),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
