<?php
declare(strict_types=1);

use AiScaler\WhatsAppBots\WhatsAppBotRepository;

require_once __DIR__ . '/../../modules/whatsapp-bots/bootstrap.php';

$toolContext = is_array($toolRuntimeContext ?? null) ? $toolRuntimeContext : [];
$accessToken = trim((string) ($toolContext['access_token'] ?? ''));
$userId = trim((string) ($toolContext['user_id'] ?? ''));
$projectContext = is_array($toolContext['project'] ?? null) ? $toolContext['project'] : [];
$activeProjectId = trim((string) ($projectContext['id'] ?? ''));
$launchToken = rawurlencode((string) ($toolContext['launch_token'] ?? ''));
$apiUrl = 'tool-action.php?launch=' . $launchToken;
$repository = new WhatsAppBotRepository();
$error = null;
$initialState = [
    'project' => null,
    'bots' => [],
    'active_bot_id' => '',
    'templates' => [],
    'conversations' => [],
    'messages' => [],
    'active_conversation_id' => '',
    'inbox_counts' => ['bot' => 0, 'human' => 0],
];

try {
    if ($accessToken === '' || $userId === '') {
        throw new RuntimeException('No encontramos la sesion segura para abrir Bots de WhatsApp.');
    }

    if ($activeProjectId === '') {
        throw new RuntimeException('Selecciona un proyecto antes de abrir Bots de WhatsApp.');
    }

    $project = $repository->findProject($accessToken, $activeProjectId);

    if (!is_array($project)) {
        throw new RuntimeException('No encontramos el proyecto activo de esta herramienta.');
    }

    $repository->ensureDefaultBot($accessToken, $activeProjectId, $userId, (string) ($project['name'] ?? ''));
    $bots = $repository->listBots($accessToken, $activeProjectId);
    $activeBotId = trim((string) ($_GET['bot'] ?? (string) ($bots[0]['id'] ?? '')));
    $activeBot = null;

    foreach ($bots as $bot) {
        if (is_array($bot) && trim((string) ($bot['id'] ?? '')) === $activeBotId) {
            $activeBot = $bot;
            break;
        }
    }

    if (!is_array($activeBot) && isset($bots[0]) && is_array($bots[0])) {
        $activeBot = $bots[0];
        $activeBotId = (string) ($activeBot['id'] ?? '');
    }

    $templates = $activeBotId !== '' ? $repository->listTemplates($accessToken, $activeBotId, $activeProjectId) : [];
    $conversations = $activeBotId !== '' ? $repository->listConversations($accessToken, $activeBotId, $activeProjectId, 'all') : [];
    $activeConversationId = trim((string) ($_GET['chat'] ?? (string) ($conversations[0]['id'] ?? '')));
    $messages = $activeConversationId !== '' && $activeBotId !== ''
        ? $repository->listMessages($accessToken, $activeConversationId, $activeBotId, $activeProjectId)
        : [];

    $inboxCounts = [
        'bot' => 0,
        'human' => 0,
    ];

    foreach ($conversations as $conversation) {
        if (!is_array($conversation)) {
            continue;
        }

        $bucket = trim((string) ($conversation['inbox_status'] ?? 'bot'));

        if ($bucket === 'humano') {
            $inboxCounts['human']++;
        } else {
            $inboxCounts['bot']++;
        }
    }

    $initialState = [
        'project' => $project,
        'bots' => $bots,
        'active_bot_id' => $activeBotId,
        'templates' => $templates,
        'conversations' => $conversations,
        'messages' => $messages,
        'active_conversation_id' => $activeConversationId,
        'inbox_counts' => $inboxCounts,
        'webhook' => [
            'url' => is_array($activeBot) ? whatsappBotWebhookUrl((string) ($activeBot['public_key'] ?? '')) : '',
            'public_key' => is_array($activeBot) ? (string) ($activeBot['public_key'] ?? '') : '',
            'verify_token' => is_array($activeBot) ? (string) ($activeBot['verify_token'] ?? '') : '',
        ],
    ];
} catch (Throwable $exception) {
    $error = normalizeWhatsAppBotException($exception);
}
?>
<div class="wa-bot-page" data-wa-bot-app="true" data-api-url="<?= htmlspecialchars($apiUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <header class="wa-bot-hero">
        <div class="wa-bot-hero-copy">
            <p class="wa-bot-eyebrow">Ejecutar</p>
            <h1>Creacion de bots de WhatsApp</h1>
            <p>Configura respuestas guiadas, opera la bandeja humana y prepara plantillas para que el seguimiento comercial se sienta rapido y natural.</p>
        </div>
    </header>

    <?php if ($error !== null): ?>
        <div class="wa-bot-notice wa-bot-notice--error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php else: ?>
        <div class="wa-bot-notice hidden" data-wa-bot-notice></div>

        <section class="wa-bot-shell">
            <div class="wa-bot-toolbar">
                <div class="wa-bot-toolbar-group">
                    <label class="wa-bot-picker">
                        <span>Bot activo</span>
                        <select data-wa-bot-select></select>
                    </label>

                    <button type="button" class="wa-secondary-button" data-wa-bot-create>
                        <span class="material-symbols-rounded">smart_toy</span>
                        <span>Nuevo bot</span>
                    </button>
                </div>

                <div class="wa-bot-toolbar-group wa-bot-toolbar-group--compact">
                    <button type="button" class="wa-bot-tab is-active" data-wa-tab-trigger="setup">Setup</button>
                    <button type="button" class="wa-bot-tab" data-wa-tab-trigger="inbox">Inbox</button>
                    <button type="button" class="wa-bot-tab" data-wa-tab-trigger="templates">Plantillas</button>
                </div>
            </div>

            <section class="wa-bot-panel" data-wa-tab-panel="setup"></section>
            <section class="wa-bot-panel hidden" data-wa-tab-panel="inbox"></section>
            <section class="wa-bot-panel hidden" data-wa-tab-panel="templates"></section>
        </section>

        <script id="wa-bot-state" type="application/json"><?= json_encode($initialState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
        <script src="tool-asset.php?launch=<?= htmlspecialchars($launchToken, ENT_QUOTES, 'UTF-8'); ?>&asset=app.js"></script>
    <?php endif; ?>
</div>
