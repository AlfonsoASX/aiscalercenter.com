<?php
declare(strict_types=1);

namespace AiScaler\Tools;

use RuntimeException;

final class ToolRepository
{
    public function listCategories(string $accessToken): array
    {
        $response = \supabaseRestRequest(
            'GET',
            'tool_categories?select=key,label,description,sort_order,is_active&order=sort_order.asc',
            [],
            $accessToken
        );

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    public function listTools(string $accessToken, ?string $categoryKey = null): array
    {
        $endpoint = 'tools?select=*'
            . '&order=sort_order.asc,title.asc';

        if ($categoryKey !== null && trim($categoryKey) !== '') {
            $endpoint .= '&category_key=eq.' . rawurlencode(trim($categoryKey));
        }

        $response = \supabaseRestRequest(
            'GET',
            $endpoint,
            [],
            $accessToken
        );

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    public function findById(string $accessToken, string $id): ?array
    {
        return $this->findOne(
            $accessToken,
            'tools?select=*&id=eq.' . rawurlencode($id) . '&limit=1'
        );
    }

    public function findBySlug(string $accessToken, string $slug): ?array
    {
        return $this->findOne(
            $accessToken,
            'tools?select=*&slug=eq.' . rawurlencode($slug) . '&limit=1'
        );
    }

    public function save(string $accessToken, array $payload): array
    {
        $headers = ['Prefer: return=representation'];

        if (isset($payload['id']) && trim((string) $payload['id']) !== '') {
            $id = trim((string) $payload['id']);
            unset($payload['id']);

            $response = \supabaseRestRequest(
                'PATCH',
                'tools?id=eq.' . rawurlencode($id),
                $payload,
                $accessToken,
                $headers
            );
        } else {
            $response = \supabaseRestRequest(
                'POST',
                'tools',
                $payload,
                $accessToken,
                $headers
            );
        }

        $data = $response['data'] ?? null;

        if (!is_array($data) || $data === []) {
            throw new RuntimeException('No fue posible guardar la herramienta.');
        }

        return is_array($data[0] ?? null) ? $data[0] : $data;
    }

    public function delete(string $accessToken, string $id): void
    {
        \supabaseRestRequest(
            'DELETE',
            'tools?id=eq.' . rawurlencode($id),
            [],
            $accessToken,
            ['Prefer: return=minimal']
        );
    }

    private function findOne(string $accessToken, string $endpoint): ?array
    {
        $response = \supabaseRestRequest(
            'GET',
            $endpoint,
            [],
            $accessToken
        );

        $data = $response['data'] ?? null;

        if (!is_array($data) || $data === []) {
            return null;
        }

        return is_array($data[0] ?? null) ? $data[0] : null;
    }
}
