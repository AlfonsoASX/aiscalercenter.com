<?php
declare(strict_types=1);

use AiScaler\WhatsAppBots\WhatsAppBotRepository;

require_once __DIR__ . '/../../modules/whatsapp-bots/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

$toolContext = is_array($toolRuntimeContext ?? null) ? $toolRuntimeContext : [];
$accessToken = trim((string) ($toolContext['access_token'] ?? ''));
$userId = trim((string) ($toolContext['user_id'] ?? ''));
$projectContext = is_array($toolContext['project'] ?? null) ? $toolContext['project'] : [];
$activeProjectId = trim((string) ($projectContext['id'] ?? ''));
$action = trim((string) ($_GET['action'] ?? 'state'));
$repository = new WhatsAppBotRepository();

try {
    if ($accessToken === '' || $userId === '') {
        throw new RuntimeException('No encontramos la sesion segura de Bots de WhatsApp.');
    }

    if ($activeProjectId === '') {
        throw new RuntimeException('Selecciona un proyecto antes de usar esta herramienta.');
    }

    $project = $repository->findProject($accessToken, $activeProjectId);

    if (!is_array($project)) {
        throw new RuntimeException('No encontramos el proyecto activo de Bots de WhatsApp.');
    }

    if ($action === 'state') {
        whatsappBotSendJson([
            'success' => true,
            'data' => whatsappBotBuildToolState(
                $repository,
                $accessToken,
                $userId,
                $project,
                $activeProjectId,
                trim((string) ($_GET['bot_id'] ?? '')),
                trim((string) ($_GET['conversation_id'] ?? ''))
            ),
        ]);
    }

    if ($action === 'create-bot') {
        whatsappBotAssertMethod('POST');
        $bot = $repository->saveBot($accessToken, whatsappBotDefaultBotPayload(
            $activeProjectId,
            $userId,
            (string) ($project['name'] ?? '')
        ));

        whatsappBotSendJson([
            'success' => true,
            'message' => 'Bot creado correctamente.',
            'data' => whatsappBotBuildToolState(
                $repository,
                $accessToken,
                $userId,
                $project,
                $activeProjectId,
                (string) ($bot['id'] ?? ''),
                ''
            ),
        ]);
    }

    if ($action === 'save-bot') {
        whatsappBotAssertMethod('POST');
        $payload = whatsappBotReadJsonPayload();
        $botId = trim((string) ($payload['id'] ?? ''));
        $flowDefinition = whatsappBotNormalizeFlowDefinition($payload['flow_definition'] ?? []);
        $scheduleDefinition = whatsappBotNormalizeScheduleDefinitionPayload($payload['schedule_definition'] ?? []);
        $routingDefinition = whatsappBotNormalizeRoutingDefinition($payload['routing_definition'] ?? []);
        $name = trim((string) ($payload['name'] ?? ''));

        if ($name === '') {
            throw new InvalidArgumentException('El bot necesita un nombre.');
        }

        $savedBot = $repository->saveBot($accessToken, [
            'id' => $botId,
            'project_id' => $activeProjectId,
            'owner_user_id' => $userId,
            'name' => $name,
            'tone' => whatsappBotNormalizeTone((string) ($payload['tone'] ?? 'amigable')),
            'welcome_message' => trim((string) ($payload['welcome_message'] ?? '')),
            'handoff_message' => trim((string) ($payload['handoff_message'] ?? '')),
            'off_hours_message' => trim((string) ($payload['off_hours_message'] ?? '')),
            'fallback_message' => trim((string) ($payload['fallback_message'] ?? '')),
            'unknown_attempt_limit' => max(1, min(5, (int) ($payload['unknown_attempt_limit'] ?? 3))),
            'timezone' => trim((string) ($payload['timezone'] ?? 'America/Mexico_City')) ?: 'America/Mexico_City',
            'business_phone_label' => trim((string) ($payload['business_phone_label'] ?? '')),
            'provider_phone_number_id' => trim((string) ($payload['provider_phone_number_id'] ?? '')),
            'provider_waba_id' => trim((string) ($payload['provider_waba_id'] ?? '')),
            'status' => in_array((string) ($payload['status'] ?? 'draft'), ['draft', 'active', 'paused'], true)
                ? (string) ($payload['status'] ?? 'draft')
                : 'draft',
            'flow_definition' => $flowDefinition,
            'schedule_definition' => $scheduleDefinition,
            'routing_definition' => $routingDefinition,
        ]);

        whatsappBotSendJson([
            'success' => true,
            'message' => 'Configuracion del bot guardada.',
            'data' => whatsappBotBuildToolState(
                $repository,
                $accessToken,
                $userId,
                $project,
                $activeProjectId,
                (string) ($savedBot['id'] ?? ''),
                trim((string) ($payload['active_conversation_id'] ?? ''))
            ),
        ]);
    }

    if ($action === 'save-template') {
        whatsappBotAssertMethod('POST');
        $payload = whatsappBotReadJsonPayload();
        $botId = trim((string) ($payload['bot_id'] ?? ''));
        $name = trim((string) ($payload['name'] ?? ''));

        if ($botId === '' || $name === '') {
            throw new InvalidArgumentException('La plantilla necesita un bot y un nombre.');
        }

        $savedTemplate = $repository->saveTemplate($accessToken, [
            'id' => trim((string) ($payload['id'] ?? '')),
            'bot_id' => $botId,
            'project_id' => $activeProjectId,
            'owner_user_id' => $userId,
            'name' => $name,
            'slug' => whatsappBotSlug($name),
            'category' => whatsappBotNormalizeTemplateCategory((string) ($payload['category'] ?? 'utility')),
            'header_text' => trim((string) ($payload['header_text'] ?? '')),
            'body_text' => trim((string) ($payload['body_text'] ?? '')),
            'footer_text' => trim((string) ($payload['footer_text'] ?? '')),
            'variables' => whatsappBotNormalizeStringArray($payload['variables'] ?? []),
            'approval_status' => whatsappBotNormalizeApprovalStatus((string) ($payload['approval_status'] ?? 'pendiente')),
            'meta_template_id' => trim((string) ($payload['meta_template_id'] ?? '')),
            'media_kind' => whatsappBotNormalizeMediaKind((string) (($payload['media']['kind'] ?? $payload['media_kind'] ?? 'none'))),
            'media_url' => trim((string) (($payload['media']['url'] ?? $payload['media_url'] ?? ''))),
            'media_storage_path' => trim((string) (($payload['media']['path'] ?? $payload['media_storage_path'] ?? ''))),
        ]);

        whatsappBotSendJson([
            'success' => true,
            'message' => 'Plantilla guardada correctamente.',
            'data' => whatsappBotBuildToolState(
                $repository,
                $accessToken,
                $userId,
                $project,
                $activeProjectId,
                $botId,
                trim((string) ($payload['active_conversation_id'] ?? ''))
            ),
        ]);
    }

    if ($action === 'toggle-conversation') {
        whatsappBotAssertMethod('POST');
        $payload = whatsappBotReadJsonPayload();
        $botId = trim((string) ($payload['bot_id'] ?? ''));
        $conversationId = trim((string) ($payload['conversation_id'] ?? ''));
        $mode = trim((string) ($payload['mode'] ?? ''));
        $conversation = $repository->findConversation($accessToken, $conversationId, $botId, $activeProjectId);

        if (!is_array($conversation)) {
            throw new InvalidArgumentException('No encontramos la conversacion seleccionada.');
        }

        $botContext = is_array($conversation['bot_context'] ?? null) ? $conversation['bot_context'] : [];

        if ($mode === 'pause_bot') {
            $conversation = $repository->saveConversation($accessToken, [
                'id' => $conversationId,
                'bot_id' => $botId,
                'project_id' => $activeProjectId,
                'conversation_state' => 'bot_pausado',
                'inbox_status' => 'humano',
                'unread_count' => 0,
                'bot_context' => $botContext,
            ]);
        } elseif ($mode === 'resume_bot') {
            $conversation = $repository->saveConversation($accessToken, [
                'id' => $conversationId,
                'bot_id' => $botId,
                'project_id' => $activeProjectId,
                'conversation_state' => 'bot_activo',
                'inbox_status' => 'bot',
                'bot_context' => $botContext,
            ]);
        } else {
            throw new InvalidArgumentException('Accion de conversacion no soportada.');
        }

        whatsappBotSendJson([
            'success' => true,
            'message' => $mode === 'pause_bot' ? 'El bot quedo pausado para que responda un humano.' : 'El bot volvio a quedar activo.',
            'data' => whatsappBotBuildToolState(
                $repository,
                $accessToken,
                $userId,
                $project,
                $activeProjectId,
                $botId,
                (string) ($conversation['id'] ?? $conversationId)
            ),
        ]);
    }

    if ($action === 'send-message') {
        whatsappBotAssertMethod('POST');
        $payload = whatsappBotReadJsonPayload();
        $botId = trim((string) ($payload['bot_id'] ?? ''));
        $conversationId = trim((string) ($payload['conversation_id'] ?? ''));
        $conversation = $repository->findConversation($accessToken, $conversationId, $botId, $activeProjectId);

        if (!is_array($conversation)) {
            throw new InvalidArgumentException('No encontramos la conversacion seleccionada.');
        }

        $isSessionOpen = whatsappBotConversationHasOpenSession($conversation);
        $messageText = trim((string) ($payload['body'] ?? ''));
        $templateId = trim((string) ($payload['template_id'] ?? ''));
        $templates = $repository->listTemplates($accessToken, $botId, $activeProjectId);
        $selectedTemplate = null;

        foreach ($templates as $template) {
            if (is_array($template) && trim((string) ($template['id'] ?? '')) === $templateId) {
                $selectedTemplate = $template;
                break;
            }
        }

        if (!$isSessionOpen && !is_array($selectedTemplate)) {
            throw new InvalidArgumentException('La sesion de 24 horas esta cerrada. Selecciona una plantilla aprobada.');
        }

        if ($isSessionOpen && $messageText === '' && !is_array($selectedTemplate)) {
            throw new InvalidArgumentException('Escribe un mensaje o elige una plantilla.');
        }

        if (is_array($selectedTemplate)) {
            $renderedTemplateBody = whatsappBotRenderTemplate(
                (string) ($selectedTemplate['body_text'] ?? ''),
                $conversation
            );

            $repository->saveMessage($accessToken, [
                'conversation_id' => $conversationId,
                'bot_id' => $botId,
                'project_id' => $activeProjectId,
                'direction' => 'outgoing',
                'author_type' => 'human',
                'message_type' => 'template',
                'body' => $renderedTemplateBody,
                'attachment_url' => trim((string) ($selectedTemplate['media_url'] ?? '')),
                'attachment_storage_path' => trim((string) ($selectedTemplate['media_storage_path'] ?? '')),
                'payload' => [
                    'template_id' => (string) ($selectedTemplate['id'] ?? ''),
                    'template_name' => (string) ($selectedTemplate['name'] ?? ''),
                ],
                'delivery_status' => 'sent',
            ]);

            $botContext = is_array($conversation['bot_context'] ?? null) ? $conversation['bot_context'] : [];
            unset($botContext['forced_template_id']);

            $repository->saveConversation($accessToken, [
                'id' => $conversationId,
                'bot_id' => $botId,
                'project_id' => $activeProjectId,
                'conversation_state' => 'bot_pausado',
                'inbox_status' => 'humano',
                'last_message_preview' => $renderedTemplateBody,
                'bot_context' => $botContext,
            ]);
        } else {
            $repository->saveMessage($accessToken, [
                'conversation_id' => $conversationId,
                'bot_id' => $botId,
                'project_id' => $activeProjectId,
                'direction' => 'outgoing',
                'author_type' => 'human',
                'message_type' => 'text',
                'body' => $messageText,
                'payload' => [],
                'delivery_status' => 'sent',
            ]);

            $repository->saveConversation($accessToken, [
                'id' => $conversationId,
                'bot_id' => $botId,
                'project_id' => $activeProjectId,
                'conversation_state' => 'bot_pausado',
                'inbox_status' => 'humano',
                'last_message_preview' => $messageText,
                'unread_count' => 0,
                'bot_context' => is_array($conversation['bot_context'] ?? null) ? $conversation['bot_context'] : [],
            ]);
        }

        whatsappBotSendJson([
            'success' => true,
            'message' => 'Mensaje registrado correctamente.',
            'data' => whatsappBotBuildToolState(
                $repository,
                $accessToken,
                $userId,
                $project,
                $activeProjectId,
                $botId,
                $conversationId
            ),
        ]);
    }

    if ($action === 'upload-media') {
        whatsappBotAssertMethod('POST');
        $target = trim((string) ($_POST['target'] ?? 'generic'));
        $file = $_FILES['file'] ?? null;

        if (!is_array($file)) {
            throw new RuntimeException('Selecciona un archivo para subir.');
        }

        $uploaded = whatsappBotUploadMediaFile($file, $accessToken, $userId, $target);

        whatsappBotSendJson([
            'success' => true,
            'message' => 'Archivo subido correctamente.',
            'data' => $uploaded,
        ]);
    }

    throw new InvalidArgumentException('Accion no soportada por Bots de WhatsApp.');
} catch (InvalidArgumentException $exception) {
    whatsappBotSendJson([
        'success' => false,
        'message' => $exception->getMessage(),
    ], 422);
} catch (Throwable $exception) {
    whatsappBotSendJson([
        'success' => false,
        'message' => normalizeWhatsAppBotException($exception),
    ], 400);
}

