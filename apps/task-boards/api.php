<?php
declare(strict_types=1);

use AiScaler\TaskBoards\TaskBoardRepository;

require_once __DIR__ . '/../../modules/task-boards/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

$toolContext = is_array($toolRuntimeContext ?? null) ? $toolRuntimeContext : [];
$accessToken = trim((string) ($toolContext['access_token'] ?? ''));
$userId = trim((string) ($toolContext['user_id'] ?? ''));
$userEmail = trim((string) ($toolContext['user_email'] ?? ''));
$projectContext = is_array($toolContext['project'] ?? null) ? $toolContext['project'] : [];
$activeProjectId = trim((string) ($projectContext['id'] ?? ''));
$action = trim((string) ($_GET['action'] ?? 'bootstrap'));
$repository = new TaskBoardRepository();

try {
    if ($accessToken === '' || $userId === '') {
        throw new RuntimeException('No encontramos la sesion segura de Tableros de tareas.');
    }

    if ($activeProjectId === '') {
        throw new RuntimeException('Selecciona un proyecto antes de usar Tableros de tareas.');
    }

    $project = $repository->findProject($accessToken, $activeProjectId);

    if (!is_array($project)) {
        throw new RuntimeException('No encontramos el proyecto activo de los tableros.');
    }

    if ($action === 'bootstrap') {
        $requestedBoardId = trim((string) ($_GET['board_id'] ?? ($_POST['board_id'] ?? '')));

        taskBoardsSendJson([
            'success' => true,
            'data' => taskBoardsBuildBootstrapPayload(
                $repository,
                $accessToken,
                $activeProjectId,
                $requestedBoardId,
                $project,
                $userId,
                $userEmail
            ),
        ]);
    }

    if ($action === 'save-board') {
        taskBoardsAssertMethod('POST');
        $payload = taskBoardsReadJsonPayload();
        $boardId = trim((string) ($payload['id'] ?? ''));
        $title = trim((string) ($payload['title'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));

        if ($title === '') {
            throw new InvalidArgumentException('El tablero necesita un titulo.');
        }

        if ($boardId === '') {
            $boards = $repository->listBoards($accessToken, $activeProjectId);
            $lastSort = $boards === [] ? 0 : max(array_map(static fn (array $board): int => (int) ($board['sort_order'] ?? 0), $boards));
            $savedBoard = $repository->createBoard($accessToken, [
                'project_id' => $activeProjectId,
                'title' => $title,
                'description' => $description,
                'sort_order' => $lastSort + 10,
                'created_by' => $userId,
                'created_by_email' => $userEmail,
                'settings' => new stdClass(),
            ]);
        } else {
            $savedBoard = $repository->updateBoard($accessToken, $activeProjectId, $boardId, [
                'title' => $title,
                'description' => $description,
            ]);
        }

        taskBoardsSendJson([
            'success' => true,
            'message' => $boardId === '' ? 'Tablero creado correctamente.' : 'Tablero actualizado correctamente.',
            'data' => taskBoardsBuildBootstrapPayload(
                $repository,
                $accessToken,
                $activeProjectId,
                (string) ($savedBoard['id'] ?? ''),
                $project,
                $userId,
                $userEmail
            ),
        ]);
    }

    if ($action === 'delete-board') {
        taskBoardsAssertMethod('POST');
        $payload = taskBoardsReadJsonPayload();
        $boardId = trim((string) ($payload['board_id'] ?? ''));

        if ($boardId === '') {
            throw new InvalidArgumentException('No encontramos el tablero que intentas eliminar.');
        }

        $repository->deleteBoard($accessToken, $activeProjectId, $boardId);

        taskBoardsSendJson([
            'success' => true,
            'message' => 'Tablero eliminado correctamente.',
            'data' => taskBoardsBuildBootstrapPayload(
                $repository,
                $accessToken,
                $activeProjectId,
                '',
                $project,
                $userId,
                $userEmail
            ),
        ]);
    }

    if ($action === 'save-structure') {
        taskBoardsAssertMethod('POST');
        $payload = taskBoardsReadJsonPayload();
        $boardId = trim((string) ($payload['board_id'] ?? ''));
        $columns = taskBoardsNormalizeStructurePayload($payload['columns'] ?? [], 'columns');
        $swimlanes = taskBoardsNormalizeStructurePayload($payload['swimlanes'] ?? [], 'swimlanes');
        $labels = taskBoardsNormalizeStructurePayload($payload['labels'] ?? [], 'labels');

        if ($boardId === '') {
            throw new InvalidArgumentException('No encontramos el tablero que intentas configurar.');
        }

        if ($columns === []) {
            throw new InvalidArgumentException('El tablero necesita al menos una columna.');
        }

        if ($swimlanes === []) {
            throw new InvalidArgumentException('El tablero necesita al menos un carril.');
        }

        $repository->saveStructure($accessToken, $boardId, $columns, $swimlanes, $labels);
        $repository->addActivity($accessToken, [
            'board_id' => $boardId,
            'event_type' => 'board_structure_updated',
            'description' => 'Actualizo columnas, carriles o etiquetas del tablero.',
            'actor_user_id' => $userId,
            'actor_email' => $userEmail,
            'payload' => [
                'columns' => count($columns),
                'swimlanes' => count($swimlanes),
                'labels' => count($labels),
            ],
        ]);

        taskBoardsSendJson([
            'success' => true,
            'message' => 'Estructura del tablero actualizada.',
            'data' => taskBoardsBuildBoardPayload($repository, $accessToken, $activeProjectId, $boardId),
        ]);
    }

    if ($action === 'save-card') {
        taskBoardsAssertMethod('POST');
        $payload = taskBoardsReadJsonPayload();
        $boardId = trim((string) ($payload['board_id'] ?? ''));
        $cardId = trim((string) ($payload['id'] ?? ''));
        $title = trim((string) ($payload['title'] ?? ''));

        if ($boardId === '') {
            throw new InvalidArgumentException('No encontramos el tablero de la tarea.');
        }

        if ($title === '') {
            throw new InvalidArgumentException('La tarea necesita un titulo.');
        }

        $boardPayload = taskBoardsBuildBoardPayload($repository, $accessToken, $activeProjectId, $boardId);
        $existingCard = $cardId !== '' ? $repository->findCard($accessToken, $boardId, $cardId) : null;
        [$defaultColumnId, $defaultSwimlaneId, $defaultSortOrder] = taskBoardsResolveCardPlacement(
            $boardPayload,
            trim((string) ($payload['column_id'] ?? '')),
            trim((string) ($payload['swimlane_id'] ?? '')),
            $existingCard
        );

        $savedCard = $repository->saveCard($accessToken, [
            'id' => $cardId,
            'board_id' => $boardId,
            'column_id' => $defaultColumnId,
            'swimlane_id' => $defaultSwimlaneId,
            'title' => $title,
            'description_markdown' => trim((string) ($payload['description_markdown'] ?? '')),
            'priority' => taskBoardsResolvePriority((string) ($payload['priority'] ?? 'medium')),
            'start_date' => taskBoardsNormalizeDateValue($payload['start_date'] ?? null),
            'due_date' => taskBoardsNormalizeDateValue($payload['due_date'] ?? null),
            'assigned_member_ids' => taskBoardsNormalizeStringArray($payload['assigned_member_ids'] ?? []),
            'label_ids' => taskBoardsNormalizeStringArray($payload['label_ids'] ?? []),
            'checklist' => taskBoardsNormalizeChecklist($payload['checklist'] ?? []),
            'metadata' => is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [],
            'sort_order' => is_numeric($payload['sort_order'] ?? null) ? (float) $payload['sort_order'] : $defaultSortOrder,
            'created_by' => $existingCard['created_by'] ?? $userId,
            'created_by_email' => trim((string) ($existingCard['created_by_email'] ?? $userEmail)),
        ]);

        $repository->addActivity($accessToken, [
            'board_id' => $boardId,
            'card_id' => (string) ($savedCard['id'] ?? ''),
            'event_type' => $cardId === '' ? 'card_created' : 'card_updated',
            'description' => $cardId === '' ? 'Creo la tarea "' . $title . '".' : 'Actualizo la tarea "' . $title . '".',
            'actor_user_id' => $userId,
            'actor_email' => $userEmail,
            'payload' => [
                'column_id' => $defaultColumnId,
                'swimlane_id' => $defaultSwimlaneId,
            ],
        ]);

        taskBoardsSendJson([
            'success' => true,
            'message' => $cardId === '' ? 'Tarea creada correctamente.' : 'Tarea actualizada correctamente.',
            'saved_card_id' => (string) ($savedCard['id'] ?? ''),
            'data' => taskBoardsBuildBoardPayload($repository, $accessToken, $activeProjectId, $boardId),
        ]);
    }

    if ($action === 'move-card') {
        taskBoardsAssertMethod('POST');
        $payload = taskBoardsReadJsonPayload();
        $boardId = trim((string) ($payload['board_id'] ?? ''));
        $cardId = trim((string) ($payload['card_id'] ?? ''));
        $columnId = trim((string) ($payload['column_id'] ?? ''));
        $swimlaneId = trim((string) ($payload['swimlane_id'] ?? ''));
        $sortOrder = is_numeric($payload['sort_order'] ?? null) ? (float) $payload['sort_order'] : 0;
        $existingCard = $repository->findCard($accessToken, $boardId, $cardId);

        if ($boardId === '' || $cardId === '' || $columnId === '' || $swimlaneId === '') {
            throw new InvalidArgumentException('No encontramos los datos necesarios para mover la tarea.');
        }

        if (!is_array($existingCard)) {
            throw new RuntimeException('No encontramos la tarea que intentas mover.');
        }

        $movedCard = $repository->moveCard($accessToken, $boardId, $cardId, $columnId, $swimlaneId, $sortOrder);
        $repository->addActivity($accessToken, [
            'board_id' => $boardId,
            'card_id' => $cardId,
            'event_type' => 'card_moved',
            'description' => 'Movio la tarea "' . trim((string) ($existingCard['title'] ?? 'Tarea')) . '" dentro del tablero.',
            'actor_user_id' => $userId,
            'actor_email' => $userEmail,
            'payload' => [
                'from_column_id' => (string) ($existingCard['column_id'] ?? ''),
                'to_column_id' => $columnId,
                'from_swimlane_id' => (string) ($existingCard['swimlane_id'] ?? ''),
                'to_swimlane_id' => $swimlaneId,
            ],
        ]);

        taskBoardsSendJson([
            'success' => true,
            'message' => 'Tarea movida correctamente.',
            'data' => [
                'card' => $movedCard,
            ],
        ]);
    }

    if ($action === 'delete-card') {
        taskBoardsAssertMethod('POST');
        $payload = taskBoardsReadJsonPayload();
        $boardId = trim((string) ($payload['board_id'] ?? ''));
        $cardId = trim((string) ($payload['card_id'] ?? ''));
        $existingCard = $repository->findCard($accessToken, $boardId, $cardId);

        if ($boardId === '' || $cardId === '') {
            throw new InvalidArgumentException('No encontramos la tarea que intentas eliminar.');
        }

        if (!is_array($existingCard)) {
            throw new RuntimeException('La tarea ya no existe.');
        }

        $repository->addActivity($accessToken, [
            'board_id' => $boardId,
            'card_id' => $cardId,
            'event_type' => 'card_deleted',
            'description' => 'Elimino la tarea "' . trim((string) ($existingCard['title'] ?? 'Tarea')) . '".',
            'actor_user_id' => $userId,
            'actor_email' => $userEmail,
            'payload' => new stdClass(),
        ]);
        $repository->deleteCard($accessToken, $boardId, $cardId);

        taskBoardsSendJson([
            'success' => true,
            'message' => 'Tarea eliminada correctamente.',
            'data' => taskBoardsBuildBoardPayload($repository, $accessToken, $activeProjectId, $boardId),
        ]);
    }

    if ($action === 'add-comment') {
        taskBoardsAssertMethod('POST');
        $payload = taskBoardsReadJsonPayload();
        $boardId = trim((string) ($payload['board_id'] ?? ''));
        $cardId = trim((string) ($payload['card_id'] ?? ''));
        $bodyMarkdown = trim((string) ($payload['body_markdown'] ?? ''));
        $card = $repository->findCard($accessToken, $boardId, $cardId);

        if ($boardId === '' || $cardId === '') {
            throw new InvalidArgumentException('No encontramos la tarea para comentar.');
        }

        if ($bodyMarkdown === '') {
            throw new InvalidArgumentException('Escribe un comentario antes de enviarlo.');
        }

        if (!is_array($card)) {
            throw new RuntimeException('No encontramos la tarea para guardar el comentario.');
        }

        $comment = $repository->saveComment($accessToken, [
            'board_id' => $boardId,
            'card_id' => $cardId,
            'body_markdown' => $bodyMarkdown,
            'author_user_id' => $userId,
            'author_email' => $userEmail,
        ]);
        $repository->addActivity($accessToken, [
            'board_id' => $boardId,
            'card_id' => $cardId,
            'event_type' => 'comment_added',
            'description' => 'Comento la tarea "' . trim((string) ($card['title'] ?? 'Tarea')) . '".',
            'actor_user_id' => $userId,
            'actor_email' => $userEmail,
            'payload' => [
                'comment_id' => (string) ($comment['id'] ?? ''),
            ],
        ]);

        taskBoardsSendJson([
            'success' => true,
            'message' => 'Comentario guardado.',
            'data' => taskBoardsBuildBoardPayload($repository, $accessToken, $activeProjectId, $boardId),
        ]);
    }

    throw new InvalidArgumentException('Accion no soportada por Tableros de tareas.');
} catch (InvalidArgumentException $exception) {
    taskBoardsSendJson([
        'success' => false,
        'message' => $exception->getMessage(),
    ], 422);
} catch (Throwable $exception) {
    taskBoardsSendJson([
        'success' => false,
        'message' => normalizeTaskBoardException($exception),
    ], 400);
}

function taskBoardsBuildBootstrapPayload(
    TaskBoardRepository $repository,
    string $accessToken,
    string $projectId,
    string $requestedBoardId,
    array $project,
    string $currentUserId,
    string $currentUserEmail
): array {
    $boards = taskBoardsNormalizeBoards($repository->listBoards($accessToken, $projectId));
    $members = taskBoardsNormalizeMembers(
        $repository->listProjectMembers($accessToken, $projectId),
        $currentUserId,
        $currentUserEmail
    );
    $resolvedBoardId = $requestedBoardId;

    if ($resolvedBoardId === '' || !taskBoardsBoardExists($boards, $resolvedBoardId)) {
        $resolvedBoardId = (string) ($boards[0]['id'] ?? '');
    }

    return [
        'project' => [
            'id' => (string) ($project['id'] ?? $projectId),
            'name' => (string) ($project['name'] ?? 'Proyecto'),
            'logo_url' => (string) ($project['logo_url'] ?? ''),
        ],
        'viewer' => [
            'user_id' => $currentUserId,
            'email' => $currentUserEmail,
        ],
        'boards' => $boards,
        'members' => $members,
        'active_board' => $resolvedBoardId !== ''
            ? taskBoardsBuildBoardPayload($repository, $accessToken, $projectId, $resolvedBoardId)
            : null,
        'realtime' => [
            'supabase_url' => supabaseProjectUrl(),
            'supabase_key' => supabaseApiKey(),
            'access_token' => $accessToken,
        ],
    ];
}

function taskBoardsBuildBoardPayload(
    TaskBoardRepository $repository,
    string $accessToken,
    string $projectId,
    string $boardId
): array {
    return taskBoardsNormalizeBoardState($repository->getBoardState($accessToken, $projectId, $boardId));
}

function taskBoardsResolveCardPlacement(array $boardPayload, string $columnId, string $swimlaneId, ?array $existingCard): array
{
    $columns = is_array($boardPayload['columns'] ?? null) ? $boardPayload['columns'] : [];
    $swimlanes = is_array($boardPayload['swimlanes'] ?? null) ? $boardPayload['swimlanes'] : [];
    $cards = is_array($boardPayload['cards'] ?? null) ? $boardPayload['cards'] : [];
    $resolvedColumnId = $columnId !== '' ? $columnId : trim((string) ($existingCard['column_id'] ?? (string) ($columns[0]['id'] ?? '')));
    $resolvedSwimlaneId = $swimlaneId !== '' ? $swimlaneId : trim((string) ($existingCard['swimlane_id'] ?? (string) ($swimlanes[0]['id'] ?? '')));
    $sortOrder = is_array($existingCard)
        ? (float) ($existingCard['sort_order'] ?? 0)
        : taskBoardsNextCardSortOrder($cards, $resolvedColumnId, $resolvedSwimlaneId);

    if ($resolvedColumnId === '' || $resolvedSwimlaneId === '') {
        throw new RuntimeException('El tablero necesita al menos una columna y un carril para crear tareas.');
    }

    return [$resolvedColumnId, $resolvedSwimlaneId, $sortOrder];
}

function taskBoardsNextCardSortOrder(array $cards, string $columnId, string $swimlaneId): float
{
    $minimum = null;

    foreach ($cards as $card) {
        if (!is_array($card)) {
            continue;
        }

        if (trim((string) ($card['column_id'] ?? '')) !== $columnId || trim((string) ($card['swimlane_id'] ?? '')) !== $swimlaneId) {
            continue;
        }

        $current = (float) ($card['sort_order'] ?? 0);
        $minimum = $minimum === null ? $current : min($minimum, $current);
    }

    return $minimum === null ? 0 : $minimum - 1024;
}

function taskBoardsResolvePriority(string $priority): string
{
    $normalized = trim(strtolower($priority));

    return in_array($normalized, ['low', 'medium', 'high', 'urgent'], true)
        ? $normalized
        : 'medium';
}

function taskBoardsNormalizeDateValue(mixed $value): ?string
{
    $normalized = trim((string) ($value ?? ''));
    return $normalized !== '' ? $normalized : null;
}

function taskBoardsNormalizeStringArray(mixed $items): array
{
    if (!is_array($items)) {
        return [];
    }

    return array_values(array_filter(array_map(static fn (mixed $item): string => trim((string) $item), $items)));
}

function taskBoardsNormalizeChecklist(mixed $items): array
{
    if (!is_array($items)) {
        return [];
    }

    $normalized = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $title = trim((string) ($item['title'] ?? ''));

        if ($title === '') {
            continue;
        }

        $normalized[] = [
            'id' => trim((string) ($item['id'] ?? '')) ?: bin2hex(random_bytes(8)),
            'title' => $title,
            'is_done' => filter_var($item['is_done'] ?? false, FILTER_VALIDATE_BOOL),
        ];
    }

    return array_values($normalized);
}

