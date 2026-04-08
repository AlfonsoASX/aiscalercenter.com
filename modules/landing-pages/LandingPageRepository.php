<?php
declare(strict_types=1);

namespace AiScaler\LandingPages;

use RuntimeException;

final class LandingPageRepository
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
            throw new RuntimeException('No fue posible preparar la cuenta de empresa para tus landing pages.');
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

    public function findProject(string $accessToken, string $projectId): ?array
    {
        $normalizedProjectId = trim($projectId);

        if ($normalizedProjectId === '') {
            return null;
        }

        $response = \supabaseRestRequest(
            'GET',
            'projects?select=id,business_id,name&id=eq.' . rawurlencode($normalizedProjectId) . '&deleted_at=is.null&limit=1',
            [],
            $accessToken
        );

        $data = $response['data'] ?? null;

        if (!is_array($data) || $data === []) {
            return null;
        }

        return is_array($data[0] ?? null) ? $data[0] : null;
    }

    public function listPages(string $accessToken, string $businessId, string $projectId = ''): array
    {
        $query = 'landing_pages?select=*&business_id=eq.' . rawurlencode($businessId) . '&deleted_at=is.null';

        if (trim($projectId) !== '') {
            $query .= '&project_id=eq.' . rawurlencode(trim($projectId));
        }

        $query .= '&order=updated_at.desc';

        $response = \supabaseRestRequest(
            'GET',
            $query,
            [],
            $accessToken
        );

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    public function findPage(string $accessToken, string $pageId, string $projectId = ''): ?array
    {
        $query = 'landing_pages?select=*&id=eq.' . rawurlencode($pageId) . '&deleted_at=is.null';

        if (trim($projectId) !== '') {
            $query .= '&project_id=eq.' . rawurlencode(trim($projectId));
        }

        $query .= '&limit=1';

        $response = \supabaseRestRequest(
            'GET',
            $query,
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
                'landing_pages?id=eq.' . rawurlencode($id),
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

    public function softDeletePage(string $accessToken, string $pageId, string $projectId = ''): void
    {
        $query = 'landing_pages?id=eq.' . rawurlencode($pageId);

        if (trim($projectId) !== '') {
            $query .= '&project_id=eq.' . rawurlencode(trim($projectId));
        }

        \supabaseRestRequest(
            'PATCH',
            $query,
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
