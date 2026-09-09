<?php
declare(strict_types=1);

namespace AiScaler\LandingPages;

use RuntimeException;

final class LandingPageRepository
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

    public function listPages(string $accessToken, string $projectId): array
    {
        $response = \supabaseRestRequest(
            'GET',
            'landing_pages?select=*&project_id=eq.' . rawurlencode(trim($projectId)) . '&deleted_at=is.null&order=updated_at.desc',
            [],
            $accessToken
        );

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    public function findPage(string $accessToken, string $pageId, string $projectId): ?array
    {
        $response = \supabaseRestRequest(
            'GET',
            'landing_pages?select=*&id=eq.' . rawurlencode($pageId) . '&project_id=eq.' . rawurlencode(trim($projectId)) . '&deleted_at=is.null&limit=1',
            [],
            $accessToken
        );

        $data = $response['data'] ?? null;

        if (!is_array($data) || $data === []) {
            return null;
        }

        return is_array($data[0] ?? null) ? $data[0] : null;
    }

    public function savePage(string $accessToken, array $payload): array
    {
        $headers = ['Prefer: return=representation'];

        if (isset($payload['id']) && trim((string) $payload['id']) !== '') {
            $id = trim((string) $payload['id']);
            unset($payload['id']);

            $response = \supabaseRestRequest(
                'PATCH',
                'landing_pages?id=eq.' . rawurlencode($id) . '&project_id=eq.' . rawurlencode((string) ($payload['project_id'] ?? '')),
                $payload,
                $accessToken,
                $headers
            );
        } else {
            $response = \supabaseRestRequest(
                'POST',
                'landing_pages',
                $payload,
                $accessToken,
                $headers
            );
        }

        $data = $response['data'] ?? null;

        if (!is_array($data) || $data === []) {
            throw new RuntimeException('No fue posible guardar la landing page.');
        }

        return is_array($data[0] ?? null) ? $data[0] : $data;
    }

    public function softDeletePage(string $accessToken, string $pageId, string $projectId): void
    {
        \supabaseRestRequest(
            'PATCH',
            'landing_pages?id=eq.' . rawurlencode($pageId) . '&project_id=eq.' . rawurlencode(trim($projectId)),
            ['deleted_at' => gmdate('c')],
            $accessToken,
            ['Prefer: return=minimal']
        );
    }

    public function getPublicPage(string $identifier): ?array
    {
        $response = \supabaseRestRequest(
            'POST',
            'rpc/get_public_landing_page_definition',
            ['p_identifier' => $identifier]
        );

        $data = $response['data'] ?? null;

        if (!is_array($data) || $data === []) {
            return null;
        }

        return is_array($data[0] ?? null) ? $data[0] : null;
    }
}