function taskBoardsNormalizeStructurePayload(mixed $items, string $mode): array
{
    if (!is_array($items)) {
        return [];
    }

    $normalized = [];

    foreach ($items as $index => $item) {
        if (!is_array($item)) {
            continue;
        }

        $title = trim((string) ($item['title'] ?? ''));

        if ($title === '') {
            continue;
        }

        $color = trim((string) ($item['color'] ?? ($mode === 'columns'
            ? '#1A73E8'
            : ($mode === 'swimlanes' ? '#EEF3FB' : '#2F7CEF'))));

        $normalized[] = [
            'id' => trim((string) ($item['id'] ?? '')),
            'title' => $title,
            'color' => $color !== '' ? $color : ($mode === 'columns'
                ? '#1A73E8'
                : ($mode === 'swimlanes' ? '#EEF3FB' : '#2F7CEF')),
            'wip_limit' => $mode === 'columns'
                ? trim((string) ($item['wip_limit'] ?? ''))
                : '',
            'sort_order' => $index * 10 + 10,
        ];
    }

    return array_values($normalized);
}

function taskBoardsAssertMethod(string $method): void
{
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== strtoupper($method)) {
        throw new InvalidArgumentException('Metodo no permitido.');
    }
}

function taskBoardsReadJsonPayload(): array
{
    $rawInput = file_get_contents('php://input');

    if ($rawInput === false || trim($rawInput) === '') {
        return $_POST;
    }

    $decoded = json_decode($rawInput, true);

    return is_array($decoded) ? $decoded : [];
}

function taskBoardsSendJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
