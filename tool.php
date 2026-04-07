<?php
declare(strict_types=1);

require_once __DIR__ . '/modules/tools/bootstrap.php';

ensureToolsSessionStarted();

$launchToken = trim((string) ($_GET['launch'] ?? ''));
$isEmbedMode = trim((string) ($_GET['embed'] ?? '')) === '1';
$launchPayload = $launchToken !== '' ? findToolLaunch($launchToken) : null;
$tool = is_array($launchPayload['tool'] ?? null) ? $launchPayload['tool'] : null;

if (!$tool) {
    redirectToolsWithClientFlash(
        buildToolsPanelUrl(),
        'La sesion para abrir esta herramienta expiro. Vuelve a abrirla desde el panel.',
        'error'
    );
}

$launchMode = (string) ($tool['launch_mode'] ?? 'php_folder');

if ($launchMode === 'php_folder') {
    $launchUser = is_array($launchPayload['user'] ?? null) ? $launchPayload['user'] : [];
    $workspaceRoot = realpath(__DIR__) ?: __DIR__;
    $appFolder = trim((string) ($tool['app_folder'] ?? ''), '/');
    $entryFile = ltrim((string) ($tool['entry_file'] ?? 'index.php'), '/');

    if ($appFolder === '' || !isSafeRelativePath($appFolder) || !isSafeRelativePath($entryFile)) {
        redirectToolsWithClientFlash(
            (string) ($tool['return_url'] ?? buildToolsPanelUrl()),
            'La herramienta no tiene una ruta de aplicacion valida.',
            'error'
        );
    }

    $appDirectory = realpath($workspaceRoot . DIRECTORY_SEPARATOR . $appFolder);

    if ($appDirectory === false || !str_starts_with($appDirectory, $workspaceRoot . DIRECTORY_SEPARATOR)) {
        redirectToolsWithClientFlash(
            (string) ($tool['return_url'] ?? buildToolsPanelUrl()),
            'No fue posible resolver la carpeta protegida de la herramienta.',
            'error'
        );
    }

    $entryPath = realpath($appDirectory . DIRECTORY_SEPARATOR . $entryFile);

    if ($entryPath === false || !str_starts_with($entryPath, $appDirectory . DIRECTORY_SEPARATOR)) {
        redirectToolsWithClientFlash(
            (string) ($tool['return_url'] ?? buildToolsPanelUrl()),
            'No fue posible abrir el archivo de entrada de la herramienta.',
            'error'
        );
    }

    $toolRuntimeContext = [
        'launch_token' => $launchToken,
        'slug' => (string) ($tool['slug'] ?? ''),
        'title' => (string) ($tool['title'] ?? ''),
        'description' => (string) ($tool['description'] ?? ''),
        'tutorial_youtube_url' => (string) ($tool['tutorial_youtube_url'] ?? ''),
        'return_url' => (string) ($tool['return_url'] ?? buildToolsPanelUrl()),
        'embed_mode' => $isEmbedMode,
    ];
    $panelConfig = require __DIR__ . '/config/panel.php';
    $role = (string) ($launchUser['role'] ?? 'regular') === 'admin' ? 'admin' : 'regular';
    $dashboardItem = is_array($panelConfig['dashboard'] ?? null) ? $panelConfig['dashboard'] : ['id' => 'inicio', 'label' => 'Inicio'];
    $accountItem = is_array($panelConfig['account_section'] ?? null) ? $panelConfig['account_section'] : ['id' => 'configuracion', 'label' => 'Configuracion'];
    $roleMenu = is_array($panelConfig['menus'][$role] ?? null) ? $panelConfig['menus'][$role] : [];
    $displayName = trim((string) ($launchUser['display_name'] ?? '')) ?: trim((string) ($launchUser['email'] ?? '')) ?: 'Usuario';
    $userEmail = trim((string) ($launchUser['email'] ?? '')) ?: 'Cuenta sin correo';
    $activeCategoryKey = trim((string) ($tool['category_key'] ?? ''));
    $workspaceAccentRgb = '47, 124, 239';

    foreach ($roleMenu as $menuItem) {
        if (!is_array($menuItem) || (string) ($menuItem['tool_category_key'] ?? '') !== $activeCategoryKey) {
            continue;
        }

        $hexColor = strtoupper(ltrim(trim((string) ($menuItem['color'] ?? '')), '#'));

        if (preg_match('/^[0-9A-F]{3}$/', $hexColor) === 1) {
            $hexColor = $hexColor[0] . $hexColor[0] . $hexColor[1] . $hexColor[1] . $hexColor[2] . $hexColor[2];
        }

        if (preg_match('/^[0-9A-F]{6}$/', $hexColor) === 1) {
            $workspaceAccentRgb = hexdec(substr($hexColor, 0, 2)) . ', ' . hexdec(substr($hexColor, 2, 2)) . ', ' . hexdec(substr($hexColor, 4, 2));
        }

        break;
    }

    $toolTitle = (string) ($tool['title'] ?? 'Herramienta');
    $toolDescription = (string) ($tool['description'] ?? '');
    $appStyleHref = is_file($appDirectory . DIRECTORY_SEPARATOR . 'style.css')
        ? 'tool-asset.php?launch=' . rawurlencode($launchToken) . '&asset=style.css'
        : null;

    ob_start();
    require $entryPath;
    $toolPageContent = (string) ob_get_clean();
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($toolTitle, ENT_QUOTES, 'UTF-8'); ?> - AiScaler Center</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,500,0,0">
    <link rel="stylesheet" href="css/tool-panel-shell.css">
    <?php if ($appStyleHref !== null): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($appStyleHref, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
</head>
<body data-view="tool" class="workspace-tool-body">
    <div id="app-layout" class="workspace-app" style="--workspace-accent-rgb: <?= htmlspecialchars($workspaceAccentRgb, ENT_QUOTES, 'UTF-8'); ?>;">
        <aside id="app-sidebar" class="workspace-sidebar" aria-label="Menú lateral">
            <div class="workspace-sidebar-head">
                <button id="sidebar-toggle" type="button" class="workspace-icon-button" aria-label="Comprimir menú lateral">
                    <span class="material-symbols-rounded">menu</span>
                </button>

                <a id="dashboard-link" href="<?= htmlspecialchars(buildToolsPanelUrl((string) ($dashboardItem['id'] ?? 'inicio')), ENT_QUOTES, 'UTF-8'); ?>" class="workspace-icon-button" aria-label="Ir a inicio" title="Inicio">
                    <span class="material-symbols-rounded">home</span>
                </a>
            </div>

            <nav id="app-rail-nav" class="workspace-nav" aria-label="Menú principal">
                <?php foreach ($roleMenu as $menuItem): ?>
                    <?php
                    $menuItem = is_array($menuItem) ? $menuItem : [];
                    $menuTargetId = (string) ($menuItem['id'] ?? '');
                    $menuCategoryKey = (string) ($menuItem['tool_category_key'] ?? '');
                    $isActive = $menuCategoryKey !== '' && $menuCategoryKey === $activeCategoryKey;
                    $menuHref = 'index.php?view=app#' . rawurlencode($menuTargetId);
                    ?>
                    <a href="<?= htmlspecialchars($menuHref, ENT_QUOTES, 'UTF-8'); ?>" class="workspace-nav-button<?= $isActive ? ' is-active' : ''; ?>" aria-current="<?= $isActive ? 'page' : 'false'; ?>">
                        <span class="workspace-nav-icon" aria-hidden="true">
                            <img src="<?= htmlspecialchars((string) ($menuItem['icon_path'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" alt="">
                        </span>
                        <span class="workspace-nav-copy">
                            <span class="workspace-nav-text workspace-nav-text--default"><?= htmlspecialchars((string) ($menuItem['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="workspace-nav-text workspace-nav-text--hover"><?= htmlspecialchars((string) ($menuItem['hover_label'] ?? ($menuItem['label'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </aside>

        <a href="<?= htmlspecialchars((string) ($tool['return_url'] ?? buildToolsPanelUrl()), ENT_QUOTES, 'UTF-8'); ?>" id="app-sidebar-backdrop" class="workspace-backdrop hidden" aria-label="Cerrar menú"></a>

        <div class="workspace-main">
            <header class="workspace-header">
                <div class="workspace-header-left">
                    <button id="mobile-menu-toggle" type="button" class="workspace-icon-button workspace-mobile-toggle" aria-label="Abrir menú lateral">
                        <span class="material-symbols-rounded">menu</span>
                    </button>

                    <a href="<?= htmlspecialchars(buildToolsPanelUrl((string) ($dashboardItem['id'] ?? 'inicio')), ENT_QUOTES, 'UTF-8'); ?>" class="workspace-logo-button" aria-label="Ir a inicio">
                        <img class="workspace-header-logo" src="img/logoAiScalerCenter.png" alt="AiScaler Center Logo">
                    </a>
                </div>

                <div class="workspace-search-shell" role="search" aria-label="Buscar en el panel">
                    <span class="material-symbols-rounded">search</span>
                    <input class="workspace-search-input" type="search" placeholder="Buscar en AiScaler" aria-label="Buscar en AiScaler">
                </div>

                <div class="workspace-user-menu">
                    <button id="user-menu-toggle" type="button" class="workspace-user-button" aria-haspopup="menu" aria-expanded="false">
                        <span class="workspace-user-name"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="material-symbols-rounded">expand_more</span>
                    </button>

                    <div id="user-menu-panel" class="workspace-user-panel hidden" role="menu">
                        <div class="workspace-user-panel-head">
                            <strong><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span><?= htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>

                        <div class="workspace-user-links">
                            <a href="<?= htmlspecialchars(buildToolsPanelUrl((string) ($accountItem['id'] ?? 'configuracion')), ENT_QUOTES, 'UTF-8'); ?>" class="workspace-user-link">
                                <span class="material-symbols-rounded">settings</span>
                                <span>Configuracion</span>
                            </a>

                            <a href="tool-logout.php" class="workspace-user-link workspace-user-link--danger">
                                <span class="material-symbols-rounded">logout</span>
                                <span>Cerrar sesion</span>
                            </a>

                            <a href="terminos-y-condiciones.php" class="workspace-user-link">
                                <span class="material-symbols-rounded">gavel</span>
                                <span>Terminos y condiciones</span>
                            </a>

                            <a href="aviso-de-privacidad.php" class="workspace-user-link">
                                <span class="material-symbols-rounded">policy</span>
                                <span>Aviso de privacidad</span>
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <main class="workspace-content">
                <section class="workspace-tool-content">
                    <?= $toolPageContent; ?>
                </section>
            </main>
        </div>
    </div>
    <script>
        (() => {
            const layout = document.getElementById('app-layout');
            const sidebar = document.getElementById('app-sidebar');
            const backdrop = document.getElementById('app-sidebar-backdrop');
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
            const userMenuToggle = document.getElementById('user-menu-toggle');
            const userMenuPanel = document.getElementById('user-menu-panel');
            let sidebarCollapsed = false;
            let sidebarOpen = false;
            let userMenuOpen = false;

            const applySidebarState = () => {
                const isDesktop = window.innerWidth >= 1024;
                layout?.classList.toggle('is-sidebar-collapsed', isDesktop && sidebarCollapsed);
                sidebar?.classList.toggle('is-open', !isDesktop && sidebarOpen);
                backdrop?.classList.toggle('hidden', isDesktop || !sidebarOpen);
            };

            const applyUserMenuState = () => {
                userMenuToggle?.setAttribute('aria-expanded', userMenuOpen ? 'true' : 'false');
                userMenuPanel?.classList.toggle('hidden', !userMenuOpen);
            };

            sidebarToggle?.addEventListener('click', () => {
                if (window.innerWidth >= 1024) {
                    sidebarCollapsed = !sidebarCollapsed;
                } else {
                    sidebarOpen = !sidebarOpen;
                }

                applySidebarState();
            });

            mobileMenuToggle?.addEventListener('click', () => {
                sidebarOpen = true;
                applySidebarState();
            });

            backdrop?.addEventListener('click', (event) => {
                event.preventDefault();
                sidebarOpen = false;
                applySidebarState();
            });

            userMenuToggle?.addEventListener('click', (event) => {
                event.stopPropagation();
                userMenuOpen = !userMenuOpen;
                applyUserMenuState();
            });

            document.addEventListener('click', (event) => {
                if (!userMenuOpen || event.target.closest('.workspace-user-menu')) {
                    return;
                }

                userMenuOpen = false;
                applyUserMenuState();
            });

            window.addEventListener('resize', applySidebarState);
            applySidebarState();
            applyUserMenuState();
        })();
    </script>
</body>
</html>
    <?php
    exit;
}

$supabaseConfig = require __DIR__ . '/config/supabase.php';
$supabaseProjectUrl = trim((string) ($supabaseConfig['project_url'] ?? ''));
$publishableKey = trim((string) ($supabaseConfig['publishable_key'] ?? ''));
$anonKey = trim((string) ($supabaseConfig['anon_key'] ?? ''));
$supabasePublicKey = $publishableKey !== '' && $publishableKey !== 'tu_publishable_key' ? $publishableKey : $anonKey;

$toolUrl = strtok($_SERVER['REQUEST_URI'] ?? '/tool.php', '?') ?: '/tool.php';
$loginUrl = 'index.php?view=login';
$appUrl = 'index.php?view=app';
$hasSupabaseConfig = $supabaseProjectUrl !== ''
    && $supabaseProjectUrl !== 'https://tu-project-ref.supabase.co'
    && $supabasePublicKey !== ''
    && $supabasePublicKey !== 'tu_publishable_key'
    && $supabasePublicKey !== 'tu_anon_key';

$authClientConfig = [
    'supabaseUrl' => $supabaseProjectUrl,
    'supabaseKey' => $supabasePublicKey,
    'landingUrl' => 'index.php',
    'loginUrl' => $loginUrl,
    'appUrl' => $appUrl,
    'hasSupabaseConfig' => $hasSupabaseConfig,
];

$toolRuntimePayload = [
    'slug' => (string) ($tool['slug'] ?? ''),
    'title' => (string) ($tool['title'] ?? ''),
    'description' => (string) ($tool['description'] ?? ''),
    'tutorial_youtube_url' => (string) ($tool['tutorial_youtube_url'] ?? ''),
    'panel_module_key' => (string) ($tool['panel_module_key'] ?? ''),
    'return_url' => (string) ($tool['return_url'] ?? buildToolsPanelUrl()),
    'launch_url' => $toolUrl . '?launch=' . rawurlencode($launchToken),
];
$panelModuleKey = (string) ($tool['panel_module_key'] ?? '');
$moduleStylesheet = match ($panelModuleKey) {
    'research_market_signals',
    'research_google',
    'research_youtube',
    'research_mercado_libre',
    'research_amazon' => 'css/modules/research.css',
    'social_post_scheduler' => 'css/modules/execute.css',
    default => null,
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string) ($tool['title'] ?? 'Herramienta'), ENT_QUOTES, 'UTF-8'); ?> - AiScaler Center</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,500,0,0">
    <?php if ($moduleStylesheet !== null): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($moduleStylesheet, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="css/tool-runtime.css">
    <script>
        window.AISCALER_AUTH_CONFIG = <?= json_encode($authClientConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        window.AISCALER_TOOL_PAYLOAD = <?= json_encode($toolRuntimePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    </script>
    <script type="module" src="js/tool-runtime.js"></script>
</head>
<body data-view="<?= $isEmbedMode ? 'tool-embed' : 'tool'; ?>" class="tool-runtime-body<?= $isEmbedMode ? ' tool-runtime-body--embed' : ''; ?>">
    <div class="tool-runtime-shell">
        <?php if (!$isEmbedMode): ?>
            <header class="tool-runtime-header">
                <div class="tool-runtime-header-copy">
                    <a class="tool-runtime-back" href="<?= htmlspecialchars((string) ($tool['return_url'] ?? buildToolsPanelUrl()), ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="material-symbols-rounded">arrow_back</span>
                        <span>Volver al panel</span>
                    </a>

                    <div>
                        <p class="tool-runtime-eyebrow">Herramienta</p>
                        <h1><?= htmlspecialchars((string) ($tool['title'] ?? 'Herramienta'), ENT_QUOTES, 'UTF-8'); ?></h1>
                        <p><?= htmlspecialchars((string) ($tool['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>

                <div class="tool-runtime-header-actions">
                    <?php if (trim((string) ($tool['tutorial_youtube_url'] ?? '')) !== ''): ?>
                        <a class="tool-runtime-tutorial" href="<?= htmlspecialchars((string) ($tool['tutorial_youtube_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noreferrer noopener">
                            <span class="material-symbols-rounded">smart_display</span>
                            <span>Ver tutorial</span>
                        </a>
                    <?php endif; ?>

                    <span id="tool-user-name" class="tool-runtime-user">Cargando usuario...</span>
                </div>
            </header>
        <?php endif; ?>
        <div id="tool-notice" class="tool-runtime-notice hidden"></div>
        <main id="tool-runtime-mount" class="tool-runtime-mount"></main>
    </div>
</body>
</html>