function whatsappBotBuildToolState(
    WhatsAppBotRepository $repository,
    string $accessToken,
    string $userId,
    array $project,
    string $projectId,
    string $requestedBotId,
    string $requestedConversationId
): array {
    $repository->ensureDefaultBot($accessToken, $projectId, $userId, (string) ($project['name'] ?? ''));
    $bots = $repository->listBots($accessToken, $projectId);
    $activeBotId = $requestedBotId;

    if ($activeBotId === '' && isset($bots[0]) && is_array($bots[0])) {
        $activeBotId = (string) ($bots[0]['id'] ?? '');
    }

    $activeBot = null;

    foreach ($bots as $bot) {
        if (is_array($bot) && trim((string) ($bot['id'] ?? '')) === $activeBotId) {
            $activeBot = $bot;
            break;
        }
    }

    if (!is_array($activeBot)) {
        $activeBot = is_array($bots[0] ?? null) ? $bots[0] : null;
        $activeBotId = (string) ($activeBot['id'] ?? '');
    }

    $templates = $activeBotId !== '' ? $repository->listTemplates($accessToken, $activeBotId, $projectId) : [];
    $conversations = $activeBotId !== '' ? $repository->listConversations($accessToken, $activeBotId, $projectId, 'all') : [];
    $activeConversationId = $requestedConversationId;

    if ($activeConversationId === '' && isset($conversations[0]) && is_array($conversations[0])) {
        $activeConversationId = (string) ($conversations[0]['id'] ?? '');
    }

    $messages = $activeConversationId !== '' && $activeBotId !== ''
        ? $repository->listMessages($accessToken, $activeConversationId, $activeBotId, $projectId)
        : [];

    $counts = ['bot' => 0, 'human' => 0];

    foreach ($conversations as $conversation) {
        if (!is_array($conversation)) {
            continue;
        }

        if (trim((string) ($conversation['inbox_status'] ?? 'bot')) === 'humano') {
            $counts['human']++;
        } else {
            $counts['bot']++;
        }
    }

    $activeBotWebhook = is_array($activeBot)
        ? [
            'url' => whatsappBotWebhookUrl((string) ($activeBot['public_key'] ?? '')),
            'public_key' => (string) ($activeBot['public_key'] ?? ''),
            'verify_token' => (string) ($activeBot['verify_token'] ?? ''),
        ]
        : ['url' => '', 'public_key' => '', 'verify_token' => ''];

    return [
        'project' => $project,
        'bots' => $bots,
        'active_bot_id' => $activeBotId,
        'templates' => $templates,
        'conversations' => $conversations,
        'messages' => $messages,
        'active_conversation_id' => $activeConversationId,
        'inbox_counts' => $counts,
        'webhook' => $activeBotWebhook,
    ];
}

