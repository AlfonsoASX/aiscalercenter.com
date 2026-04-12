<?php
declare(strict_types=1);

use AiScaler\TaskBoards\TaskBoardRepository;

require_once __DIR__ . '/../../modules/task-boards/bootstrap.php';

$toolContext = is_array($toolRuntimeContext ?? null) ? $toolRuntimeContext : [];
$accessToken = trim((string) ($toolContext['access_token'] ?? ''));
$userId = trim((string) ($toolContext['user_id'] ?? ''));
$userEmail = trim((string) ($toolContext['user_email'] ?? ''));
$projectContext = is_array($toolContext['project'] ?? null) ? $toolContext['project'] : [];
$activeProjectId = trim((string) ($projectContext['id'] ?? ''));
$repository = new TaskBoardRepository();
$project = null;
$members = [];
$boards = [];
$activeBoard = null;
$error = null;
$launchToken = rawurlencode((string) ($toolContext['launch_token'] ?? ''));
$apiUrl = 'tool-action.php?launch=' . $launchToken;

try {
    if ($accessToken === '' || $userId === '') {
        throw new RuntimeException('No encontramos la sesion segura para abrir Tableros de tareas. Vuelve a abrir la herramienta desde el panel.');
    }

    if ($activeProjectId === '') {
        throw new RuntimeException('Selecciona un proyecto antes de crear tableros.');
    }

    $project = $repository->findProject($accessToken, $activeProjectId);

    if (!is_array($project)) {
        throw new RuntimeException('No encontramos el proyecto activo para cargar los tableros.');
    }

    $members = taskBoardsNormalizeMembers(
        $repository->listProjectMembers($accessToken, $activeProjectId),
        $userId,
        $userEmail
    );
    $boards = taskBoardsNormalizeBoards($repository->listBoards($accessToken, $activeProjectId));

    if ($boards !== []) {
        $selectedBoardId = trim((string) ($_GET['board_id'] ?? ''));

        if ($selectedBoardId === '' || !taskBoardsBoardExists($boards, $selectedBoardId)) {
            $selectedBoardId = (string) ($boards[0]['id'] ?? '');
        }

        if ($selectedBoardId !== '') {
            $activeBoard = taskBoardsNormalizeBoardState(
                $repository->getBoardState($accessToken, $activeProjectId, $selectedBoardId)
            );
        }
    }
} catch (Throwable $exception) {
    $error = normalizeTaskBoardException($exception);
}

$initialState = [
    'project' => [
        'id' => (string) ($project['id'] ?? $activeProjectId),
        'name' => (string) ($project['name'] ?? 'Proyecto'),
        'logo_url' => (string) ($project['logo_url'] ?? ''),
    ],
    'viewer' => [
        'user_id' => $userId,
        'email' => $userEmail,
    ],
    'boards' => $boards,
    'members' => $members,
    'active_board' => $activeBoard,
    'realtime' => [
        'supabase_url' => supabaseProjectUrl(),
        'supabase_key' => supabaseApiKey(),
        'access_token' => $accessToken,
    ],
];
?>
<div
    class="task-boards-page"
    data-task-boards="true"
    data-api-url="<?= htmlspecialchars($apiUrl, ENT_QUOTES, 'UTF-8'); ?>"
>
    <header class="task-boards-context-bar">
        <div class="task-boards-context-copy">
            <p class="task-boards-eyebrow">Diseñar</p>
            <h1>Tableros de tareas</h1>
            <p>Proyecto activo: <?= htmlspecialchars((string) ($project['name'] ?? 'Proyecto'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>

        <?php if ($error === null): ?>
            <div class="task-boards-context-actions">
                <button type="button" class="task-boards-primary" data-task-board-create>
                    <span class="material-symbols-rounded">add_circle</span>
                    <span>Nuevo tablero</span>
                </button>
            </div>
        <?php endif; ?>
    </header>

    <?php if ($error !== null): ?>
        <div class="task-boards-notice task-boards-notice--error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php else: ?>
        <div class="task-boards-notice hidden" data-task-boards-notice></div>
        <section class="task-boards-shell" data-task-boards-shell></section>
        <script id="task-boards-state" type="application/json"><?= taskBoardsJsonEncode($initialState); ?></script>
        <script type="module" src="tool-asset.php?launch=<?= htmlspecialchars($launchToken, ENT_QUOTES, 'UTF-8'); ?>&asset=app.js"></script>
    <?php endif; ?>
</div>
