<?php
declare(strict_types=1);

namespace AiScaler\Analytics;

use RuntimeException;

final class AnalyticsRepository
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

    public function listLandingPages(string $accessToken, string $projectId): array
    {
        return $this->requestList(
            $accessToken,
            'landing_pages?select=id,project_id,public_id,slug,title,status,view_count,published_at,updated_at&project_id=eq.'
            . rawurlencode(trim($projectId))
            . '&deleted_at=is.null&order=updated_at.desc'
        );
    }

    public function listForms(string $accessToken, string $projectId): array
    {
        return $this->requestList(
            $accessToken,
            'forms?select=id,project_id,public_id,slug,title,status,response_count,published_at,updated_at&project_id=eq.'
            . rawurlencode(trim($projectId))
            . '&deleted_at=is.null&order=updated_at.desc'
        );
    }

    public function listLeads(string $accessToken, string $projectId): array
    {
        return $this->requestList(
            $accessToken,
            'customer_pipeline_leads?select=id,project_id,full_name,source_label,source_type,estimated_value,currency_code,created_at,updated_at,stage_id,tags&project_id=eq.'
            . rawurlencode(trim($projectId))
            . '&deleted_at=is.null&order=created_at.desc'
        );
    }

    public function listStages(string $accessToken, string $projectId): array
    {
        return $this->requestList(
            $accessToken,
            'customer_pipeline_stages?select=id,project_id,key,title,accent_color,sort_order&project_id=eq.'
            . rawurlencode(trim($projectId))
            . '&order=sort_order.asc'
        );
    }

    public function listScheduledPosts(string $accessToken, string $projectId): array
    {
        return $this->requestList(
            $accessToken,
            'scheduled_posts?select=id,project_id,title,body,notes,scheduled_at,status,preview_provider_key,created_at,updated_at&project_id=eq.'
            . rawurlencode(trim($projectId))
            . '&order=scheduled_at.desc'
        );
    }

    public function listScheduledTargets(string $accessToken, array $posts): array
    {
        $postIds = [];

        foreach ($posts as $post) {
            $id = trim((string) ($post['id'] ?? ''));

            if ($id !== '') {
                $postIds[] = $id;
            }
        }

        $postIds = array_values(array_unique($postIds));

        if ($postIds === []) {
            return [];
        }

        return $this->requestList(
            $accessToken,
            'scheduled_post_targets?select=id,post_id,social_connection_id,provider_key,connection_label,publication_type,config,validation_snapshot,created_at,updated_at&post_id=in.('
            . implode(',', array_map('rawurlencode', $postIds))
            . ')&order=created_at.desc'
        );
    }

    public function listTrafficSnapshots(string $accessToken, string $projectId): array
    {
        return $this->requestList(
            $accessToken,
            'analytics_traffic_sources?select=*&project_id=eq.'
            . rawurlencode(trim($projectId))
            . '&order=metric_date.desc,source_label.asc'
        );
    }

    public function listCplSnapshots(string $accessToken, string $projectId): array
    {
        return $this->requestList(
            $accessToken,
            'analytics_cpl_snapshots?select=*&project_id=eq.'
            . rawurlencode(trim($projectId))
            . '&order=snapshot_date.desc,source_label.asc'
        );
    }

    public function listHeatmapPages(string $accessToken, string $projectId): array
    {
        return $this->requestList(
            $accessToken,
            'analytics_heatmap_pages?select=*&project_id=eq.'
            . rawurlencode(trim($projectId))
            . '&order=snapshot_date.desc,page_title.asc'
        );
    }

    public function listUtmRegistry(string $accessToken, string $projectId): array
    {
        return $this->requestList(
            $accessToken,
            'analytics_utm_registry?select=*&project_id=eq.'
            . rawurlencode(trim($projectId))
            . '&order=created_at.desc'
        );
    }

    public function listCampaignAlerts(string $accessToken, string $projectId): array
    {
        return $this->requestList(
            $accessToken,
            'analytics_campaign_alerts?select=*&project_id=eq.'
            . rawurlencode(trim($projectId))
            . '&order=detected_at.desc'
        );
    }

    private function requestList(string $accessToken, string $endpoint): array
    {
        try {
            $response = \supabaseRestRequest(
                'GET',
                $endpoint,
                [],
                $accessToken
            );
        } catch (RuntimeException $exception) {
            if ($this->isMissingSchemaException($exception)) {
                return [];
            }

            throw $exception;
        }

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    private function isMissingSchemaException(RuntimeException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'pgrst205')
            || str_contains($message, 'schema cache')
            || str_contains($message, 'could not find the table')
            || str_contains($message, 'does not exist');
    }
}
