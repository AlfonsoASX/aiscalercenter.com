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
        throw new RuntimeException('No encontramos la sesion segura de Tableros.');
    }

    if ($activeProjectId === '') {
        throw new RuntimeException('Selecciona un proyecto antes de usar Tableros.');
    }

    $project = $repository->findProject($accessToken, $activeProjectId);

    if (!is_array($project)) {
        throw new RuntimeException('No encontramos el proyecto activo de los tableros.');
    }

    taskBoardsRunProjectAutomation($repository, $accessToken, $activeProjectId);

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
        $labels = taskBoardsNormalizeStructurePayload($payload['labels'] ?? [], 'labels');

        if ($boardId === '') {
            throw new InvalidArgumentException('No encontramos el tablero que intentas configurar.');
        }

        if ($columns === []) {
            throw new InvalidArgumentException('El tablero necesita al menos una columna.');
        }

        $repository->saveStructure($accessToken, $boardId, $columns, $labels);
        $repository->addActivity($accessToken, [
            'board_id' => $boardId,
            'event_type' => 'board_structure_updated',
            'description' => 'Actualizo columnas o etiquetas del tablero.',
            'actor_user_id' => $userId,
            'actor_email' => $userEmail,
            'payload' => [
                'columns' => count($columns),
                'labels' => count($labels),
            ],
        ]);

        taskBoardsSendJson([
            'success' => true,
            'message' => 'Estructura del tablero actualizada.',
            'data' => taskBoardsBuildBoardPayload($repository, $accessToken, $activeProjectId, $boardId, $userId),
        ]);
    }

    if ($action === 'save-card') {
        taskBoardsAssertMethod('POST');
        $payload = taskBoardsReadJsonPayload();
        $boardId = trim((string) ($payload['board_id'] ?? ''));
        $cardId = trim((string) ($payload['id'] ?? ''));
        $title = trim((string) ($payload['title'] ?? ''));

        if ($boardId === '') {
            throw new InvalidArgumentException('No encontramos el tablero de la ficha.');
        }

        if ($title === '') {
            throw new InvalidArgumentException('La ficha necesita un titulo.');
        }

        $boardPayload = taskBoardsBuildBoardPayload($repository, $accessToken, $activeProjectId, $boardId, $userId);
        $existingCard = $cardId !== '' ? $repository->findCard($accessToken, $boardId, $cardId) : null;
        [$defaultColumnId, $defaultSortOrder] = taskBoardsResolveCardPlacement(
            $boardPayload,
            trim((string) ($payload['column_id'] ?? '')),
            $existingCard
        );

        $savedCard = $repository->saveCard($accessToken, [
            'id' => $cardId,
            'board_id' => $boardId,
            'column_id' => $defaultColumnId,
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

        taskBoardsHandleColumnEntry(
            $repository,
            $accessToken,
            $activeProjectId,
            $project,
            $boardPayload,
            $savedCard,
            $defaultColumnId,
            $userId,
            $userEmail,
            $cardId === '' || trim((string) ($existingCard['column_id'] ?? '')) !== $defaultColumnId
        );

        $repository->addActivity($accessToken, [
            'board_id' => $boardId,
            'card_id' => (string) ($savedCard['id'] ?? ''),
            'event_type' => $cardId === '' ? 'card_created' : 'card_updated',
            'description' => $cardId === '' ? 'Creo la ficha "' . $title . '".' : 'Actualizo la ficha "' . $title . '".',
            'actor_user_id' => $userId,
            'actor_email' => $userEmail,
            'payload' => [
                'column_id' => $defaultColumnId,
            ],
        ]);

        taskBoardsSendJson([
            'success' => true,
            'message' => $cardId === '' ? 'Ficha creada correctamente.' : 'Ficha actualizada correctamente.',
            'saved_card_id' => (string) ($savedCard['id'] ?? ''),
            'data' => taskBoardsBuildBoardPayload($repository, $accessToken, $activeProjectId, $boardId, $userId),
        ]);
    }

    if ($action === 'move-card') {
        taskBoardsAssertMethod('POST');
        $payload = taskBoardsReadJsonPayload();
        $boardId = trim((string) ($payload['board_id'] ?? ''));
        $cardId = trim((string) ($payload['card_id'] ?? ''));
        $columnId = trim((string) ($payload['column_id'] ?? ''));
        $sortOrder = is_numeric($payload['sort_order'] ?? null) ? (float) $payload['sort_order'] : 0;
        $existingCard = $repository->findCard($accessToken, $boardId, $cardId);

        if ($boardId === '' || $cardId === '' || $columnId === '') {
            throw new InvalidArgumentException('No encontramos los datos necesarios para mover la ficha.');
        }

        if (!is_array($existingCard)) {
            throw new RuntimeException('No encontramos la ficha que intentas mover.');
        }

        $movedCard = $repository->moveCard($accessToken, $boardId, $cardId, $columnId, $sortOrder);
        taskBoardsHandleColumnEntry(
            $repository,
            $accessToken,
            $activeProjectId,
            $project,
            taskBoardsBuildBoardPayload($repository, $accessToken, $activeProjectId, $boardId, $userId),
            $movedCard,
            $columnId,
            $userId,
            $userEmail,
            trim((string) ($existingCard['column_id'] ?? '')) !== $columnId
        );
        $repository->addActivity($accessToken, [
            'board_id' => $boardId,
            'card_id' => $cardId,
            'event_type' => 'card_moved',
            'description' => 'Movio la ficha "' . trim((string) ($existingCard['title'] ?? 'Ficha')) . '" dentro del tablero.',
            'actor_user_id' => $userId,
            'actor_email' => $userEmail,
            'payload' => [
                'from_column_id' => (string) ($existingCard['column_id'] ?? ''),
                'to_column_id' => $columnId,
            ],
        ]);

        taskBoardsSendJson([
            'success' => true,
            'message' => 'Ficha movida correctamente.',
            'data' => [
                'card' => $movedCard,
            ],
        ]);
    }

    if ($action === 'save-column') {
        taskBoardsAssertMethod('POST');
        $payload = taskBoardsReadJsonPayload();
        $boardId = trim((string) ($payload['board_id'] ?? ''));
        $columnId = trim((string) ($payload['column_id'] ?? ''));
        $title = trim((string) ($payload['title'] ?? ''));
        $boardPayload = taskBoardsBuildBoardPayload($repository, $accessToken, $activeProjectId, $boardId, $userId);

        if ($boardId === '' || $columnId === '') {
            throw new InvalidArgumentException('No encontramos la columna que intentas editar.');
        }

        if ($title === '') {
            throw new InvalidArgumentException('La columna necesita un nombre.');
        }

        $column = $repository->updateColumn($accessToken, $boardId, $columnId, [
            'title' => $title,
            'accent_color' => trim((string) ($payload['accent_color'] ?? '#1A73E8')) ?: '#1A73E8',
            'responsible_member_id' => trim((string) ($payload['responsible_member_id'] ?? '')) ?: null,
            'wip_limit' => ($payload['wip_limit'] ?? '') === '' ? null : (int) ($payload['wip_limit'] ?? 0),
            'is_archived' => filter_var($payload['is_archived'] ?? false, FILTER_VALIDATE_BOOL),
        ]);

        taskBoardsRepositionColumn($repository, $accessToken, $boardId, $columnId, (int) ($payload['position'] ?? 0), $boardPayload);

        $repository->addActivity($accessToken, [
            'board_id' => $boardId,
            'event_type' => 'column_updated',
            'description' => 'Actualizo la columna "' . trim((string) ($column['title'] ?? $title)) . '".',
            'actor_user_id' => $userId,
            'actor_email' => $userEmail,
            'payload' => [
                'column_id' => $columnId,
            ],
        ]);

        taskBoardsSendJson([
            'success' => true,
            'message' => 'Columna actualizada correctamente.',
            'data' => taskBoardsBuildBoardPayload($repository, $accessToken, $activeProjectId, $boardId, $userId),
        ]);
    }

    if ($action === 'duplicate-column') {
        taskBoardsAssertMethod('POST');
        $payload = taskBoardsReadJsonPayload();
        $boardId = trim((string) ($payload['board_id'] ?? ''));
        $columnId = trim((string) ($payload['column_id'] ?? ''));
        $sourceColumn = $repository->findColumn($accessToken, $boardId, $columnId);

        if (!is_array($sourceColumn)) {
            throw new InvalidArgumentException('No encontramos la columna que intentas copiar.');
        }

        $columns = $repository->listColumns($accessToken, $boardId);
        $lastSort = $columns === [] ? 0 : max(array_map(static fn (array $column): int => (int) ($column['sort_order'] ?? 0), $columns));
        $newColumn = $repository->createColumn($accessToken, [
            'board_id' => $boardId,
            'title' => trim((string) ($sourceColumn['title'] ?? 'Columna')) . ' (copia)',
            'accent_color' => trim((string) ($sourceColumn['accent_color'] ?? '#1A73E8')) ?: '#1A73E8',
            'responsible_member_id' => trim((string) ($sourceColumn['responsible_member_id'] ?? '')) ?: null,
            'wip_limit' => ($sourceColumn['wip_limit'] ?? null) === null ? null : (int) ($sourceColumn['wip_limit'] ?? 0),
            'sort_order' => $lastSort + 10,
            'is_archived' => false,
        ]);

        foreach ($repository->listCardsForColumn($accessToken, $boardId, $columnId) as $index => $card) {
            if (!is_array($card)) {
                continue;
            }

            $repository->saveCard($accessToken, [
                'board_id' => $boardId,
                'column_id' => (string) ($newColumn['id'] ?? ''),
                'title' => trim((string) ($card['title'] ?? 'Ficha')),
                'description_markdown' => trim((string) ($card['description_markdown'] ?? '')),
                'priority' => taskBoardsResolvePriority((string) ($card['priority'] ?? 'medium')),
                'start_date' => taskBoardsNormalizeDateValue($card['start_date'] ?? null),
                'due_date' => taskBoardsNormalizeDateValue($card['due_date'] ?? null),
                'assigned_member_ids' => taskBoardsNormalizeStringArray($card['assigned_member_ids'] ?? []),
                'label_ids' => taskBoardsNormalizeStringArray($card['label_ids'] ?? []),
                'checklist' => taskBoardsNormalizeChecklist($card['checklist'] ?? []),
                'metadata' => is_array($card['metadata'] ?? null) ? $card['metadata'] : [],
                'sort_order' => $index * 1024,
                'created_by' => $userId,
                'created_by_email' => $userEmail,
            ]);
        }

        taskBoardsSendJson([
            'success' => true,
            'message' => 'Lista copiada correctamente.',
            'data' => taskBoardsBuildBoardPayload($repository, $accessToken, $activeProjectId, $boardId, $userId),
        ]);
    }

    if ($action === 'move-column-cards') {
        taskBoardsAssertMethod('POST');
        $payload = taskBoardsReadJsonPayload();
        $boardId = trim((string) ($payload['board_id'] ?? ''));
        $fromColumnId = trim((string) ($payload['from_column_id'] ?? ''));
        $toColumnId = trim((string) ($payload['to_column_id'] ?? ''));

        if ($boardId === '' || $fromColumnId === '' || $toColumnId === '' || $fromColumnId === $toColumnId) {
            throw new InvalidArgumentException('Selecciona una columna destino valida.');
        }

        $cards = $repository->listCardsForColumn($accessToken, $boardId, $fromColumnId);
        $boardPayload = taskBoardsBuildBoardPayload($repository, $accessToken, $activeProjectId, $boardId, $userId);
        $baseSortOrder = taskBoardsAppendCardSortOrder($boardPayload['cards'] ?? [], $toColumnId);

        foreach ($cards as $index => $card) {
            if (!is_array($card) || trim((string) ($card['id'] ?? '')) === '') {
                continue;
            }

            $repository->moveCard(
                $accessToken,
                $boardId,
                (string) $card['id'],
                $toColumnId,
                $baseSortOrder + (($index + 1) * 1024)
            );
        }

        taskBoardsSendJson([
            'success' => true,
            'message' => 'Las fichas se movieron correctamente.',
            'data' => taskBoardsBuildBoardPayload($repository, $accessToken, $activeProjectId, $boardId, $userId),
        ]);
    }

    if ($action === 'sort-column') {
        taskBoardsAssertMethod('POST');
        $payload = taskBoardsReadJsonPayload();
        $boardId = trim((string) ($payload['board_id'] ?? ''));
        $columnId = trim((string) ($payload['column_id'] ?? ''));
        $sortKey = trim((string) ($payload['sort_key'] ?? 'due_date'));
        $direction = trim((string) ($payload['direction'] ?? 'asc'));

        taskBoardsSortColumnCards($repository, $accessToken, $boardId, $columnId, $sortKey, $direction);

        taskBoardsSendJson([
            'success' => true,
            'message' => 'La lista se ordenó correctamente.',
            'data' => taskBoardsBuildBoardPayload($repository, $accessToken, $activeProjectId, $boardId, $userId),
        ]);
    }

    if ($action === 'toggle-column-follow') {
        taskBoardsAssertMethod('POST');
        $payload = taskBoardsReadJsonPayload();
        $boardId = trim((string) ($payload['board_id'] ?? ''));
        $columnId = trim((string) ($payload['column_id'] ?? ''));
        $shouldFollow = filter_var($payload['follow'] ?? false, FILTER_VALIDATE_BOOL);

        if ($shouldFollow) {
            $repository->followColumn($accessToken, [
                'board_id' => $boardId,
                'column_id' => $columnId,
                'user_id' => $userId,
                'user_email' => $userEmail,
            ]);
        } else {
            $repository->unfollowColumn($accessToken, $boardId, $columnId, $userId);
        }

        taskBoardsSendJson([
            'success' => true,
            'message' => $shouldFollow ? 'Ahora sigues esta lista.' : 'Dejaste de seguir esta lista.',
            'data' => taskBoardsBuildBoardPayload($repository, $accessToken, $activeProjectId, $boardId, $userId),
        ]);
    }

    if ($action === 'save-column-rule') {
        taskBoardsAssertMethod('POST');
        $payload = taskBoardsReadJsonPayload();
        $boardId = trim((string) ($payload['board_id'] ?? ''));
        $columnId = trim((string) ($payload['column_id'] ?? ''));

        if ($boardId === '' || $columnId === '') {
            throw new InvalidArgumentException('No encontramos la regla que intentas guardar.');
        }

        $repository->saveColumnRule($accessToken, [
            'id' => trim((string) ($payload['id'] ?? '')),
            'board_id' => $boardId,
            'column_id' => $columnId,
            'title' => trim((string) ($payload['title'] ?? '')) ?: 'Regla de columna',
            'trigger_type' => taskBoardsNormalizeRuleTrigger((string) ($payload['trigger_type'] ?? 'card_added')),
            'action_type' => taskBoardsNormalizeRuleAction((string) ($payload['action_type'] ?? 'sort_list')),
            'config' => is_array($payload['config'] ?? null) ? $payload['config'] : [],
            'is_active' => filter_var($payload['is_active'] ?? true, FILTER_VALIDATE_BOOL),
            'created_by' => $userId,
            'created_by_email' => $userEmail,
        ]);

        taskBoardsSendJson([
            'success' => true,
            'message' => 'Regla guardada correctamente.',
            'data' => taskBoardsBuildBoardPayload($repository, $accessToken, $activeProjectId, $boardId, $userId),
        ]);
    }

    if ($action === 'delete-column-rule') {
        taskBoardsAssertMethod('POST');
        $payload = taskBoardsReadJsonPayload();
        $boardId = trim((string) ($payload['board_id'] ?? ''));
        $ruleId = trim((string) ($payload['rule_id'] ?? ''));

        $repository->deleteColumnRule($accessToken, $boardId, $ruleId);

        taskBoardsSendJson([
            'success' => true,
            'message' => 'Regla eliminada correctamente.',
            'data' => taskBoardsBuildBoardPayload($repository, $accessToken, $activeProjectId, $boardId, $userId),
        ]);
    }

    if ($action === 'archive-column') {
        taskBoardsAssertMethod('POST');
        $payload = taskBoardsReadJsonPayload();
        $boardId = trim((string) ($payload['board_id'] ?? ''));
        $columnId = trim((string) ($payload['column_id'] ?? ''));
        $archived = filter_var($payload['archived'] ?? true, FILTER_VALIDATE_BOOL);

        $repository->updateColumn($accessToken, $boardId, $columnId, [
            'is_archived' => $archived,
        ]);

        taskBoardsSendJson([
            'success' => true,
            'message' => $archived ? 'Lista archivada correctamente.' : 'Lista restaurada correctamente.',
            'data' => taskBoardsBuildBoardPayload($repository, $accessToken, $activeProjectId, $boardId, $userId),
        ]);
    }

    if ($action === 'archive-column-cards') {
        taskBoardsAssertMethod('POST');
        $payload = taskBoardsReadJsonPayload();
        $boardId = trim((string) ($payload['board_id'] ?? ''));
        $columnId = trim((string) ($payload['column_id'] ?? ''));
        $archived = filter_var($payload['archived'] ?? true, FILTER_VALIDATE_BOOL);

        $repository->archiveColumnCards($accessToken, $boardId, $columnId, $archived);

        taskBoardsSendJson([
            'success' => true,
            'message' => $archived ? 'Las fichas de la lista se archivaron correctamente.' : 'Las fichas de la lista se restauraron correctamente.',
            'data' => taskBoardsBuildBoardPayload($repository, $accessToken, $activeProjectId, $boardId, $userId),
        ]);
    }

    if ($action === 'notifications') {
        taskBoardsAssertMethod('POST');

        taskBoardsSendJson([
            'success' => true,
            'data' => [
                'notifications' => taskBoardsNormalizeNotifications(
                    $repository->listNotifications($accessToken, $userId, $activeProjectId)
                ),
            ],
        ]);
    }

    if ($action === 'notifications-read') {
        taskBoardsAssertMethod('POST');
        $payload = taskBoardsReadJsonPayload();
        $ids = taskBoardsNormalizeStringArray($payload['ids'] ?? []);
        $repository->markNotificationsRead($accessToken, $userId, $ids);

        taskBoardsSendJson([
            'success' => true,
            'data' => [
                'notifications' => taskBoardsNormalizeNotifications(
                    $repository->listNotifications($accessToken, $userId, $activeProjectId)
                ),
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
            throw new InvalidArgumentException('No encontramos la ficha que intentas eliminar.');
        }

        if (!is_array($existingCard)) {
            throw new RuntimeException('La ficha ya no existe.');
        }

        $repository->addActivity($accessToken, [
            'board_id' => $boardId,
            'card_id' => $cardId,
            'event_type' => 'card_deleted',
            'description' => 'Elimino la ficha "' . trim((string) ($existingCard['title'] ?? 'Ficha')) . '".',
            'actor_user_id' => $userId,
            'actor_email' => $userEmail,
            'payload' => new stdClass(),
        ]);
        $repository->deleteCard($accessToken, $boardId, $cardId);

        taskBoardsSendJson([
            'success' => true,
            'message' => 'Ficha eliminada correctamente.',
            'data' => taskBoardsBuildBoardPayload($repository, $accessToken, $activeProjectId, $boardId, $userId),
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
            throw new InvalidArgumentException('No encontramos la ficha para comentar.');
        }

        if ($bodyMarkdown === '') {
            throw new InvalidArgumentException('Escribe un comentario antes de enviarlo.');
        }

        if (!is_array($card)) {
            throw new RuntimeException('No encontramos la ficha para guardar el comentario.');
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
            'description' => 'Comento la ficha "' . trim((string) ($card['title'] ?? 'Ficha')) . '".',
            'actor_user_id' => $userId,
            'actor_email' => $userEmail,
            'payload' => [
                'comment_id' => (string) ($comment['id'] ?? ''),
            ],
        ]);

        taskBoardsSendJson([
            'success' => true,
            'message' => 'Comentario guardado.',
            'data' => taskBoardsBuildBoardPayload($repository, $accessToken, $activeProjectId, $boardId, $userId),
        ]);
    }

    throw new InvalidArgumentException('Accion no soportada por Tableros.');
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
            ? taskBoardsBuildBoardPayload($repository, $accessToken, $projectId, $resolvedBoardId, $currentUserId)
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
    string $boardId,
    string $currentUserId = ''
): array {
    return taskBoardsNormalizeBoardState($repository->getBoardState($accessToken, $projectId, $boardId, $currentUserId));
}

function taskBoardsResolveCardPlacement(array $boardPayload, string $columnId, ?array $existingCard): array
{
    $columns = is_array($boardPayload['columns'] ?? null) ? $boardPayload['columns'] : [];
    $cards = is_array($boardPayload['cards'] ?? null) ? $boardPayload['cards'] : [];
    $resolvedColumnId = $columnId !== '' ? $columnId : trim((string) ($existingCard['column_id'] ?? (string) ($columns[0]['id'] ?? '')));
    $sortOrder = is_array($existingCard)
        ? (float) ($existingCard['sort_order'] ?? 0)
        : taskBoardsNextCardSortOrder($cards, $resolvedColumnId);

    if ($resolvedColumnId === '') {
        throw new RuntimeException('El tablero necesita al menos una columna para crear fichas.');
    }

    return [$resolvedColumnId, $sortOrder];
}

function taskBoardsNextCardSortOrder(array $cards, string $columnId): float
{
    $minimum = null;

    foreach ($cards as $card) {
        if (!is_array($card)) {
            continue;
        }

        if (trim((string) ($card['column_id'] ?? '')) !== $columnId) {
            continue;
        }

        $current = (float) ($card['sort_order'] ?? 0);
        $minimum = $minimum === null ? $current : min($minimum, $current);
    }

    return $minimum === null ? 0 : $minimum - 1024;
}

function taskBoardsAppendCardSortOrder(array $cards, string $columnId): float
{
    $maximum = null;

    foreach ($cards as $card) {
        if (!is_array($card)) {
            continue;
        }

        if (trim((string) ($card['column_id'] ?? '')) !== $columnId) {
            continue;
        }

        $current = (float) ($card['sort_order'] ?? 0);
        $maximum = $maximum === null ? $current : max($maximum, $current);
    }

    return $maximum === null ? 0 : $maximum;
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

        if ($mode !== 'labels' && $title === '') {
            continue;
        }

        $color = trim((string) ($item['color'] ?? ($mode === 'columns' ? '#1A73E8' : '#2F7CEF')));

        $normalized[] = [
            'id' => trim((string) ($item['id'] ?? '')),
            'title' => $title,
            'color' => $color !== '' ? $color : ($mode === 'columns' ? '#1A73E8' : '#2F7CEF'),
            'responsible_member_id' => $mode === 'columns'
                ? trim((string) ($item['responsible_member_id'] ?? ''))
                : '',
            'wip_limit' => $mode === 'columns'
                ? trim((string) ($item['wip_limit'] ?? ''))
                : '',
            'is_archived' => $mode === 'columns'
                ? filter_var($item['is_archived'] ?? false, FILTER_VALIDATE_BOOL)
                : false,
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

function taskBoardsNormalizeRuleTrigger(string $trigger): string
{
    $normalized = trim(strtolower($trigger));

    return in_array($normalized, ['card_added', 'daily', 'weekly_monday'], true)
        ? $normalized
        : 'card_added';
}

function taskBoardsNormalizeRuleAction(string $action): string
{
    $normalized = trim(strtolower($action));

    return in_array($normalized, ['sort_list', 'assign_responsible', 'create_notification'], true)
        ? $normalized
        : 'sort_list';
}

function taskBoardsNormalizeNotifications(array $notifications): array
{
    return array_values(array_filter(array_map(static function ($item): ?array {
        if (!is_array($item)) {
            return null;
        }

        return [
            'id' => (string) ($item['id'] ?? ''),
            'title' => (string) ($item['title'] ?? ''),
            'body' => (string) ($item['body'] ?? ''),
            'is_read' => filter_var($item['is_read'] ?? false, FILTER_VALIDATE_BOOL),
            'created_at' => (string) ($item['created_at'] ?? ''),
            'destination' => is_array($item['destination'] ?? null) ? $item['destination'] : [],
            'payload' => is_array($item['payload'] ?? null) ? $item['payload'] : [],
        ];
    }, $notifications)));
}

function taskBoardsRepositionColumn(
    TaskBoardRepository $repository,
    string $accessToken,
    string $boardId,
    string $columnId,
    int $position,
    array $boardPayload
): void {
    $columns = array_values(array_filter(is_array($boardPayload['columns'] ?? null) ? $boardPayload['columns'] : [], static fn ($column): bool => is_array($column)));
    $orderedIds = array_values(array_map(static fn (array $column): string => trim((string) ($column['id'] ?? '')), $columns));
    $orderedIds = array_values(array_filter($orderedIds, static fn (string $value): bool => $value !== ''));
    $currentIndex = array_search($columnId, $orderedIds, true);

    if ($currentIndex === false) {
        return;
    }

    array_splice($orderedIds, (int) $currentIndex, 1);
    $targetIndex = max(0, min($position, count($orderedIds)));
    array_splice($orderedIds, $targetIndex, 0, [$columnId]);
    $repository->updateColumnSortOrders($accessToken, $boardId, $orderedIds);
}

function taskBoardsSortColumnCards(
    TaskBoardRepository $repository,
    string $accessToken,
    string $boardId,
    string $columnId,
    string $sortKey,
    string $direction
): void {
    $cards = $repository->listCardsForColumn($accessToken, $boardId, $columnId);
    $normalizedDirection = trim(strtolower($direction)) === 'desc' ? 'desc' : 'asc';
    $normalizedSortKey = in_array($sortKey, ['due_date', 'created_at', 'title', 'priority'], true) ? $sortKey : 'due_date';
    $priorityOrder = ['urgent' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];

    usort($cards, static function (array $left, array $right) use ($normalizedSortKey, $normalizedDirection, $priorityOrder): int {
        $compare = 0;

        if ($normalizedSortKey === 'priority') {
            $compare = ($priorityOrder[trim((string) ($left['priority'] ?? 'medium'))] ?? 9)
                <=> ($priorityOrder[trim((string) ($right['priority'] ?? 'medium'))] ?? 9);
        } else {
            $leftValue = trim((string) ($left[$normalizedSortKey] ?? ''));
            $rightValue = trim((string) ($right[$normalizedSortKey] ?? ''));

            if ($leftValue === '' && $rightValue !== '') {
                $compare = 1;
            } elseif ($leftValue !== '' && $rightValue === '') {
                $compare = -1;
            } else {
                $compare = $leftValue <=> $rightValue;
            }
        }

        if ($compare === 0) {
            $compare = trim((string) ($left['id'] ?? '')) <=> trim((string) ($right['id'] ?? ''));
        }

        return $normalizedDirection === 'desc' ? ($compare * -1) : $compare;
    });

    foreach ($cards as $index => $card) {
        $cardId = trim((string) ($card['id'] ?? ''));

        if ($cardId === '') {
            continue;
        }

        $repository->updateCard($accessToken, $boardId, $cardId, [
            'sort_order' => ($index + 1) * 1024,
        ]);
    }
}

function taskBoardsRunProjectAutomation(
    TaskBoardRepository $repository,
    string $accessToken,
    string $projectId
): void {
    $boards = taskBoardsNormalizeBoards($repository->listBoards($accessToken, $projectId));
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $weekday = (int) $now->format('N');

    foreach ($boards as $board) {
        $boardId = trim((string) ($board['id'] ?? ''));

        if ($boardId === '') {
            continue;
        }

        foreach ($repository->listColumnRules($accessToken, $boardId) as $rule) {
            if (!is_array($rule) || !filter_var($rule['is_active'] ?? true, FILTER_VALIDATE_BOOL)) {
                continue;
            }

            $triggerType = taskBoardsNormalizeRuleTrigger((string) ($rule['trigger_type'] ?? 'card_added'));

            if ($triggerType === 'card_added') {
                continue;
            }

            $lastRunAt = trim((string) ($rule['last_run_at'] ?? ''));
            $shouldRun = false;

            if ($triggerType === 'daily') {
                $shouldRun = $lastRunAt === '' || substr($lastRunAt, 0, 10) !== $now->format('Y-m-d');
            } elseif ($triggerType === 'weekly_monday') {
                $shouldRun = $weekday === 1 && ($lastRunAt === '' || substr($lastRunAt, 0, 10) !== $now->format('Y-m-d'));
            }

            if (!$shouldRun) {
                continue;
            }

            taskBoardsExecuteRule($repository, $accessToken, $projectId, $boardId, $rule, null);
        }
    }
}

function taskBoardsHandleColumnEntry(
    TaskBoardRepository $repository,
    string $accessToken,
    string $projectId,
    array $project,
    array $boardPayload,
    array $card,
    string $columnId,
    string $actorUserId,
    string $actorEmail,
    bool $shouldNotify
): void {
    if (!$shouldNotify) {
        return;
    }

    $columns = is_array($boardPayload['columns'] ?? null) ? $boardPayload['columns'] : [];
    $column = null;

    foreach ($columns as $candidate) {
        if (is_array($candidate) && trim((string) ($candidate['id'] ?? '')) === $columnId) {
            $column = $candidate;
            break;
        }
    }

    if (!is_array($column)) {
        return;
    }

    $projectMembers = $repository->listProjectMembers($accessToken, $projectId);
    $notifications = taskBoardsBuildColumnNotifications(
        $repository,
        $accessToken,
        $projectId,
        $projectMembers,
        $boardPayload,
        $column,
        $card,
        $actorUserId,
        $actorEmail
    );

    if ($notifications !== []) {
        $repository->createNotifications($accessToken, $notifications);
    }

    foreach ($repository->listColumnRules($accessToken, trim((string) ($boardPayload['board']['id'] ?? ''))) as $rule) {
        if (!is_array($rule) || trim((string) ($rule['column_id'] ?? '')) !== $columnId) {
            continue;
        }

        if (taskBoardsNormalizeRuleTrigger((string) ($rule['trigger_type'] ?? 'card_added')) !== 'card_added') {
            continue;
        }

        taskBoardsExecuteRule($repository, $accessToken, $projectId, trim((string) ($boardPayload['board']['id'] ?? '')), $rule, $card);
    }
}

function taskBoardsBuildColumnNotifications(
    TaskBoardRepository $repository,
    string $accessToken,
    string $projectId,
    array $projectMembers,
    array $boardPayload,
    array $column,
    array $card,
    string $actorUserId,
    string $actorEmail
): array {
    $memberMap = [];

    foreach ($projectMembers as $member) {
        if (!is_array($member)) {
            continue;
        }

        $memberMap[trim((string) ($member['id'] ?? ''))] = $member;
    }

    $recipientRows = [];
    $responsibleMemberId = trim((string) ($column['responsible_member_id'] ?? ''));
    $responsibleMember = $responsibleMemberId !== '' ? ($memberMap[$responsibleMemberId] ?? null) : null;
    $followers = $repository->listColumnFollowers($accessToken, trim((string) ($boardPayload['board']['id'] ?? '')), trim((string) ($column['id'] ?? '')));
    $recipients = [];

    if (is_array($responsibleMember) && trim((string) ($responsibleMember['user_id'] ?? '')) !== '') {
        $recipients[trim((string) ($responsibleMember['user_id'] ?? ''))] = [
            'user_id' => trim((string) ($responsibleMember['user_id'] ?? '')),
            'email' => strtolower(trim((string) ($responsibleMember['invited_email'] ?? ''))),
        ];
    }

    foreach ($followers as $follow) {
        if (!is_array($follow)) {
            continue;
        }

        $followUserId = trim((string) ($follow['user_id'] ?? ''));

        if ($followUserId === '') {
            continue;
        }

        $recipients[$followUserId] = [
            'user_id' => $followUserId,
            'email' => strtolower(trim((string) ($follow['user_email'] ?? ''))),
        ];
    }

    $boardId = trim((string) ($boardPayload['board']['id'] ?? ''));
    $cardTitle = trim((string) ($card['title'] ?? 'Ficha'));
    $columnTitle = trim((string) ($column['title'] ?? 'Lista'));

    foreach ($recipients as $recipientUserId => $recipient) {
        if ($recipientUserId === '' || $recipientUserId === $actorUserId) {
            continue;
        }

        $recipientRows[] = [
            'user_id' => $recipientUserId,
            'project_id' => $projectId,
            'source_tool_slug' => 'task-boards',
            'source_type' => 'column_card_entry',
            'title' => 'Nueva ficha en ' . $columnTitle,
            'body' => $cardTitle . ' entro en ' . $columnTitle . '.',
            'destination' => [
                'board_id' => $boardId,
                'card_id' => trim((string) ($card['id'] ?? '')),
                'column_id' => trim((string) ($column['id'] ?? '')),
            ],
            'payload' => [
                'actor_email' => $actorEmail,
            ],
        ];
    }

    $cards = is_array($boardPayload['cards'] ?? null) ? $boardPayload['cards'] : [];
    $cardId = trim((string) ($card['id'] ?? ''));

    if ($cardId !== '') {
        $seen = false;

        foreach ($cards as $candidate) {
            if (is_array($candidate) && trim((string) ($candidate['id'] ?? '')) === $cardId) {
                $seen = true;
                break;
            }
        }

        if (!$seen) {
            $cards[] = $card;
        }
    }

    $cardsInColumn = array_values(array_filter($cards, static function ($candidate) use ($column): bool {
        return is_array($candidate) && trim((string) ($candidate['column_id'] ?? '')) === trim((string) ($column['id'] ?? ''));
    }));
    $limit = is_numeric($column['wip_limit'] ?? null) ? (int) ($column['wip_limit'] ?? 0) : null;

    if ($limit !== null && count($cardsInColumn) > $limit) {
        foreach ($recipients as $recipientUserId => $recipient) {
            if ($recipientUserId === '') {
                continue;
            }

            $recipientRows[] = [
                'user_id' => $recipientUserId,
                'project_id' => $projectId,
                'source_tool_slug' => 'task-boards',
                'source_type' => 'column_over_limit',
                'title' => $columnTitle . ' superó su límite de fichas',
                'body' => 'La lista tiene ' . count($cardsInColumn) . ' fichas activas y su límite es ' . $limit . '.',
                'destination' => [
                    'board_id' => $boardId,
                    'column_id' => trim((string) ($column['id'] ?? '')),
                ],
                'payload' => [
                    'limit' => $limit,
                    'current_count' => count($cardsInColumn),
                ],
            ];
        }
    }

    return $recipientRows;
}

function taskBoardsExecuteRule(
    TaskBoardRepository $repository,
    string $accessToken,
    string $projectId,
    string $boardId,
    array $rule,
    ?array $card
): void {
    $columnId = trim((string) ($rule['column_id'] ?? ''));
    $actionType = taskBoardsNormalizeRuleAction((string) ($rule['action_type'] ?? 'sort_list'));
    $config = is_array($rule['config'] ?? null) ? $rule['config'] : [];

    if ($columnId === '') {
        return;
    }

    if ($actionType === 'sort_list') {
        taskBoardsSortColumnCards(
            $repository,
            $accessToken,
            $boardId,
            $columnId,
            trim((string) ($config['sort_key'] ?? 'due_date')),
            trim((string) ($config['direction'] ?? 'asc'))
        );

        $recipientIds = [];
        $creatorId = trim((string) ($rule['created_by'] ?? ''));

        if ($creatorId !== '') {
            $recipientIds[$creatorId] = true;
        }

        foreach ($repository->listColumnFollowers($accessToken, $boardId, $columnId) as $follow) {
            if (!is_array($follow)) {
                continue;
            }

            $followUserId = trim((string) ($follow['user_id'] ?? ''));

            if ($followUserId !== '') {
                $recipientIds[$followUserId] = true;
            }
        }

        if ($recipientIds !== []) {
            $repository->createNotifications($accessToken, array_map(static function (string $recipientUserId) use ($projectId, $boardId, $columnId, $rule): array {
                return [
                    'user_id' => $recipientUserId,
                    'project_id' => $projectId,
                    'source_tool_slug' => 'task-boards',
                    'source_type' => 'column_rule_sorted',
                    'title' => 'La automatizacion reordeno una lista',
                    'body' => 'Se ejecuto la regla "' . trim((string) ($rule['title'] ?? 'Regla de columna')) . '".',
                    'destination' => [
                        'board_id' => $boardId,
                        'column_id' => $columnId,
                    ],
                    'payload' => [
                        'rule_id' => trim((string) ($rule['id'] ?? '')),
                    ],
                ];
            }, array_keys($recipientIds)));
        }
    } elseif ($actionType === 'assign_responsible' && is_array($card)) {
        $column = $repository->findColumn($accessToken, $boardId, $columnId);
        $memberId = trim((string) ($column['responsible_member_id'] ?? ''));
        $assigned = taskBoardsNormalizeStringArray($card['assigned_member_ids'] ?? []);

        if ($memberId !== '' && !in_array($memberId, $assigned, true)) {
            $assigned[] = $memberId;
            $repository->updateCard($accessToken, $boardId, trim((string) ($card['id'] ?? '')), [
                'assigned_member_ids' => array_values($assigned),
            ]);
        }
    } elseif ($actionType === 'create_notification') {
        $recipientUserId = trim((string) ($rule['created_by'] ?? ''));

        if ($recipientUserId !== '') {
            $repository->createNotifications($accessToken, [[
                'user_id' => $recipientUserId,
                'project_id' => $projectId,
                'source_tool_slug' => 'task-boards',
                'source_type' => 'column_rule',
                'title' => trim((string) ($config['title'] ?? ($rule['title'] ?? 'Regla ejecutada'))),
                'body' => trim((string) ($config['body'] ?? 'Se ejecuto una regla de columna.')),
                'destination' => [
                    'board_id' => $boardId,
                    'column_id' => $columnId,
                    'card_id' => is_array($card) ? trim((string) ($card['id'] ?? '')) : '',
                ],
                'payload' => [
                    'rule_id' => trim((string) ($rule['id'] ?? '')),
                ],
            ]]);
        }
    }

    $repository->saveColumnRule($accessToken, [
        'id' => trim((string) ($rule['id'] ?? '')),
        'board_id' => $boardId,
        'column_id' => $columnId,
        'title' => trim((string) ($rule['title'] ?? 'Regla de columna')),
        'trigger_type' => taskBoardsNormalizeRuleTrigger((string) ($rule['trigger_type'] ?? 'card_added')),
        'action_type' => $actionType,
        'config' => $config,
        'is_active' => filter_var($rule['is_active'] ?? true, FILTER_VALIDATE_BOOL),
        'created_by' => trim((string) ($rule['created_by'] ?? '')) ?: null,
        'created_by_email' => trim((string) ($rule['created_by_email'] ?? '')),
        'last_run_at' => gmdate('c'),
    ]);
}