function whatsappBotAssertMethod(string $method): void
{
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== strtoupper($method)) {
        throw new InvalidArgumentException('Metodo no permitido.');
    }
}

function whatsappBotReadJsonPayload(): array
{
    $rawInput = file_get_contents('php://input');

    if ($rawInput === false || trim($rawInput) === '') {
        return $_POST;
    }

    $decoded = json_decode($rawInput, true);

    return is_array($decoded) ? $decoded : [];
}

function whatsappBotNormalizeTone(string $value): string
{
    $normalized = trim($value);

    return in_array($normalized, ['formal', 'amigable', 'directo'], true) ? $normalized : 'amigable';
}

function whatsappBotNormalizeTemplateCategory(string $value): string
{
    $normalized = trim($value);

    return in_array($normalized, ['marketing', 'utility', 'follow_up'], true) ? $normalized : 'utility';
}

function whatsappBotNormalizeApprovalStatus(string $value): string
{
    $normalized = trim($value);

    return in_array($normalized, ['pendiente', 'aprobado', 'rechazado'], true) ? $normalized : 'pendiente';
}

function whatsappBotNormalizeMediaKind(string $value): string
{
    $normalized = trim($value);

    return in_array($normalized, ['none', 'image', 'audio', 'document'], true) ? $normalized : 'none';
}

