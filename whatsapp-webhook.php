<?php
declare(strict_types=1);

use AiScaler\WhatsAppBots\WhatsAppBotRepository;

require_once __DIR__ . '/modules/whatsapp-bots/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

$publicKey = trim((string) ($_GET['bot'] ?? ''));
$repository = new WhatsAppBotRepository();

try {
    if ($publicKey === '') {
        throw new InvalidArgumentException('Falta la llave publica del bot.');
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $verifyToken = trim((string) ($_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? ''));
        $challenge = (string) ($_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '');
        $context = $repository->getPublicBotContext($publicKey);

        if (!is_array($context)) {
            throw new InvalidArgumentException('No encontramos el bot solicitado.');
        }

        if (!hash_equals((string) ($context['verify_token'] ?? ''), $verifyToken)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'El token de verificacion no es valido.',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        header('Content-Type: text/plain; charset=UTF-8');
        echo $challenge;
        exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        throw new InvalidArgumentException('Metodo no permitido.');
    }

    $context = $repository->getPublicBotContext($publicKey);

    if (!is_array($context)) {
        throw new InvalidArgumentException('No encontramos el bot solicitado.');
    }

    $payload = whatsappWebhookReadPayload();
    $statuses = whatsappWebhookExtractStatuses($payload);

    foreach ($statuses as $status) {
        $externalMessageId = trim((string) ($status['external_message_id'] ?? ''));
        $deliveryStatus = trim((string) ($status['delivery_status'] ?? ''));

        if ($externalMessageId === '' || $deliveryStatus === '') {
            continue;
        }

        $repository->updatePublicMessageStatus($publicKey, $externalMessageId, $deliveryStatus, $status);
    }

    foreach (whatsappWebhookExtractIncomingMessages($payload) as $message) {
        whatsappWebhookProcessIncomingMessage($repository, $context, $publicKey, $message);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Webhook procesado correctamente.',
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
        'message' => normalizeWhatsAppBotException($exception),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function whatsappWebhookReadPayload(): array
{
    $rawInput = file_get_contents('php://input');

    if ($rawInput === false || trim($rawInput) === '') {
        return [];
    }

    $decoded = json_decode($rawInput, true);

    return is_array($decoded) ? $decoded : [];
}

function whatsappWebhookExtractStatuses(array $payload): array
{
    $rows = [];

    foreach ((array) ($payload['entry'] ?? []) as $entry) {
        foreach ((array) ($entry['changes'] ?? []) as $change) {
            $value = is_array($change['value'] ?? null) ? $change['value'] : [];

            foreach ((array) ($value['statuses'] ?? []) as $status) {
                if (!is_array($status)) {
                    continue;
                }

                $rows[] = [
                    'external_message_id' => trim((string) ($status['id'] ?? '')),
                    'delivery_status' => trim((string) ($status['status'] ?? '')),
                    'recipient_id' => trim((string) ($status['recipient_id'] ?? '')),
                    'timestamp' => trim((string) ($status['timestamp'] ?? '')),
                    'payload' => $status,
                ];
            }
        }
    }

    return $rows;
}

function whatsappWebhookExtractIncomingMessages(array $payload): array
{
    $rows = [];

    foreach ((array) ($payload['entry'] ?? []) as $entry) {
        foreach ((array) ($entry['changes'] ?? []) as $change) {
            $value = is_array($change['value'] ?? null) ? $change['value'] : [];
            $contacts = (array) ($value['contacts'] ?? []);
            $contactProfile = is_array($contacts[0]['profile'] ?? null) ? $contacts[0]['profile'] : [];
            $contactWaId = trim((string) ($contacts[0]['wa_id'] ?? ''));

            foreach ((array) ($value['messages'] ?? []) as $message) {
                if (!is_array($message)) {
                    continue;
                }

                $textBody = whatsappWebhookResolveMessageText($message);

                $rows[] = [
                    'external_message_id' => trim((string) ($message['id'] ?? '')),
                    'customer_phone' => whatsappBotSanitizePhone((string) ($message['from'] ?? $contactWaId)),
                    'customer_name' => trim((string) ($contactProfile['name'] ?? '')),
                    'body' => $textBody,
                    'message_type' => whatsappWebhookResolveMessageType($message),
                    'payload' => $message,
                    'source_reference' => trim((string) ($message['referral']['headline'] ?? $message['referral']['body'] ?? '')),
                ];
            }
        }
    }

    return $rows;
}

function whatsappWebhookResolveMessageText(array $message): string
{
    $type = trim((string) ($message['type'] ?? 'text'));

    if ($type === 'text') {
        return trim((string) ($message['text']['body'] ?? ''));
    }

    if ($type === 'button') {
        return trim((string) ($message['button']['text'] ?? $message['button']['payload'] ?? ''));
    }

    if ($type === 'interactive') {
        $interactive = is_array($message['interactive'] ?? null) ? $message['interactive'] : [];
        $interactiveType = trim((string) ($interactive['type'] ?? ''));

        if ($interactiveType === 'button_reply') {
            return trim((string) ($interactive['button_reply']['title'] ?? $interactive['button_reply']['id'] ?? ''));
        }

        if ($interactiveType === 'list_reply') {
            return trim((string) ($interactive['list_reply']['title'] ?? $interactive['list_reply']['id'] ?? ''));
        }
    }

    return trim((string) ($message[$type]['caption'] ?? ''));
}

function whatsappWebhookResolveMessageType(array $message): string
{
    $type = trim((string) ($message['type'] ?? 'text'));

    return in_array($type, ['text', 'image', 'audio', 'document', 'button', 'interactive'], true)
        ? $type
        : 'text';
}

function whatsappWebhookProcessIncomingMessage(
    WhatsAppBotRepository $repository,
    array $bot,
    string $publicKey,
    array $message
): void {
    $phone = whatsappBotSanitizePhone((string) ($message['customer_phone'] ?? ''));
    $incomingText = trim((string) ($message['body'] ?? ''));

    if ($phone === '') {
        return;
    }

    $conversation = $repository->getPublicConversationState($publicKey, $phone);
    $hadConversation = is_array($conversation);
    $routingDefinition = is_array($bot['routing_definition'] ?? null) ? $bot['routing_definition'] : [];
    $flowDefinition = is_array($bot['flow_definition'] ?? null) ? $bot['flow_definition'] : whatsappBotDefaultFlowDefinition();

    $conversationPayload = [
        'customer_phone' => $phone,
        'customer_name' => trim((string) ($message['customer_name'] ?? '')),
        'source_label' => 'WhatsApp',
        'source_reference' => trim((string) ($message['source_reference'] ?? '')),
        'touch_last_customer_message' => true,
        'session_expires_at' => gmdate('c', time() + 86400),
        'last_message_preview' => $incomingText,
        'increment_unread' => 1,
    ];

    if (is_array($conversation)) {
        $conversationPayload['conversation_id'] = (string) ($conversation['id'] ?? '');
        $conversationPayload['unknown_attempts'] = (int) ($conversation['unknown_attempts'] ?? 0);
        $conversationPayload['bot_context'] = is_array($conversation['bot_context'] ?? null) ? $conversation['bot_context'] : [];
    }

    $conversation = $repository->upsertPublicConversation($publicKey, $conversationPayload);
    $repository->appendPublicMessage($publicKey, [
        'conversation_id' => (string) ($conversation['id'] ?? ''),
        'direction' => 'incoming',
        'author_type' => 'customer',
        'message_type' => 'text',
        'body' => $incomingText,
        'payload' => $message['payload'] ?? [],
        'delivery_status' => 'received',
        'external_message_id' => trim((string) ($message['external_message_id'] ?? '')),
    ]);

    if (trim((string) ($conversation['conversation_state'] ?? 'bot_activo')) === 'bot_pausado') {
        return;
    }

    $botContext = is_array($conversation['bot_context'] ?? null) ? $conversation['bot_context'] : [];
    $campaignTargetId = whatsappWebhookResolveCampaignTrigger($routingDefinition, $incomingText);

    if ($campaignTargetId !== '') {
        whatsappWebhookRunFlowItem($repository, $publicKey, $bot, $conversation, $flowDefinition, $campaignTargetId);
        return;
    }

    $awaitingField = trim((string) ($botContext['awaiting_field'] ?? ''));

    if ($awaitingField !== '') {
        whatsappWebhookHandleFieldCapture($repository, $publicKey, $bot, $conversation, $flowDefinition, $awaitingField, $incomingText);
        return;
    }

    $option = whatsappWebhookResolveFlowItemByMessage($flowDefinition, $incomingText);

    if (is_array($option)) {
        whatsappWebhookRunFlowPayload($repository, $publicKey, $bot, $conversation, $flowDefinition, $option);
        return;
    }

    if (!$hadConversation || whatsappWebhookLooksLikeGreeting($incomingText)) {
        whatsappWebhookSendBotText($repository, $publicKey, $conversation, (string) ($bot['welcome_message'] ?? ''));
        whatsappWebhookSendMainMenu($repository, $publicKey, $conversation, $flowDefinition);
        return;
    }

    $unknownAttempts = (int) ($conversation['unknown_attempts'] ?? 0) + 1;
    $repository->patchPublicConversation($publicKey, [
        'conversation_id' => (string) ($conversation['id'] ?? ''),
        'unknown_attempts' => $unknownAttempts,
    ]);

    if ($unknownAttempts >= max(1, (int) ($bot['unknown_attempt_limit'] ?? 3))) {
        whatsappWebhookTransferToHuman($repository, $publicKey, $bot, $conversation, true);
        return;
    }

    whatsappWebhookSendBotText($repository, $publicKey, $conversation, (string) ($bot['fallback_message'] ?? ''));
    whatsappWebhookSendMainMenu($repository, $publicKey, $conversation, $flowDefinition);
}

function whatsappWebhookResolveCampaignTrigger(array $routingDefinition, string $text): string
{
    $normalizedText = mb_strtolower(trim($text));

    foreach ((array) ($routingDefinition['campaign_triggers'] ?? []) as $trigger) {
        if (!is_array($trigger)) {
            continue;
        }

        if ($normalizedText === mb_strtolower(trim((string) ($trigger['trigger_text'] ?? '')))) {
            return trim((string) ($trigger['target_option_id'] ?? ''));
        }
    }

    return '';
}

function whatsappWebhookResolveFlowItemByMessage(array $flowDefinition, string $text): ?array
{
    $normalizedText = mb_strtolower(trim($text));

    foreach (array_merge((array) ($flowDefinition['main_buttons'] ?? []), (array) ($flowDefinition['list_options'] ?? [])) as $item) {
        if (!is_array($item)) {
            continue;
        }

        $label = mb_strtolower(trim((string) ($item['label'] ?? '')));
        $id = mb_strtolower(trim((string) ($item['id'] ?? '')));

        if ($normalizedText !== '' && ($normalizedText === $label || $normalizedText === $id)) {
            return $item;
        }
    }

    return null;
}

function whatsappWebhookRunFlowItem(
    WhatsAppBotRepository $repository,
    string $publicKey,
    array $bot,
    array $conversation,
    array $flowDefinition,
    string $itemId
): void {
    foreach (array_merge((array) ($flowDefinition['main_buttons'] ?? []), (array) ($flowDefinition['list_options'] ?? [])) as $item) {
        if (is_array($item) && trim((string) ($item['id'] ?? '')) === $itemId) {
            whatsappWebhookRunFlowPayload($repository, $publicKey, $bot, $conversation, $flowDefinition, $item);
            return;
        }
    }
}

function whatsappWebhookRunFlowPayload(
    WhatsAppBotRepository $repository,
    string $publicKey,
    array $bot,
    array $conversation,
    array $flowDefinition,
    array $item
): void {
    $conversationId = (string) ($conversation['id'] ?? '');
    $captureSequence = array_values(array_filter((array) ($item['capture_sequence'] ?? []), static fn (mixed $field): bool => trim((string) $field) !== ''));

    if (trim((string) ($item['response_text'] ?? '')) !== '') {
        whatsappWebhookSendBotText($repository, $publicKey, $conversation, (string) ($item['response_text'] ?? ''));
    }

    $attachment = whatsappBotNormalizeAttachment($item['attachment'] ?? null);

    if (is_array($attachment)) {
        whatsappWebhookSendBotAttachment($repository, $publicKey, $conversation, $attachment);
    }

    if ($captureSequence !== []) {
        $firstField = (string) array_shift($captureSequence);
        $botContext = is_array($conversation['bot_context'] ?? null) ? $conversation['bot_context'] : [];
        $botContext['active_option_id'] = (string) ($item['id'] ?? '');
        $botContext['capture_sequence'] = $captureSequence;
        $botContext['captured'] = [];
        $botContext['awaiting_field'] = $firstField;

        $repository->patchPublicConversation($publicKey, [
            'conversation_id' => $conversationId,
            'bot_context' => $botContext,
            'conversation_state' => 'bot_activo',
            'inbox_status' => 'bot',
        ]);
        whatsappWebhookSendBotText($repository, $publicKey, $conversation, whatsappBotResolveFieldPrompt($flowDefinition, $firstField));
        return;
    }

    if ((bool) ($item['human_on_complete'] ?? false)) {
        whatsappWebhookTransferToHuman($repository, $publicKey, $bot, $conversation, false);
    }
}

function whatsappWebhookHandleFieldCapture(
    WhatsAppBotRepository $repository,
    string $publicKey,
    array $bot,
    array $conversation,
    array $flowDefinition,
    string $awaitingField,
    string $incomingText
): void {
    if ($awaitingField === 'email' && !filter_var($incomingText, FILTER_VALIDATE_EMAIL)) {
        whatsappWebhookSendBotText($repository, $publicKey, $conversation, 'Ese correo no parece valido. Me lo compartes otra vez?');
        return;
    }

    $botContext = is_array($conversation['bot_context'] ?? null) ? $conversation['bot_context'] : [];
    $captured = is_array($botContext['captured'] ?? null) ? $botContext['captured'] : [];
    $captured[$awaitingField] = $incomingText;
    $remainingFields = array_values(array_filter((array) ($botContext['capture_sequence'] ?? []), static fn (mixed $field): bool => trim((string) $field) !== ''));
    $activeOptionId = trim((string) ($botContext['active_option_id'] ?? ''));
    $nextField = $remainingFields !== [] ? (string) array_shift($remainingFields) : '';
    $option = whatsappWebhookResolveFlowItemByMessage($flowDefinition, $activeOptionId);

    if (!is_array($option)) {
        foreach (array_merge((array) ($flowDefinition['main_buttons'] ?? []), (array) ($flowDefinition['list_options'] ?? [])) as $item) {
            if (is_array($item) && trim((string) ($item['id'] ?? '')) === $activeOptionId) {
                $option = $item;
                break;
            }
        }
    }

    $patchPayload = [
        'conversation_id' => (string) ($conversation['id'] ?? ''),
        'customer_name' => $awaitingField === 'name' ? $incomingText : (string) ($conversation['customer_name'] ?? ''),
        'customer_email' => $awaitingField === 'email' ? $incomingText : (string) ($conversation['customer_email'] ?? ''),
        'customer_phone' => $awaitingField === 'phone' ? whatsappBotSanitizePhone($incomingText) : (string) ($conversation['customer_phone'] ?? ''),
        'bot_context' => array_merge($botContext, [
            'captured' => $captured,
            'capture_sequence' => $remainingFields,
            'awaiting_field' => $nextField,
        ]),
    ];

    $conversation = $repository->patchPublicConversation($publicKey, $patchPayload);

    if ($nextField !== '') {
        whatsappWebhookSendBotText($repository, $publicKey, $conversation, whatsappBotResolveFieldPrompt($flowDefinition, $nextField));
        return;
    }

    $leadId = trim((string) ($conversation['lead_id'] ?? ''));

    if ($leadId === '' && is_array($option) && (bool) ($option['create_lead_on_complete'] ?? false)) {
        $lead = $repository->createProjectLead((string) ($bot['project_id'] ?? ''), [
            'full_name' => (string) ($captured['name'] ?? $conversation['customer_name'] ?? 'Lead de WhatsApp'),
            'email' => (string) ($captured['email'] ?? $conversation['customer_email'] ?? ''),
            'phone' => (string) ($captured['phone'] ?? $conversation['customer_phone'] ?? ''),
            'source_label' => 'WhatsApp Bot',
            'source_type' => 'whatsapp_bot',
            'source_reference' => (string) ($conversation['id'] ?? ''),
            'notes' => (string) ($captured['requirement'] ?? ''),
            'metadata' => [
                'bot_id' => (string) ($bot['id'] ?? ''),
                'conversation_id' => (string) ($conversation['id'] ?? ''),
            ],
        ]);

        if (is_array($lead)) {
            $conversation = $repository->patchPublicConversation($publicKey, [
                'conversation_id' => (string) ($conversation['id'] ?? ''),
                'bot_context' => array_merge($botContext, [
                    'captured' => $captured,
                    'capture_sequence' => [],
                    'awaiting_field' => '',
                ]),
                'lead_id' => (string) ($lead['id'] ?? $lead['lead_id'] ?? ''),
            ]);
        }
    }

    if (is_array($option) && trim((string) ($option['success_message'] ?? '')) !== '') {
        whatsappWebhookSendBotText($repository, $publicKey, $conversation, (string) ($option['success_message'] ?? ''));
    } else {
        whatsappWebhookSendBotText($repository, $publicKey, $conversation, 'Gracias. Ya tengo la informacion necesaria.');
    }

    $repository->patchPublicConversation($publicKey, [
        'conversation_id' => (string) ($conversation['id'] ?? ''),
        'bot_context' => array_merge($botContext, [
            'captured' => $captured,
            'capture_sequence' => [],
            'awaiting_field' => '',
            'active_option_id' => '',
        ]),
        'unknown_attempts' => 0,
    ]);

    if (is_array($option) && (bool) ($option['human_on_complete'] ?? false)) {
        whatsappWebhookTransferToHuman($repository, $publicKey, $bot, $conversation, false);
    }
}

function whatsappWebhookTransferToHuman(
    WhatsAppBotRepository $repository,
    string $publicKey,
    array $bot,
    array $conversation,
    bool $forcedByFallback
): void {
    $withinHours = whatsappBotIsWithinBusinessHours(
        is_array($bot['schedule_definition'] ?? null) ? $bot['schedule_definition'] : [],
        trim((string) ($bot['timezone'] ?? 'America/Mexico_City'))
    );

    if ($withinHours) {
        whatsappWebhookSendBotText($repository, $publicKey, $conversation, (string) ($bot['handoff_message'] ?? 'Te transfiero con un especialista en un momento.'));
        $repository->patchPublicConversation($publicKey, [
            'conversation_id' => (string) ($conversation['id'] ?? ''),
            'conversation_state' => 'bot_pausado',
            'inbox_status' => 'humano',
            'unknown_attempts' => $forcedByFallback ? max(1, (int) ($conversation['unknown_attempts'] ?? 0)) : 0,
        ]);
        return;
    }

    whatsappWebhookSendBotText($repository, $publicKey, $conversation, (string) ($bot['off_hours_message'] ?? 'Nuestros asesores estan dormidos, pero te atenderan manana a primera hora.'));
}

function whatsappWebhookSendMainMenu(
    WhatsAppBotRepository $repository,
    string $publicKey,
    array $conversation,
    array $flowDefinition
): void {
    $buttons = array_slice((array) ($flowDefinition['main_buttons'] ?? []), 0, 3);

    if ($buttons === []) {
        return;
    }

    $labels = [];

    foreach ($buttons as $button) {
        if (is_array($button) && trim((string) ($button['label'] ?? '')) !== '') {
            $labels[] = (string) ($button['label'] ?? '');
        }
    }

    if ($labels === []) {
        return;
    }

    $repository->appendPublicMessage($publicKey, [
        'conversation_id' => (string) ($conversation['id'] ?? ''),
        'direction' => 'outgoing',
        'author_type' => 'bot',
        'message_type' => 'button',
        'body' => 'Elige una opcion para continuar.',
        'payload' => ['buttons' => $labels],
        'delivery_status' => 'sent',
    ]);
}

function whatsappWebhookSendBotText(
    WhatsAppBotRepository $repository,
    string $publicKey,
    array $conversation,
    string $text
): void {
    $body = trim($text);

    if ($body === '') {
        return;
    }

    $repository->appendPublicMessage($publicKey, [
        'conversation_id' => (string) ($conversation['id'] ?? ''),
        'direction' => 'outgoing',
        'author_type' => 'bot',
        'message_type' => 'text',
        'body' => $body,
        'payload' => [],
        'delivery_status' => 'sent',
    ]);
}

function whatsappWebhookSendBotAttachment(
    WhatsAppBotRepository $repository,
    string $publicKey,
    array $conversation,
    array $attachment
): void {
    $kind = trim((string) ($attachment['kind'] ?? 'document'));

    $repository->appendPublicMessage($publicKey, [
        'conversation_id' => (string) ($conversation['id'] ?? ''),
        'direction' => 'outgoing',
        'author_type' => 'bot',
        'message_type' => $kind === 'image' ? 'image' : ($kind === 'audio' ? 'audio' : 'document'),
        'body' => trim((string) ($attachment['file_name'] ?? 'Archivo')),
        'attachment_url' => trim((string) ($attachment['url'] ?? '')),
        'attachment_storage_path' => trim((string) ($attachment['path'] ?? '')),
        'attachment_mime' => trim((string) ($attachment['mime_type'] ?? '')),
        'payload' => [],
        'delivery_status' => 'sent',
    ]);
}

function whatsappWebhookLooksLikeGreeting(string $text): bool
{
    $normalized = mb_strtolower(trim($text));

    return in_array($normalized, ['hola', 'buenas', 'buenos dias', 'buenas tardes', 'info', 'informacion'], true);
}
