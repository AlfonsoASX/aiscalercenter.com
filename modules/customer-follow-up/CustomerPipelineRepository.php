<?php
declare(strict_types=1);

namespace AiScaler\CustomerPipeline;

use RuntimeException;

final class CustomerPipelineRepository
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

    public function getBoard(string $accessToken, string $projectId): array
    {
        return [
            'settings' => $this->getSettings($accessToken, $projectId),
            'stages' => $this->listStages($accessToken, $projectId),
            'leads' => $this->listLeads($accessToken, $projectId),
        ];
    }

    public function getSettings(string $accessToken, string $projectId): ?array
    {
        $response = \supabaseRestRequest(
            'GET',
            'customer_pipeline_settings?select=project_id,public_key&project_id=eq.' . rawurlencode(trim($projectId)) . '&limit=1',
            [],
            $accessToken
        );

        $data = $response['data'] ?? null;

        if (!is_array($data) || $data === []) {
            return null;
        }

        return is_array($data[0] ?? null) ? $data[0] : null;
    }

    public function listStages(string $accessToken, string $projectId): array
    {
        $response = \supabaseRestRequest(
            'GET',
            'customer_pipeline_stages?select=*&project_id=eq.' . rawurlencode(trim($projectId)) . '&order=sort_order.asc',
            [],
            $accessToken
        );

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    public function listLeads(string $accessToken, string $projectId): array
    {
        $response = \supabaseRestRequest(
            'GET',
            'customer_pipeline_leads?select=*&project_id=eq.' . rawurlencode(trim($projectId)) . '&deleted_at=is.null&order=sort_order.asc,created_at.desc',
            [],
            $accessToken
        );

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    public function saveLead(string $accessToken, array $payload): array
    {
        $headers = ['Prefer: return=representation'];
        $projectId = trim((string) ($payload['project_id'] ?? ''));

        if ($projectId === '') {
            throw new RuntimeException('No encontramos el proyecto del lead.');
        }

        if (isset($payload['id']) && trim((string) $payload['id']) !== '') {
            $id = trim((string) $payload['id']);
            unset($payload['id']);

            $response = \supabaseRestRequest(
                'PATCH',
                'customer_pipeline_leads?id=eq.' . rawurlencode($id) . '&project_id=eq.' . rawurlencode($projectId),
                $payload,
                $accessToken,
                $headers
            );
        } else {
            $response = \supabaseRestRequest(
                'POST',
                'customer_pipeline_leads',
                $payload,
                $accessToken,
                $headers
            );
        }

        $data = $response['data'] ?? null;

        if (!is_array($data) || $data === []) {
            throw new RuntimeException('No fue posible guardar el lead.');
        }

        return is_array($data[0] ?? null) ? $data[0] : $data;
    }

    public function moveLead(
        string $accessToken,
        string $leadId,
        string $projectId,
        string $stageId,
        float $sortOrder,
        ?string $lostReason = null
    ): array {
        $payload = [
            'stage_id' => trim($stageId),
            'sort_order' => $sortOrder,
        ];

        if ($lostReason !== null) {
            $payload['lost_reason'] = $lostReason;
        }

        $response = \supabaseRestRequest(
            'PATCH',
            'customer_pipeline_leads?id=eq.' . rawurlencode(trim($leadId)) . '&project_id=eq.' . rawurlencode(trim($projectId)),
            $payload,
            $accessToken,
            ['Prefer: return=representation']
        );

        $data = $response['data'] ?? null;

        if (!is_array($data) || $data === []) {
            throw new RuntimeException('No fue posible mover el lead.');
        }

        return is_array($data[0] ?? null) ? $data[0] : $data;
    }

    public function submitPublicLead(string $publicKey, array $payload): array
    {
        $response = \supabaseRestRequest(
            'POST',
            'rpc/submit_public_customer_lead',
            [
                'p_public_key' => $publicKey,
                'p_payload' => $payload,
            ]
        );

        $data = $response['data'] ?? null;

        if (!is_array($data) || $data === []) {
            throw new RuntimeException('No fue posible registrar el lead publico.');
        }

        return is_array($data[0] ?? null) ? $data[0] : $data;
    }
}

