<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/pwa.php';
require_once __DIR__ . '/lib/account_session.php';
require_once __DIR__ . '/lib/app_guards.php';
require_once __DIR__ . '/modules/tools/bootstrap.php';

ensureAccountSessionStarted();
ensureToolsSessionStarted();

$appKey = trim((string) ($_GET['app'] ?? ''));
$app = findPrivateApp($appKey);

if (!is_array($app)) {
    http_response_code(404);
    exit('App privada no encontrada.');
}

try {
    [$accessToken, $user, $app, $billingState] = requireAppAccess($appKey);
} catch (Throwable) {
    accountRedirectToLogin('/' . (string) ($app['route'] ?? $appKey));
}

$displayName = trim((string) ($user['user_metadata']['full_name'] ?? '')) ?: trim((string) ($user['email'] ?? 'Usuario'));
$workspace = privateAppResolveWorkspace($accessToken, $user, $app);
$workspaceName = trim((string) ($workspace['name'] ?? 'Workspace'));
$workspaceId = trim((string) ($workspace['id'] ?? ''));
$workspaceLogoUrl = trim((string) ($workspace['logo_url'] ?? ''));
$returnUrl = appAccountUrl();
$pageTitle = (string) ($app['title'] ?? 'App');
$toolContext = null;
$appStyleHref = null;
$moduleStylesheet = null;
$renderMode = (string) ($app['mode'] ?? 'php_folder');
$writeBlockedMessage = '';

$supabaseConfig = require __DIR__ . '/config/supabase.php';
$panelConfig = require __DIR__ . '/config/panel.php';
$supabaseProjectUrl = trim((string) ($supabaseConfig['project_url'] ?? ''));
$publishableKey = trim((string) ($supabaseConfig['publishable_key'] ?? ''));
$anonKey = trim((string) ($supabaseConfig['anon_key'] ?? ''));
$supabasePublicKey = $publishableKey !== '' && $publishableKey !== 'tu_publishable_key' ? $publishableKey : $anonKey;
$hasSupabaseConfig = $supabaseProjectUrl !== ''
    && $supabasePublicKey !== ''
    && $supabasePublicKey !== 'tu_publishable_key'
    && $supabasePublicKey !== 'tu_anon_key';

$authClientConfig = [
    'supabaseUrl' => $supabaseProjectUrl,
    'supabaseKey' => $supabasePublicKey,
    'landingUrl' => appHomeUrl(),
    'loginUrl' => appLoginUrl(),
    'appUrl' => appAccountUrl(),
    'accountUrl' => appAccountUrl(),
    'defaultAfterLoginUrl' => privateAppUrl($appKey),
    'redirectTarget' => privateAppUrl($appKey),
    'accountSessionUrl' => appToolUrl('api/account-session.php'),
    'toolsSessionUrl' => appToolUrl('api/tools-session.php'),
    'hasSupabaseConfig' => $hasSupabaseConfig,
    'panel' => $panelConfig,
];

if ($renderMode === 'redirect_legacy') {
    header('Location: ' . appPanelUrl((string) ($app['legacy_section'] ?? 'inicio')), true, 302);
    exit;
}