function whatsappBotNormalizeStringArray(mixed $value): array
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

function whatsappBotNormalizeFlowDefinition(mixed $value): array
{
    $default = whatsappBotDefaultFlowDefinition();
    $candidate = is_array($value) ? $value : [];
    $default['main_buttons'] = whatsappBotNormalizeFlowItems($candidate['main_buttons'] ?? [], 3);
    $default['list_options'] = whatsappBotNormalizeFlowItems($candidate['list_options'] ?? [], 10);
    $prompts = is_array($candidate['field_prompts'] ?? null) ? $candidate['field_prompts'] : [];

    foreach ($default['field_prompts'] as $key => $prompt) {
        $default['field_prompts'][$key] = trim((string) ($prompts[$key] ?? $prompt));
    }

    return $default;
}

function whatsappBotNormalizeFlowItems(mixed $value, int $limit): array
{
    if (!is_array($value)) {
        return [];
    }

    $items = [];
    $allowedFields = ['name', 'email', 'phone', 'requirement'];

    foreach (array_slice($value, 0, $limit) as $item) {
        if (!is_array($item)) {
            continue;
        }

        $label = trim((string) ($item['label'] ?? ''));

        if ($label === '') {
            continue;
        }

        $captureSequence = [];

        foreach ((array) ($item['capture_sequence'] ?? []) as $fieldKey) {
            $normalizedFieldKey = trim((string) $fieldKey);

            if ($normalizedFieldKey !== '' && in_array($normalizedFieldKey, $allowedFields, true) && !in_array($normalizedFieldKey, $captureSequence, true)) {
                $captureSequence[] = $normalizedFieldKey;
            }
        }

        $items[] = [
            'id' => trim((string) ($item['id'] ?? whatsappBotSlug($label))) ?: 'item-' . bin2hex(random_bytes(4)),
            'label' => $label,
            'response_text' => trim((string) ($item['response_text'] ?? '')),
            'capture_sequence' => $captureSequence,
            'human_on_complete' => (bool) ($item['human_on_complete'] ?? false),
            'create_lead_on_complete' => (bool) ($item['create_lead_on_complete'] ?? false),
            'success_message' => trim((string) ($item['success_message'] ?? '')),
            'attachment' => whatsappBotNormalizeAttachment($item['attachment'] ?? null),
        ];
    }

    return $items;
}

