<?php
declare(strict_types=1);

namespace AiScaler\Forms;

use RuntimeException;

final class FormRepository
{
    public function ensureDefaultBusiness(string $accessToken, string $userId, string $email = ''): array
    {
        $businesses = $this->listBusinesses($accessToken);

        if ($businesses !== []) {
            return $businesses[0];
        }

        $name = 'Mi empresa';

        if (trim($email) !== '') {
            $name = 'Empresa de ' . preg_replace('/@.+$/', '', trim($email));
        }

        $response = \supabaseRestRequest(
            'POST',
            'businesses',
            [
                'owner_user_id' => $userId,
                'name' => $name,
            ],
            $accessToken,
            ['Prefer: return=representation']
        );

        $data = $response['data'] ?? null;

        if (!is_array($data) || $data === []) {
            throw new RuntimeException('No fue posible preparar la cuenta de empresa para tus formularios.');
        }

        return is_array($data[0] ?? null) ? $data[0] : $data;
    }

    public function listBusinesses(string $accessToken): array
    {
        $response = \supabaseRestRequest(
            'GET',
            'businesses?select=*&order=created_at.asc',
            [],
            $accessToken
        );

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    public function listForms(string $accessToken, string $businessId): array
    {
        $response = \supabaseRestRequest(
            'GET',
            'forms?select=*&business_id=eq.' . rawurlencode($businessId) . '&deleted_at=is.null&order=updated_at.desc',
            [],
            $accessToken
        );

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    public function findForm(string $accessToken, string $formId): ?array
    {
        $response = \supabaseRestRequest(
            'GET',
            'forms?select=*&id=eq.' . rawurlencode($formId) . '&deleted_at=is.null&limit=1',
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
                'forms?id=eq.' . rawurlencode($id),
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

    public function softDeleteForm(string $accessToken, string $formId): void
    {
        \supabaseRestRequest(
            'PATCH',
            'forms?id=eq.' . rawurlencode($formId),
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
}
