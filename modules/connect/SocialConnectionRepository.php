<?php
declare(strict_types=1);

namespace AiScaler\Connect;

use RuntimeException;

final class SocialConnectionRepository
{
    public function list(string $accessToken): array
    {
        $response = \supabaseRestRequest(
            'GET',
            'social_connections?select=*&order=created_at.desc',
            [],
            $accessToken
        );

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    public function find(string $accessToken, string $id): ?array
    {
        $response = \supabaseRestRequest(
            'GET',
            'social_connections?select=*&id=eq.' . rawurlencode($id) . '&limit=1',
            [],
            $accessToken
        );

        $data = $response['data'] ?? null;

        if (!is_array($data) || $data === []) {
            return null;
        }

        return is_array($data[0] ?? null) ? $data[0] : null;
    }

    public function save(string $accessToken, array $payload): array
    {
        $headers = [
            'Prefer: return=representation',
        ];

        if (isset($payload['id']) && trim((string) $payload['id']) !== '') {
            $id = trim((string) $payload['id']);
            unset($payload['id']);

            $response = \supabaseRestRequest(
                'PATCH',
                'social_connections?id=eq.' . rawurlencode($id),
                $payload,
                $accessToken,
                $headers
            );
        } else {
            $response = \supabaseRestRequest(
                'POST',
                'social_connections',
                $payload,
                $accessToken,
                $headers
            );
        }

        $data = $response['data'] ?? null;

        if (!is_array($data) || $data === []) {
            throw new RuntimeException('No fue posible guardar la conexion.');
        }

        return is_array($data[0] ?? null) ? $data[0] : $data;
    }

    public function delete(string $accessToken, string $id): void
    {
        \supabaseRestRequest(
            'DELETE',
            'social_connections?id=eq.' . rawurlencode($id),
            [],
            $accessToken,
            ['Prefer: return=minimal']
        );
    }
}