function whatsappBotNormalizeScheduleDefinitionPayload(mixed $value): array
{
    return whatsappBotNormalizeSchedule(is_array($value) ? $value : []);
}

function whatsappBotNormalizeRoutingDefinition(mixed $value): array
{
    $default = whatsappBotDefaultRoutingDefinition();
    $candidate = is_array($value) ? $value : [];
    $default['follow_up_stage_key'] = trim((string) ($candidate['follow_up_stage_key'] ?? $default['follow_up_stage_key']));
    $default['follow_up_template_id'] = trim((string) ($candidate['follow_up_template_id'] ?? ''));
    $default['campaign_triggers'] = [];

    foreach ((array) ($candidate['campaign_triggers'] ?? []) as $trigger) {
        if (!is_array($trigger)) {
            continue;
        }

        $phrase = trim((string) ($trigger['trigger_text'] ?? ''));
        $target = trim((string) ($trigger['target_option_id'] ?? ''));

        if ($phrase === '' || $target === '') {
            continue;
        }

        $default['campaign_triggers'][] = [
            'id' => trim((string) ($trigger['id'] ?? whatsappBotSlug($phrase))) ?: 'trigger-' . bin2hex(random_bytes(4)),
            'trigger_text' => $phrase,
            'target_option_id' => $target,
        ];
    }

    return $default;
}