if ((bool) ($billingState['read_only'] ?? true) && !in_array(strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')), ['GET', 'HEAD'], true)) {
    $writeBlockedMessage = 'Tu cuenta esta en modo lectura. Reactiva tu suscripcion para volver a editar.';
    $_POST = [];
    $_FILES = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
}

$tool = findAppToolBySlug((string) ($app['tool_slug'] ?? ''), $user);

if (!is_array($tool)) {
    http_response_code(404);
    exit('No fue posible resolver la app privada solicitada.');
}

$privateConfig = getToolLaunchConfig((string) ($tool['slug'] ?? ''));
$launchToken = bin2hex(random_bytes(24));
$mergedTool = mergeToolWithPrivateConfig($tool, $privateConfig);
$sanitizedTool = sanitizeToolForLaunch($mergedTool, $returnUrl, [
    'id' => 'cuenta',
    'label' => 'Cuenta',
]);

$sanitizedTool['private_route'] = privateAppUrl($appKey);
$sanitizedTool['hide_sidebar'] = true;
$sanitizedTool['hide_tool_chrome'] = true;

rememberToolLaunch($launchToken, [
    'tool' => $sanitizedTool,
    'user_id' => (string) ($user['id'] ?? ''),
    'access_token' => $accessToken,
    'project' => [
        'id' => $workspaceId,
        'name' => $workspaceName,
        'logo_url' => $workspaceLogoUrl,
    ],
    'user' => [
        'email' => (string) ($user['email'] ?? ''),
        'display_name' => $displayName,
        'role' => isToolsAdminUser($user) ? 'admin' : 'regular',
    ],
    'created_at' => time(),
    'read_only' => (bool) ($billingState['read_only'] ?? true),
    'private_app_key' => $appKey,
    'private_base_url' => privateAppUrl($appKey),
    'write_blocked_message' => $writeBlockedMessage,
    'subscription' => is_array($billingState['subscription'] ?? null) ? $billingState['subscription'] : [],
]);

$toolContext = [
    'launch_token' => $launchToken,
    'slug' => (string) ($tool['slug'] ?? ''),
    'title' => (string) ($tool['title'] ?? ''),
    'description' => (string) ($tool['description'] ?? ''),
    'tutorial_youtube_url' => (string) ($tool['tutorial_youtube_url'] ?? ''),
    'return_url' => $returnUrl,
    'embed_mode' => false,
    'access_token' => $accessToken,
    'user_id' => (string) ($user['id'] ?? ''),
    'user_email' => (string) ($user['email'] ?? ''),
    'project' => [
        'id' => $workspaceId,
        'name' => $workspaceName,
        'logo_url' => $workspaceLogoUrl,
    ],
    'read_only' => (bool) ($billingState['read_only'] ?? true),
    'private_app_key' => $appKey,
    'private_base_url' => privateAppUrl($appKey),
    'write_blocked_message' => $writeBlockedMessage,
    'subscription' => is_array($billingState['subscription'] ?? null) ? $billingState['subscription'] : [],
];

if ($renderMode === 'php_folder') {
    $appDirectory = realpath(__DIR__ . '/' . trim((string) ($app['app_folder'] ?? ''), '/')) ?: '';
    $entryFile = $appDirectory !== '' ? realpath($appDirectory . '/index.php') : false;

    if ($appDirectory === '' || $entryFile === false) {
        http_response_code(404);
        exit('La app privada no tiene un entrypoint valido.');
    }

    if (is_file($appDirectory . '/style.css')) {
        $appStyleHref = 'tool-asset.php?launch=' . rawurlencode($launchToken) . '&asset=style.css';
    }

    ob_start();
    $toolRuntimeContext = $toolContext;
    require $entryFile;
    $privateAppContent = (string) ob_get_clean();
} else {
    $toolRuntimePayload = [
        'slug' => (string) ($tool['slug'] ?? ''),
        'title' => (string) ($tool['title'] ?? ''),
        'description' => (string) ($tool['description'] ?? ''),
        'tutorial_youtube_url' => (string) ($tool['tutorial_youtube_url'] ?? ''),
        'panel_module_key' => (string) ($app['panel_module_key'] ?? $tool['panel_module_key'] ?? ''),
        'return_url' => $returnUrl,
        'launch_url' => privateAppUrl($appKey),
    ];
    $panelModuleKey = (string) ($toolRuntimePayload['panel_module_key'] ?? '');
    $moduleStylesheet = match ($panelModuleKey) {
        'research_market_signals',
        'research_google',
        'research_youtube',
        'research_mercado_libre',
        'research_amazon' => 'css/modules/research.css',
        'social_post_scheduler' => 'css/modules/execute.css',
        default => null,
    };
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> - AiScaler Center</title>
    <?= renderPwaHead([
        'description' => 'App privada del ecosistema ASX con acceso directo desde tu cuenta.',
        'background_color' => '#f5f7fb',
    ]); ?>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,500,0,0">
    <?php if ($appStyleHref !== null): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($appStyleHref, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php if ($moduleStylesheet !== null): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars(pwaAssetUrl($moduleStylesheet), ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php if ($renderMode === 'panel_module'): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars(pwaAssetUrl('css/tool-runtime.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <style>
        :root {
            --app-shell-bg: #f5f7fb;
            --app-shell-card: rgba(255,255,255,.94);
            --app-shell-line: rgba(19,32,58,.12);
            --app-shell-text: #13203a;
            --app-shell-muted: #5f6b7c;
            --app-shell-primary: #0f766e;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Manrope, sans-serif;
            color: var(--app-shell-text);
            background:
                radial-gradient(circle at top left, rgba(15,118,110,.12), transparent 24rem),
                linear-gradient(180deg, #f8fbff 0%, var(--app-shell-bg) 100%);
        }
        .private-app-shell {
            width: min(1480px, calc(100% - 1.25rem));
            margin: 0 auto;
            padding: .75rem 0 1.5rem;
        }
        .private-app-topbar, .private-app-banner {
            border: 1px solid var(--app-shell-line);
            border-radius: 1.4rem;
            background: var(--app-shell-card);
            backdrop-filter: blur(18px);
            box-shadow: 0 18px 40px rgba(19,32,58,.08);
        }
        .private-app-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .95rem 1.1rem;
        }
        .private-app-brand, .private-app-meta, .private-app-actions {
            display: flex;
            align-items: center;
            gap: .75rem;
        }
        .private-app-brand a, .private-app-link {
            color: inherit;
            text-decoration: none;
            font-weight: 800;
        }
        .private-app-chip {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .5rem .75rem;
            border-radius: 999px;
            border: 1px solid var(--app-shell-line);
            background: rgba(15,118,110,.08);
            color: var(--app-shell-primary);
            font-size: .85rem;
            font-weight: 800;
        }
        .private-app-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .55rem;
            min-height: 2.95rem;
            padding: 0 1rem;
            border-radius: 999px;
            border: 1px solid var(--app-shell-line);
            background: #fff;
            color: var(--app-shell-text);
            font: inherit;
            text-decoration: none;
            cursor: pointer;
        }
        .private-app-button--primary {
            background: linear-gradient(135deg, #0f766e, #115e59);
            color: #fff;
            border-color: rgba(15,118,110,.2);
        }
        .private-app-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-top: .85rem;
            padding: .95rem 1rem;
        }
        .private-app-main { margin-top: 1rem; }
        @media (max-width: 960px) {
            .private-app-topbar, .private-app-banner { flex-direction: column; align-items: stretch; }
            .private-app-brand, .private-app-meta, .private-app-actions { flex-wrap: wrap; }
        }
    </style>
    <script>
        window.AISCALER_AUTH_CONFIG = <?= json_encode($authClientConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        <?php if ($renderMode === 'panel_module'): ?>
        window.AISCALER_TOOL_PAYLOAD = <?= json_encode($toolRuntimePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        <?php endif; ?>
    </script>
    <?php if ($renderMode === 'panel_module'): ?>
        <script type="module" src="<?= htmlspecialchars(pwaAssetUrl('js/tool-runtime.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <?php endif; ?>
    <script type="module" src="<?= htmlspecialchars(pwaAssetUrl('js/account-shell.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</head>
<body data-view="private-app">
    <div class="private-app-shell">
        <header class="private-app-topbar">
            <div class="private-app-brand">
                <a href="<?= htmlspecialchars(appAccountUrl(), ENT_QUOTES, 'UTF-8'); ?>">AiScaler</a>
                <div>
                    <strong><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></strong>
                    <div style="color:var(--app-shell-muted); font-size:.92rem;"><?= htmlspecialchars($workspaceName, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>

            <div class="private-app-meta">
                <span class="private-app-chip"><span class="material-symbols-rounded">folder_managed</span><?= htmlspecialchars($workspaceName, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="private-app-chip"><span class="material-symbols-rounded"><?= $billingState['read_only'] ? 'lock' : 'verified'; ?></span><?= htmlspecialchars($billingState['read_only'] ? 'Modo lectura' : 'Suscripcion activa', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>

            <div class="private-app-actions">
                <a class="private-app-button" href="<?= htmlspecialchars(appAccountUrl(), ENT_QUOTES, 'UTF-8'); ?>">Cuenta</a>
                <button type="button" class="private-app-button private-app-button--primary" data-account-logout>Salir</button>
            </div>
        </header>

        <?php if ($billingState['read_only']): ?>
            <section class="private-app-banner">
                <div>
                    <strong>Tu cuenta esta en modo lectura.</strong>
                    <div style="color:var(--app-shell-muted); margin-top:.25rem;">Puedes revisar tus datos, pero no crear ni editar hasta reactivar la suscripcion.</div>
                </div>
                <a class="private-app-button private-app-button--primary" href="<?= htmlspecialchars(appAccountUrl('suscripcion'), ENT_QUOTES, 'UTF-8'); ?>">Reactivar suscripcion</a>
            </section>
        <?php endif; ?>

        <main class="private-app-main">
            <?php if ($renderMode === 'php_folder'): ?>
                <?= $privateAppContent; ?>
            <?php else: ?>
                <div class="tool-runtime-shell">
                    <header class="tool-runtime-header">
                        <div class="tool-runtime-header-copy">
                            <div>
                                <p class="tool-runtime-eyebrow">App privada</p>
                                <h1><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
                                <p><?= htmlspecialchars((string) ($tool['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        </div>
                        <div class="tool-runtime-header-user">
                            <span id="tool-user-name"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </header>
                    <div id="tool-notice" class="tool-runtime-notice hidden"></div>
                    <main id="tool-runtime-mount" class="tool-runtime-mount"></main>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
