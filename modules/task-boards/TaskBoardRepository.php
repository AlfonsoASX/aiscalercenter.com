<?php
declare(strict_types=1);

namespace AiScaler\TaskBoards;

use RuntimeException;

final class TaskBoardRepository
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

    public function listProjectMembers(string $accessToken, string $projectId): array
    {
        $response = \supabaseRestRequest(
            'GET',
            'project_members?select=id,user_id,invited_email,role,status&project_id=eq.' . rawurlencode(trim($projectId)) . '&status=eq.active&order=created_at.asc',
            [],
            $accessToken
        );

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    public function listBoards(string $accessToken, string $projectId): array
    {
        $response = \supabaseRestRequest(
            'GET',
            'task_boards?select=id,project_id,title,description,sort_order,settings,created_at,updated_at&project_id=eq.' . rawurlencode(trim($projectId)) . '&order=sort_order.asc,updated_at.desc',
            [],
            $accessToken
        );

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    public function findBoard(string $accessToken, string $projectId, string $boardId): ?array
    {
        $response = \supabaseRestRequest(
            'GET',
            'task_boards?select=id,project_id,title,description,sort_order,settings,created_at,updated_at&id=eq.' . rawurlencode(trim($boardId)) . '&project_id=eq.' . rawurlencode(trim($projectId)) . '&limit=1',
            [],
            $accessToken
        );

        $data = $response['data'] ?? null;

        if (!is_array($data) || $data === []) {
            return null;
        }

        return is_array($data[0] ?? null) ? $data[0] : null;
    }

    public function getBoardState(string $accessToken, string $projectId, string $boardId): array
    {
        $board = $this->findBoard($accessToken, $projectId, $boardId);

        if (!is_array($board)) {
            throw new RuntimeException('No encontramos el tablero solicitado.');
        }

        return [
            'board' => $board,
            'columns' => $this->listColumns($accessToken, $boardId),
            'swimlanes' => $this->listSwimlanes($accessToken, $boardId),
            'labels' => $this->listLabels($accessToken, $boardId),
            'cards' => $this->listCards($accessToken, $boardId),
            'comments' => $this->listComments($accessToken, $boardId),
            'activity' => $this->listActivity($accessToken, $boardId),
        ];
    }

    public function listColumns(string $accessToken, string $boardId): array
    {
        $response = \supabaseRestRequest(
            'GET',
            'task_board_columns?select=*&board_id=eq.' . rawurlencode(trim($boardId)) . '&order=sort_order.asc',
            [],
            $accessToken
        );

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    public function listSwimlanes(string $accessToken, string $boardId): array
    {
        $response = \supabaseRestRequest(
            'GET',
            'task_board_swimlanes?select=*&board_id=eq.' . rawurlencode(trim($boardId)) . '&order=sort_order.asc',
            [],
            $accessToken
        );

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    public function listLabels(string $accessToken, string $boardId): array
    {
        $response = \supabaseRestRequest(
            'GET',
            'task_board_labels?select=*&board_id=eq.' . rawurlencode(trim($boardId)) . '&order=sort_order.asc',
            [],
            $accessToken
        );

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    public function listCards(string $accessToken, string $boardId): array
    {
        $response = \supabaseRestRequest(
            'GET',
            'task_board_cards?select=*&board_id=eq.' . rawurlencode(trim($boardId)) . '&order=sort_order.asc,created_at.asc',
            [],
            $accessToken
        );

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    public function listComments(string $accessToken, string $boardId): array
    {
        $response = \supabaseRestRequest(
            'GET',
            'task_board_comments?select=*&board_id=eq.' . rawurlencode(trim($boardId)) . '&order=created_at.asc',
            [],
            $accessToken
        );

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    public function listActivity(string $accessToken, string $boardId, int $limit = 120): array
    {
        $response = \supabaseRestRequest(
            'GET',
            'task_board_activity?select=*&board_id=eq.' . rawurlencode(trim($boardId)) . '&order=created_at.desc&limit=' . max(20, min(200, $limit)),
            [],
            $accessToken
        );

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    public function findCard(string $accessToken, string $boardId, string $cardId): ?array
    {
        $response = \supabaseRestRequest(
            'GET',
            'task_board_cards?select=*&board_id=eq.' . rawurlencode(trim($boardId)) . '&id=eq.' . rawurlencode(trim($cardId)) . '&limit=1',
            [],
            $accessToken
        );

        $data = $response['data'] ?? null;

        if (!is_array($data) || $data === []) {
            return null;
        }

        return is_array($data[0] ?? null) ? $data[0] : null;
    }

    public function createBoard(string $accessToken, array $payload): array
    {
        $response = \supabaseRestRequest(
            'POST',
            'task_boards',
            $payload,
            $accessToken,
            ['Prefer: return=representation']
        );

        $data = $response['data'] ?? null;
        $board = is_array($data[0] ?? null) ? $data[0] : null;

        if (!is_array($board)) {
            throw new RuntimeException('No fue posible crear el tablero.');
        }

        $this->seedDefaultStructure($accessToken, (string) ($board['id'] ?? ''));

        return $board;
    }

    public function updateBoard(string $accessToken, string $projectId, string $boardId, array $payload): array
    {
        $response = \supabaseRestRequest(
            'PATCH',
            'task_boards?id=eq.' . rawurlencode(trim($boardId)) . '&project_id=eq.' . rawurlencode(trim($projectId)),
            $payload,
            $accessToken,
            ['Prefer: return=representation']
        );

        $data = $response['data'] ?? null;
        $board = is_array($data[0] ?? null) ? $data[0] : null;

        if (!is_array($board)) {
            throw new RuntimeException('No fue posible actualizar el tablero.');
        }

        return $board;
    }

    public function deleteBoard(string $accessToken, string $projectId, string $boardId): void
    {
        \supabaseRestRequest(
            'DELETE',
            'task_boards?id=eq.' . rawurlencode(trim($boardId)) . '&project_id=eq.' . rawurlencode(trim($projectId)),
            [],
            $accessToken
        );
    }

    public function saveStructure(
        string $accessToken,
        string $boardId,
        array $columns,
        array $swimlanes,
        array $labels
    ): array {
        $existingColumns = $this->listColumns($accessToken, $boardId);
        $existingSwimlanes = $this->listSwimlanes($accessToken, $boardId);
        $existingLabels = $this->listLabels($accessToken, $boardId);

        $columnRows = [];
        foreach (array_values($columns) as $index => $column) {
            if (!is_array($column)) {
                continue;
            }

            $columnRows[] = [
                'id' => trim((string) ($column['id'] ?? '')),
                'board_id' => $boardId,
                'title' => trim((string) ($column['title'] ?? '')),
                'accent_color' => trim((string) ($column['accent_color'] ?? '#1A73E8')) ?: '#1A73E8',
                'wip_limit' => ($column['wip_limit'] ?? '') === '' ? null : (int) ($column['wip_limit'] ?? 0),
                'sort_order' => $index * 10 + 10,
                'is_archived' => false,
            ];
        }

        $swimlaneRows = [];
        foreach (array_values($swimlanes) as $index => $swimlane) {
            if (!is_array($swimlane)) {
                continue;
            }

            $swimlaneRows[] = [
                'id' => trim((string) ($swimlane['id'] ?? '')),
                'board_id' => $boardId,
                'title' => trim((string) ($swimlane['title'] ?? '')),
                'accent_color' => trim((string) ($swimlane['accent_color'] ?? '#EEF3FB')) ?: '#EEF3FB',
                'sort_order' => $index * 10 + 10,
                'is_default' => $index === 0,
                'is_archived' => false,
            ];
        }

        $labelRows = [];
        foreach (array_values($labels) as $index => $label) {
            if (!is_array($label)) {
                continue;
            }

            $labelRows[] = [
                'id' => trim((string) ($label['id'] ?? '')),
                'board_id' => $boardId,
                'title' => trim((string) ($label['title'] ?? '')),
                'color' => trim((string) ($label['color'] ?? '#2F7CEF')) ?: '#2F7CEF',
                'sort_order' => $index * 10 + 10,
            ];
        }

        $this->upsertRows('task_board_columns', $columnRows, $accessToken);
        $this->upsertRows('task_board_swimlanes', $swimlaneRows, $accessToken);
        $this->upsertRows('task_board_labels', $labelRows, $accessToken);

        $columnIds = array_values(array_filter(array_map(static fn (array $row): string => trim((string) ($row['id'] ?? '')), $columnRows)));
        $swimlaneIds = array_values(array_filter(array_map(static fn (array $row): string => trim((string) ($row['id'] ?? '')), $swimlaneRows)));
        $labelIds = array_values(array_filter(array_map(static fn (array $row): string => trim((string) ($row['id'] ?? '')), $labelRows)));

        $removedColumnIds = $this->diffIds($existingColumns, $columnIds);
        $removedSwimlaneIds = $this->diffIds($existingSwimlanes, $swimlaneIds);
        $removedLabelIds = $this->diffIds($existingLabels, $labelIds);

        if ($removedColumnIds !== [] && $this->cardsExistForColumnIds($accessToken, $boardId, $removedColumnIds)) {
            throw new RuntimeException('Mueve o elimina las tareas de una columna antes de quitarla.');
        }

        if ($removedSwimlaneIds !== [] && $this->cardsExistForSwimlaneIds($accessToken, $boardId, $removedSwimlaneIds)) {
            throw new RuntimeException('Mueve o elimina las tareas de un carril antes de quitarlo.');
        }

        if ($removedColumnIds !== []) {
            $this->deleteRows('task_board_columns', $removedColumnIds, ['board_id' => $boardId], $accessToken);
        }

        if ($removedSwimlaneIds !== []) {
            $this->deleteRows('task_board_swimlanes', $removedSwimlaneIds, ['board_id' => $boardId], $accessToken);
        }

        if ($removedLabelIds !== []) {
            $this->deleteRows('task_board_labels', $removedLabelIds, ['board_id' => $boardId], $accessToken);
        }

        return [
            'columns' => $this->listColumns($accessToken, $boardId),
            'swimlanes' => $this->listSwimlanes($accessToken, $boardId),
            'labels' => $this->listLabels($accessToken, $boardId),
        ];
    }

    public function saveCard(string $accessToken, array $payload): array
    {
        $headers = ['Prefer: return=representation'];
        $boardId = trim((string) ($payload['board_id'] ?? ''));

        if ($boardId === '') {
            throw new RuntimeException('No encontramos el tablero de la tarea.');
        }

        if (isset($payload['id']) && trim((string) $payload['id']) !== '') {
            $cardId = trim((string) $payload['id']);
            unset($payload['id']);

            $response = \supabaseRestRequest(
                'PATCH',
                'task_board_cards?id=eq.' . rawurlencode($cardId) . '&board_id=eq.' . rawurlencode($boardId),
                $payload,
                $accessToken,
                $headers
            );
        } else {
            $response = \supabaseRestRequest(
                'POST',
                'task_board_cards',
                $payload,
                $accessToken,
                $headers
            );
        }

        $data = $response['data'] ?? null;
        $card = is_array($data[0] ?? null) ? $data[0] : null;

        if (!is_array($card)) {
            throw new RuntimeException('No fue posible guardar la tarea.');
        }

        return $card;
    }

    public function moveCard(
        string $accessToken,
        string $boardId,
        string $cardId,
        string $columnId,
        string $swimlaneId,
        float $sortOrder
    ): array {
        $response = \supabaseRestRequest(
            'PATCH',
            'task_board_cards?id=eq.' . rawurlencode(trim($cardId)) . '&board_id=eq.' . rawurlencode(trim($boardId)),
            [
                'column_id' => trim($columnId),
                'swimlane_id' => trim($swimlaneId),
                'sort_order' => $sortOrder,
            ],
            $accessToken,
            ['Prefer: return=representation']
        );

        $data = $response['data'] ?? null;
        $card = is_array($data[0] ?? null) ? $data[0] : null;

        if (!is_array($card)) {
            throw new RuntimeException('No fue posible mover la tarea.');
        }

        return $card;
    }

    public function deleteCard(string $accessToken, string $boardId, string $cardId): void
    {
        \supabaseRestRequest(
            'DELETE',
            'task_board_cards?id=eq.' . rawurlencode(trim($cardId)) . '&board_id=eq.' . rawurlencode(trim($boardId)),
            [],
            $accessToken
        );
    }

    public function saveComment(string $accessToken, array $payload): array
    {
        $response = \supabaseRestRequest(
            'POST',
            'task_board_comments',
            $payload,
            $accessToken,
            ['Prefer: return=representation']
        );

        $data = $response['data'] ?? null;
        $comment = is_array($data[0] ?? null) ? $data[0] : null;

        if (!is_array($comment)) {
            throw new RuntimeException('No fue posible guardar el comentario.');
        }

        return $comment;
    }

    public function addActivity(string $accessToken, array $payload): void
    {
        \supabaseRestRequest(
            'POST',
            'task_board_activity',
            $payload,
            $accessToken
        );
    }

    private function seedDefaultStructure(string $accessToken, string $boardId): void
    {
        $this->upsertRows('task_board_columns', [
            [
                'board_id' => $boardId,
                'title' => 'Pendientes',
                'accent_color' => '#1A73E8',
                'wip_limit' => null,
                'sort_order' => 10,
                'is_archived' => false,
            ],
            [
                'board_id' => $boardId,
                'title' => 'En progreso',
                'accent_color' => '#D93025',
                'wip_limit' => 5,
                'sort_order' => 20,
                'is_archived' => false,
            ],
            [
                'board_id' => $boardId,
                'title' => 'Revision',
                'accent_color' => '#DF9C0A',
                'wip_limit' => 3,
                'sort_order' => 30,
                'is_archived' => false,
            ],
            [
                'board_id' => $boardId,
                'title' => 'Hecho',
                'accent_color' => '#188038',
                'wip_limit' => null,
                'sort_order' => 40,
                'is_archived' => false,
            ],
        ], $accessToken);

        $this->upsertRows('task_board_swimlanes', [
            [
                'board_id' => $boardId,
                'title' => 'General',
                'accent_color' => '#EEF3FB',
                'sort_order' => 10,
                'is_default' => true,
                'is_archived' => false,
            ],
            [
                'board_id' => $boardId,
                'title' => 'Expedite',
                'accent_color' => '#FFF1F0',
                'sort_order' => 20,
                'is_default' => false,
                'is_archived' => false,
            ],
        ], $accessToken);

        $this->upsertRows('task_board_labels', [
            [
                'board_id' => $boardId,
                'title' => 'Urgente',
                'color' => '#D93025',
                'sort_order' => 10,
            ],
            [
                'board_id' => $boardId,
                'title' => 'Bug',
                'color' => '#DF9C0A',
                'sort_order' => 20,
            ],
            [
                'board_id' => $boardId,
                'title' => 'Diseno',
                'color' => '#2F7CEF',
                'sort_order' => 30,
            ],
        ], $accessToken);
    }

    private function upsertRows(string $table, array $rows, string $accessToken): void
    {
        $normalizedRows = array_values(array_filter(array_map(static function (mixed $row): ?array {
            if (!is_array($row)) {
                return null;
            }

            $normalized = [];

            foreach ($row as $key => $value) {
                if ($value === '') {
                    continue;
                }

                $normalized[$key] = $value;
            }

            return $normalized;
        }, $rows)));

        if ($normalizedRows === []) {
            return;
        }

        \supabaseRestRequest(
            'POST',
            $table . '?on_conflict=id',
            $normalizedRows,
            $accessToken,
            ['Prefer: resolution=merge-duplicates,return=representation']
        );
    }

    private function deleteRows(string $table, array $ids, array $filters, string $accessToken): void
    {
        if ($ids === []) {
            return;
        }

        $endpoint = $table . '?id=in.(' . implode(',', array_map('rawurlencode', array_values($ids))) . ')';

        foreach ($filters as $filterKey => $filterValue) {
            $endpoint .= '&' . rawurlencode((string) $filterKey) . '=eq.' . rawurlencode(trim((string) $filterValue));
        }

        \supabaseRestRequest('DELETE', $endpoint, [], $accessToken);
    }

    private function diffIds(array $existingRows, array $keptIds): array
    {
        $existingIds = array_values(array_filter(array_map(static fn (mixed $row): string => is_array($row) ? trim((string) ($row['id'] ?? '')) : '', $existingRows)));

        if ($existingIds === []) {
            return [];
        }

        return array_values(array_diff($existingIds, $keptIds));
    }

    private function cardsExistForColumnIds(string $accessToken, string $boardId, array $columnIds): bool
    {
        if ($columnIds === []) {
            return false;
        }

        $response = \supabaseRestRequest(
            'GET',
            'task_board_cards?select=id&board_id=eq.' . rawurlencode(trim($boardId)) . '&column_id=in.(' . implode(',', array_map('rawurlencode', $columnIds)) . ')&limit=1',
            [],
            $accessToken
        );

        return is_array($response['data'] ?? null) && $response['data'] !== [];
    }

    private function cardsExistForSwimlaneIds(string $accessToken, string $boardId, array $swimlaneIds): bool
    {
        if ($swimlaneIds === []) {
            return false;
        }

        $response = \supabaseRestRequest(
            'GET',
            'task_board_cards?select=id&board_id=eq.' . rawurlencode(trim($boardId)) . '&swimlane_id=in.(' . implode(',', array_map('rawurlencode', $swimlaneIds)) . ')&limit=1',
            [],
            $accessToken
        );

        return is_array($response['data'] ?? null) && $response['data'] !== [];
    }
}