function whatsappBotUploadMediaFile(array $file, string $accessToken, string $userId, string $target): array
{
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($errorCode !== UPLOAD_ERR_OK) {
        throw new RuntimeException(whatsappBotUploadErrorMessage($errorCode));
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    $originalName = basename((string) ($file['name'] ?? 'archivo'));
    $size = (int) ($file['size'] ?? 0);

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('No fue posible leer el archivo temporal.');
    }

    if ($size <= 0 || $size > 20 * 1024 * 1024) {
        throw new RuntimeException('El archivo debe pesar menos de 20 MB.');
    }

    $mimeType = whatsappBotDetectMimeType($tmpName);
    $allowed = [
        'image/jpeg' => ['extension' => 'jpg', 'kind' => 'image'],
        'image/png' => ['extension' => 'png', 'kind' => 'image'],
        'image/webp' => ['extension' => 'webp', 'kind' => 'image'],
        'image/gif' => ['extension' => 'gif', 'kind' => 'image'],
        'audio/mpeg' => ['extension' => 'mp3', 'kind' => 'audio'],
        'audio/ogg' => ['extension' => 'ogg', 'kind' => 'audio'],
        'audio/wav' => ['extension' => 'wav', 'kind' => 'audio'],
        'application/pdf' => ['extension' => 'pdf', 'kind' => 'document'],
    ];

    if (!isset($allowed[$mimeType])) {
        throw new RuntimeException('Formato no permitido. Usa imagen, audio MP3/OGG/WAV o PDF.');
    }

    $targetFolder = whatsappBotSlug($target) ?: 'media';
    $storagePath = appStorageUserPath(
        $userId,
        'whatsapp_bots',
        gmdate('Y'),
        gmdate('m'),
        $targetFolder,
        bin2hex(random_bytes(10)) . '.' . $allowed[$mimeType]['extension']
    );
    $body = file_get_contents($tmpName);

    if ($body === false) {
        throw new RuntimeException('No fue posible preparar el archivo para subirlo.');
    }

    whatsappBotPutStorageObject($storagePath, $body, $mimeType, $accessToken);

    return [
        'kind' => $allowed[$mimeType]['kind'],
        'path' => $storagePath,
        'url' => appStoragePublicUrl($storagePath),
        'file_name' => $originalName,
        'mime_type' => $mimeType,
    ];
}

function whatsappBotPutStorageObject(string $path, string $body, string $mimeType, string $accessToken): void
{
    $curl = curl_init(appStorageObjectUrl($path));

    if ($curl === false) {
        throw new RuntimeException('No fue posible inicializar la subida al storage central.');
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . supabaseApiKey(),
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: ' . $mimeType,
            'x-upsert: false',
        ],
    ]);

    $responseBody = curl_exec($curl);

    if ($responseBody === false) {
        $error = curl_error($curl);
        curl_close($curl);
        throw new RuntimeException('Error al subir el archivo a Supabase Storage: ' . $error);
    }

    $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($statusCode >= 400) {
        throw new RuntimeException('Supabase Storage respondio con error HTTP ' . $statusCode . ': ' . (string) $responseBody);
    }
}

function whatsappBotDetectMimeType(string $path): string
{
    $finfo = finfo_open(FILEINFO_MIME_TYPE);

    if ($finfo === false) {
        throw new RuntimeException('No fue posible validar el tipo de archivo.');
    }

    $mimeType = finfo_file($finfo, $path);
    finfo_close($finfo);

    return is_string($mimeType) ? $mimeType : '';
}

function whatsappBotUploadErrorMessage(int $errorCode): string
{
    return match ($errorCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo supera el limite de carga permitido.',
        UPLOAD_ERR_PARTIAL => 'El archivo se subio de forma incompleta. Intenta nuevamente.',
        UPLOAD_ERR_NO_FILE => 'Selecciona un archivo para subir.',
        default => 'No fue posible subir el archivo.',
    };
}

function whatsappBotSlug(string $value): string
{
    $normalized = strtolower(trim($value));
    $normalized = preg_replace('/[^a-z0-9]+/i', '-', $normalized) ?? '';

    return trim($normalized, '-');
}

function whatsappBotConversationHasOpenSession(array $conversation): bool
{
    $expiresAt = trim((string) ($conversation['session_expires_at'] ?? ''));

    if ($expiresAt === '') {
        return false;
    }

    return strtotime($expiresAt) > time();
}

function whatsappBotRenderTemplate(string $body, array $conversation): string
{
    $replacements = [
        '{Nombre}' => trim((string) ($conversation['customer_name'] ?? 'cliente')) ?: 'cliente',
        '{Telefono}' => trim((string) ($conversation['customer_phone'] ?? '')),
        '{Email}' => trim((string) ($conversation['customer_email'] ?? '')),
        '{Empresa}' => trim((string) ($conversation['customer_company'] ?? '')),
    ];

    return strtr($body, $replacements);
}

function whatsappBotSendJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
