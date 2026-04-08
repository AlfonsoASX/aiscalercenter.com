<?php
declare(strict_types=1);

namespace AiScaler\WhatsAppBots;

use RuntimeException;

final class WhatsAppBotRepository
{
    public function findProject(string $accessToken, string $projectId): ?array
    {
        $normalizedProjectId = trim($projectId);

        if ($normalizedProjectId === '') {
            return null;
        }

        $response = \supabaseRestRequest(
            'GET',
            'projects?select=id,name,logo_url&id=eq.' . rawurlencode($normalizedProjectId) . '&deleted_at=is.null&limit=1',
            [],
            $accessToken
        );

        $data = $response['data'] ?? null;

        if (!is_array($data) || $data === []) {
            return null;
        }

        return is_array($data[0] ?? null) ? $data[0] : null;
    }

    public function listBots(string $accessToken, string $projectId): array
    {
        $response = \supabaseRestRequest(
            'GET',
            'whatsapp_bots?select=*&project_id=eq.' . rawurlencode(trim($projectId)) . '&deleted_at=is.null&order=updated_at.desc',
            [],
            $accessToken
        );

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    public function ensureDefaultBot(string $accessToken, string $projectId, string $ownerUserId, string $projectName = ''): array
    {
        $bots = $this->listBots($accessToken, $projectId);

        if ($bots !== []) {
            return is_array($bots[0] ?? null) ? $bots[0] : [];
        }

        return $this->saveBot($accessToken, \whatsappBotDefaultBotPayload($projectId, $ownerUserId, $projectName));
    }

    public function findBot(string $accessToken, string $botId, string $projectId): ?array
    {
        $response = \supabaseRestRequest(
            'GET',
            'whatsapp_bots?select=*&id=eq.' . rawurlencode($botId) . '&project_id=eq.' . rawurlencode(trim($projectId)) . '&deleted_at=is.null&limit=1',
            [],
            $accessToken
        );

        $data = $response['data'] ?? null;

        if (!is_array($data) || $data === []) {
            return null;
        }

        return is_array($data[0] ?? null) ? $data[0] : null;
    }

    public function saveBot(string $accessToken, array $payload): array
    {
        $headers = ['Prefer: return=representation'];
        $projectId = trim((string) ($payload['project_id'] ?? ''));

        if ($projectId === '') {
            throw new RuntimeException('No encontramos el proyecto del bot.');
        }

        if (isset($payload['id']) && trim((string) $payload['id']) !== '') {
            $id = trim((string) $payload['id']);
            unset($payload['id']);

            $response = \supabaseRestRequest(
                'PATCH',
                'whatsapp_bots?id=eq.' . rawurlencode($id) . '&project_id=eq.' . rawurlencode($projectId),
                $payload,
                $accessToken,
                $headers
            );
        } else {
            $response = \supabaseRestRequest(
                'POST',
                'whatsapp_bots',
                $payload,
                $accessToken,
                $headers
            );
        }

        return $this->firstRow($response, 'No fue posible guardar el bot.');
    }

    public function listTemplates(string $accessToken, string $botId, string $projectId): array
    {
        $response = \supabaseRestRequest(
            'GET',
            'whatsapp_bot_templates?select=*&bot_id=eq.' . rawurlencode($botId) . '&project_id=eq.' . rawurlencode(trim($projectId)) . '&deleted_at=is.null&order=updated_at.desc',
            [],
            $accessToken
        );

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    public function saveTemplate(string $accessToken, array $payload): array
    {
        $headers = ['Prefer: return=representation'];
        $projectId = trim((string) ($payload['project_id'] ?? ''));

        if ($projectId === '') {
            throw new RuntimeException('No encontramos el proyecto de la plantilla.');
        }

        if (isset($payload['id']) && trim((string) $payload['id']) !== '') {
            $id = trim((string) $payload['id']);
            unset($payload['id']);

            $response = \supabaseRestRequest(
                'PATCH',
                'whatsapp_bot_templates?id=eq.' . rawurlencode($id) . '&project_id=eq.' . rawurlencode($projectId),
                $payload,
                $accessToken,
                $headers
            );
        } else {
            $response = \supabaseRestRequest(
                'POST',
                'whatsapp_bot_templates',
                $payload,
                $accessToken,
                $headers
            );
        }

        return $this->firstRow($response, 'No fue posible guardar la plantilla.');
    }

    public function listConversations(string $accessToken, string $botId, string $projectId, string $filter = 'all'): array
    {
        $filters = [
            'bot_id=eq.' . rawurlencode($botId),
            'project_id=eq.' . rawurlencode(trim($projectId)),
            'archived_at=is.null',
            'order=updated_at.desc',
        ];

        if ($filter === 'bot') {
            $filters[] = 'inbox_status=eq.bot';
        } elseif ($filter === 'human') {
            $filters[] = 'inbox_status=eq.humano';
        }

        $response = \supabaseRestRequest(
            'GET',
            'whatsapp_bot_conversations?select=*&' . implode('&', $filters),
            [],
            $accessToken
        );

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    public function findConversation(string $accessToken, string $conversationId, string $botId, string $projectId): ?array
    {
        $response = \supabaseRestRequest(
            'GET',
            'whatsapp_bot_conversations?select=*&id=eq.' . rawurlencode($conversationId) . '&bot_id=eq.' . rawurlencode($botId) . '&project_id=eq.' . rawurlencode($projectId) . '&archived_at=is.null&limit=1',
            [],
            $accessToken
        );

        $data = $response['data'] ?? null;

        if (!is_array($data) || $data === []) {
            return null;
        }

        return is_array($data[0] ?? null) ? $data[0] : null;
    }

    public function findConversationByPhone(string $accessToken, string $botId, string $projectId, string $phone): ?array
    {
        $normalizedPhone = \whatsappBotSanitizePhone($phone);

        if ($normalizedPhone === '') {
            return null;
        }

        $response = \supabaseRestRequest(
            'GET',
            'whatsapp_bot_conversations?select=*&bot_id=eq.' . rawurlencode($botId) . '&project_id=eq.' . rawurlencode($projectId) . '&customer_phone=eq.' . rawurlencode($normalizedPhone) . '&archived_at=is.null&limit=1',
            [],
            $accessToken
        );

        $data = $response['data'] ?? null;

        if (!is_array($data) || $data === []) {
            return null;
        }

        return is_array($data[0] ?? null) ? $data[0] : null;
    }

    public function saveConversation(string $accessToken, array $payload): array
    {
        $headers = ['Prefer: return=representation'];
        $projectId = trim((string) ($payload['project_id'] ?? ''));
        $botId = trim((string) ($payload['bot_id'] ?? ''));

        if ($projectId === '' || $botId === '') {
            throw new RuntimeException('No encontramos el proyecto o el bot de la conversacion.');
        }

        if (isset($payload['id']) && trim((string) $payload['id']) !== '') {
            $id = trim((string) $payload['id']);
            unset($payload['id']);

            $response = \supabaseRestRequest(
                'PATCH',
                'whatsapp_bot_conversations?id=eq.' . rawurlencode($id) . '&bot_id=eq.' . rawurlencode($botId) . '&project_id=eq.' . rawurlencode($projectId),
                $payload,
                $accessToken,
                $headers
            );
        } else {
            $response = \supabaseRestRequest(
                'POST',
                'whatsapp_bot_conversations',
                $payload,
                $accessToken,
                $headers
            );
        }

        return $this->firstRow($response, 'No fue posible guardar la conversacion.');
    }

    public function listMessages(string $accessToken, string $conversationId, string $botId, string $projectId): array
    {
        $response = \supabaseRestRequest(
            'GET',
            'whatsapp_bot_messages?select=*&conversation_id=eq.' . rawurlencode($conversationId) . '&bot_id=eq.' . rawurlencode($botId) . '&project_id=eq.' . rawurlencode($projectId) . '&order=created_at.asc',
            [],
            $accessToken
        );

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    public function saveMessage(string $accessToken, array $payload): array
    {
        $response = \supabaseRestRequest(
            'POST',
            'whatsapp_bot_messages',
            $payload,
            $accessToken,
            ['Prefer: return=representation']
        );

        return $this->firstRow($response, 'No fue posible guardar el mensaje.');
    }

    public function logEvent(string $accessToken, array $payload): array
    {
        $response = \supabaseRestRequest(
            'POST',
            'whatsapp_bot_events',
            $payload,
            $accessToken,
            ['Prefer: return=representation']
        );

        return $this->firstRow($response, 'No fue posible registrar el evento.');
    }

    public function getPublicBotContext(string $publicKey): ?array
    {
        $response = \supabaseRestRequest('POST', 'rpc/get_public_whatsapp_bot_context', [
            'p_public_key' => $publicKey,
        ]);

        return $this->rpcObject($response);
    }

    public function getPublicConversationState(string $publicKey, string $customerPhone): ?array
    {
        $response = \supabaseRestRequest('POST', 'rpc/get_public_whatsapp_conversation_state', [
            'p_public_key' => $publicKey,
            'p_customer_phone' => \whatsappBotSanitizePhone($customerPhone),
        ]);

        return $this->rpcObject($response);
    }

    public function upsertPublicConversation(string $publicKey, array $payload): array
    {
        $response = \supabaseRestRequest('POST', 'rpc/upsert_public_whatsapp_conversation', [
            'p_public_key' => $publicKey,
            'p_payload' => $payload,
        ]);

        $data = $this->rpcObject($response);

        if (!is_array($data)) {
            throw new RuntimeException('No fue posible guardar la conversacion publica.');
        }

        return $data;
    }

    public function appendPublicMessage(string $publicKey, array $payload): array
    {
        $response = \supabaseRestRequest('POST', 'rpc/append_public_whatsapp_message', [
            'p_public_key' => $publicKey,
            'p_payload' => $payload,
        ]);

        $data = $this->rpcObject($response);

        if (!is_array($data)) {
            throw new RuntimeException('No fue posible registrar el mensaje del webhook.');
        }

        return $data;
    }

    public function patchPublicConversation(string $publicKey, array $payload): array
    {
        $response = \supabaseRestRequest('POST', 'rpc/patch_public_whatsapp_conversation', [
            'p_public_key' => $publicKey,
            'p_payload' => $payload,
        ]);

        $data = $this->rpcObject($response);

        if (!is_array($data)) {
            throw new RuntimeException('No fue posible actualizar la conversacion del webhook.');
        }

        return $data;
    }

    public function updatePublicMessageStatus(string $publicKey, string $externalMessageId, string $status, array $payload = []): void
    {
        \supabaseRestRequest('POST', 'rpc/update_public_whatsapp_message_status', [
            'p_public_key' => $publicKey,
            'p_external_message_id' => $externalMessageId,
            'p_delivery_status' => $status,
            'p_payload' => $payload,
        ]);
    }

    public function createProjectLead(string $projectId, array $payload): ?array
    {
        $response = \supabaseRestRequest('POST', 'rpc/create_project_customer_pipeline_lead', [
            'p_project_id' => $projectId,
            'p_payload' => $payload,
        ]);

        return $this->rpcObject($response);
    }

    public function triggerFollowUpFromLeadStage(string $accessToken, string $projectId, array $lead, string $stageKey, string $stageTitle): void
    {
        $normalizedStageKey = trim($stageKey);
        $phone = \whatsappBotSanitizePhone((string) ($lead['phone'] ?? ''));

        if ($normalizedStageKey === '' || $phone === '') {
            return;
        }

        foreach ($this->listBots($accessToken, $projectId) as $bot) {
            if (!is_array($bot)) {
                continue;
            }

            $routing = is_array($bot['routing_definition'] ?? null) ? $bot['routing_definition'] : [];
            $followUpStageKey = trim((string) ($routing['follow_up_stage_key'] ?? ''));
            $templateId = trim((string) ($routing['follow_up_template_id'] ?? ''));

            if ($followUpStageKey === '' || $followUpStageKey !== $normalizedStageKey) {
                continue;
            }

            $conversation = $this->findConversationByPhone(
                $accessToken,
                (string) ($bot['id'] ?? ''),
                $projectId,
                $phone
            );

            $botContext = is_array($conversation['bot_context'] ?? null) ? $conversation['bot_context'] : [];
            $botContext['forced_template_id'] = $templateId;
            $botContext['follow_up_stage_key'] = $normalizedStageKey;
            $botContext['follow_up_stage_title'] = $stageTitle;
            $botContext['linked_lead_id'] = (string) ($lead['id'] ?? '');

            $savedConversation = $this->saveConversation($accessToken, [
                'id' => (string) ($conversation['id'] ?? ''),
                'bot_id' => (string) ($bot['id'] ?? ''),
                'project_id' => $projectId,
                'lead_id' => (string) ($lead['id'] ?? ''),
                'customer_name' => trim((string) ($lead['full_name'] ?? '')),
                'customer_email' => trim((string) ($lead['email'] ?? '')),
                'customer_phone' => $phone,
                'customer_company' => trim((string) ($lead['company_name'] ?? '')),
                'source_label' => trim((string) ($lead['source_label'] ?? 'Seguimiento de clientes')),
                'source_reference' => (string) ($lead['id'] ?? ''),
                'conversation_state' => 'sesion_cerrada',
                'inbox_status' => 'humano',
                'session_expires_at' => gmdate('c', time() - 60),
                'last_message_preview' => 'Seguimiento pendiente desde la etapa ' . $stageTitle,
                'bot_context' => $botContext,
                'unknown_attempts' => 0,
            ]);

            $this->saveMessage($accessToken, [
                'conversation_id' => (string) ($savedConversation['id'] ?? ''),
                'bot_id' => (string) ($bot['id'] ?? ''),
                'project_id' => $projectId,
                'direction' => 'outgoing',
                'author_type' => 'system',
                'message_type' => 'status',
                'body' => 'El lead se movio a ' . $stageTitle . '. Usa una plantilla aprobada para retomar la conversacion fuera de la ventana de 24 horas.',
                'payload' => [
                    'follow_up_stage_key' => $normalizedStageKey,
                    'follow_up_template_id' => $templateId,
                ],
                'delivery_status' => 'sent',
            ]);
        }
    }

    private function firstRow(array $response, string $errorMessage): array
    {
        $data = $response['data'] ?? null;

        if (!is_array($data) || $data === []) {
            throw new RuntimeException($errorMessage);
        }

        return is_array($data[0] ?? null) ? $data[0] : $data;
    }

    private function rpcObject(array $response): ?array
    {
        $data = $response['data'] ?? null;

        if (!is_array($data)) {
            return null;
        }

        if (array_is_list($data)) {
            return is_array($data[0] ?? null) ? $data[0] : null;
        }

        return $data;
    }
}
