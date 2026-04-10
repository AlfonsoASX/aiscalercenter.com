<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/supabase_api.php';
require_once __DIR__ . '/TaskBoardRepository.php';

function normalizeTaskBoardException(Throwable $exception): string
{
    $message = $exception->getMessage();
    $normalized = strtolower($message);

    if (
        str_contains($normalized, 'could not find the table')
        || str_contains($normalized, 'pgrst205')
        || str_contains($normalized, 'schema cache')
        || str_contains($normalized, 'does not exist')
        || str_contains($normalized, 'task_board_')
    ) {
        return 'La estructura de Tableros de tareas aun no existe. Ejecuta supabase/task_boards_schema.sql en Supabase.';
    }

    if (str_contains($normalized, 'row-level security')) {
        return 'Supabase bloqueo la operacion por permisos. Revisa supabase/task_boards_schema.sql.';
    }

    return $message !== '' ? $message : 'Ocurrio un error inesperado en Tableros de tareas.';
}

function taskBoardsJsonEncode(array $payload): string
{
    return json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    ) ?: '{}';
}

function taskBoardsBoardExists(array $boards, string $boardId): bool
{
    foreach ($boards as $board) {
        if ((string) ($board['id'] ?? '') === $boardId) {
            return true;
        }
    }

    return false;
}

function taskBoardsNormalizeBoards(array $boards): array
{
    return array_values(array_filter(array_map(static function ($board): ?array {
        if (!is_array($board)) {
            return null;
        }

        return [
            'id' => (string) ($board['id'] ?? ''),
            'project_id' => (string) ($board['project_id'] ?? ''),
            'title' => (string) ($board['title'] ?? ''),
            'description' => (string) ($board['description'] ?? ''),
            'sort_order' => (int) ($board['sort_order'] ?? 0),
            'settings' => is_array($board['settings'] ?? null) ? $board['settings'] : [],
            'created_at' => (string) ($board['created_at'] ?? ''),
            'updated_at' => (string) ($board['updated_at'] ?? ''),
        ];
    }, $boards)));
}

function taskBoardsNormalizeMembers(array $members, string $currentUserId, string $currentUserEmail): array
{
    return array_values(array_filter(array_map(static function ($member) use ($currentUserId, $currentUserEmail): ?array {
        if (!is_array($member) || (string) ($member['status'] ?? '') !== 'active') {
            return null;
        }

        $memberId = (string) ($member['id'] ?? '');
        $userId = (string) ($member['user_id'] ?? '');
        $email = strtolower(trim((string) ($member['invited_email'] ?? '')));
        $role = (string) ($member['role'] ?? 'member');
        $label = $email;

        if ($label === '' && $userId !== '') {
            if ($userId === $currentUserId) {
                $label = $currentUserEmail !== '' ? $currentUserEmail : 'Tu usuario';
            } else {
                $label = match ($role) {
                    'owner' => 'Propietario del proyecto',
                    'admin' => 'Administrador del proyecto',
                    default => 'Miembro del proyecto',
                };
            }
        }

        if ($label === '') {
            $label = 'Miembro del proyecto';
        }

        return [
            'id' => $memberId,
            'user_id' => $userId,
            'email' => $email,
            'role' => $role,
            'label' => $label,
            'is_current_user' => $userId !== '' && $userId === $currentUserId,
        ];
    }, $members)));
}

function taskBoardsNormalizeBoardState(array $state): array
{
    return [
        'board' => is_array($state['board'] ?? null) ? $state['board'] : null,
        'columns' => array_values(is_array($state['columns'] ?? null) ? $state['columns'] : []),
        'swimlanes' => array_values(is_array($state['swimlanes'] ?? null) ? $state['swimlanes'] : []),
        'labels' => array_values(is_array($state['labels'] ?? null) ? $state['labels'] : []),
        'cards' => array_values(is_array($state['cards'] ?? null) ? $state['cards'] : []),
        'comments' => array_values(is_array($state['comments'] ?? null) ? $state['comments'] : []),
        'activity' => array_values(is_array($state['activity'] ?? null) ? $state['activity'] : []),
    ];
}
