<?php
declare(strict_types=1);

namespace AiScaler\Forms;

use RuntimeException;

final class FormRepository
{
    public function findProject(string $accessToken, string $projectId): ?array
    {
        $normalizedProjectId = trim($projectId);

        if ($normalizedProjectId === '') {
            return null;
        }

        $response = \supabaseRestRequest(
            'GET',
            'projects?select=id,name&id=eq.' . rawurlencode($normalizedProjectId) . '&deleted_at=is.null&limit=1',
            [],
            $accessToken
        );

        $data = $response['data'] ?? null;

        if (!is_array($data) || $data === []) {
            return null;
        }

        return is_array($data[0] ?? null) ? $data[0] : null;
    }

    public function listForms(string $accessToken, string $projectId): array
    {
        $response = \supabaseRestRequest(
            'GET',
            'forms?select=*&project_id=eq.' . rawurlencode(trim($projectId)) . '&deleted_at=is.null&order=updated_at.desc',
            [],
            $accessToken
        );

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    public function findForm(string $accessToken, string $formId, string $projectId): ?array
    {
        $response = \supabaseRestRequest(
            'GET',
            'forms?select=*&id=eq.' . rawurlencode($formId) . '&project_id=eq.' . rawurlencode(trim($projectId)) . '&deleted_at=is.null&limit=1',
            [],
            $accessToken
        );

        $data = $response['data'] ?? null;

        if (!is_array($data) || $data === []) {
            return null;
        }

        return is_array($data[0] ?? null) ? $data[0] : null;
    }

    public function saveForm(string $accessToken, array $payload): array
    {
        $headers = ['Prefer: return=representation'];

        if (isset($payload['id']) && trim((string) $payload['id']) !== '') {
            $id = trim((string) $payload['id']);
            unset($payload['id']);

            $response = \supabaseRestRequest(
                'PATCH',
                'forms?id=eq.' . rawurlencode($id) . '&project_id=eq.' . rawurlencode((string) ($payload['project_id'] ?? '')),
                $payload,
                $accessToken,
                $headers
            );
        } else {
            $response = \supabaseRestRequest(
                'POST',
                'forms',
                $payload,
                $accessToken,
                $headers
            );
        }

        $data = $response['data'] ?? null;

        if (!is_array($data) || $data === []) {
            throw new RuntimeException('No fue posible guardar el formulario.');
        }

        return is_array($data[0] ?? null) ? $data[0] : $data;
    }

    public function softDeleteForm(string $accessToken, string $formId, string $projectId): void
    {
        \supabaseRestRequest(
            'PATCH',
            'forms?id=eq.' . rawurlencode($formId) . '&project_id=eq.' . rawurlencode(trim($projectId)),
            ['deleted_at' => gmdate('c')],
            $accessToken,
            ['Prefer: return=minimal']
        );
    }

    public function getPublicForm(string $identifier): ?array
    {
        $response = \supabaseRestRequest(
            'POST',
            'rpc/get_public_form_definition',
            ['p_identifier' => $identifier]
        );

        $data = $response['data'] ?? null;

        if (!is_array($data) || $data === []) {
            return null;
        }

        return is_array($data[0] ?? null) ? $data[0] : null;
    }

    public function submitPublicResponse(string $publicId, array $answers, array $metadata): array
    {
        $response = \supabaseRestRequest(
            'POST',
            'rpc/submit_public_form_response',
            [
                'p_public_id' => $publicId,
                'p_answers' => $answers,
                'p_metadata' => $metadata,
            ]
        );

        $data = $response['data'] ?? null;

        if (!is_array($data) || $data === []) {
            throw new RuntimeException('No fue posible guardar la respuesta.');
        }

        return is_array($data[0] ?? null) ? $data[0] : $data;
    }

    public function trackPublicSession(
        string $publicId,
        string $sessionKey,
        string $event,
        ?int $answeredCount = null,
        ?int $questionCount = null,
        array $metadata = []
    ): array {
        $payload = [
            'p_public_id' => $publicId,
            'p_session_key' => $sessionKey,
            'p_event' => $event,
            'p_metadata' => $metadata,
        ];

        if ($answeredCount !== null) {
            $payload['p_answered_count'] = max(0, $answeredCount);
        }

        if ($questionCount !== null) {
            $payload['p_question_count'] = max(0, $questionCount);
        }

        $response = \supabaseRestRequest(
            'POST',
            'rpc/track_public_form_session',
            $payload
        );

        $data = $response['data'] ?? null;

        if (!is_array($data) || $data === []) {
            throw new RuntimeException('No fue posible registrar la sesion del formulario.');
        }

        return is_array($data[0] ?? null) ? $data[0] : $data;
    }

    public function listFormResponses(string $accessToken, string $formId, string $projectId): array
    {
        $response = \supabaseRestRequest(
            'GET',
            'form_responses?select=id,form_id,answers,metadata,submitted_at&form_id=eq.'
            . rawurlencode(trim($formId))
            . '&project_id=eq.'
            . rawurlencode(trim($projectId))
            . '&order=submitted_at.desc',
            [],
            $accessToken
        );

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    public function listFormSessions(string $accessToken, string $formId, string $projectId): array
    {
        $response = \supabaseRestRequest(
            'GET',
            'form_sessions?select=id,form_id,session_key,status,visited_at,started_at,completed_at,abandoned_at,last_seen_at,question_count,answered_count,metadata&form_id=eq.'
            . rawurlencode(trim($formId))
            . '&project_id=eq.'
            . rawurlencode(trim($projectId))
            . '&order=visited_at.desc',
            [],
            $accessToken
        );

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }
}
