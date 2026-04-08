<?php
declare(strict_types=1);

use AiScaler\Tools\ToolRepository;

require_once __DIR__ . '/lib/supabase_api.php';
require_once __DIR__ . '/modules/tools/bootstrap.php';

ensureToolsSessionStarted();
cleanupExpiredToolLaunches();

$isPartial = trim((string) ($_GET['partial'] ?? '')) === '1';
$serverAuth = getToolsServerAuth();
$accessToken = trim((string) ($serverAuth['access_token'] ?? ''));
$userId = trim((string) ($serverAuth['user_id'] ?? ''));
$categoryKey = trim((string) ($_GET['category_key'] ?? ''));
$sectionId = trim((string) ($_GET['section_id'] ?? ''));
$projectId = trim((string) ($_GET['project_id'] ?? ''));
$projectName = trim((string) ($_GET['project_name'] ?? ''));
$projectLogoUrl = trim((string) ($_GET['project_logo_url'] ?? ''));
$openSlug = trim((string) ($_GET['open'] ?? ''));
$repository = new ToolRepository();
$errorMessage = '';
$category = [
    'key' => $categoryKey,
    'label' => ucfirst($categoryKey),
    'description' => '',
];
$tools = [];

if ($accessToken === '' || $userId === '') {
    $errorMessage = 'No encontramos la sesion del panel en PHP. Recarga el panel e intenta de nuevo.';
} elseif ($categoryKey === '') {
    $errorMessage = 'Selecciona una categoria valida de herramientas.';
} else {
    try {
        $categories = $repository->listCategories($accessToken);
        $foundCategory = findToolCategory($categories, $categoryKey);

        if (is_array($foundCategory)) {
            $category = [
                'key' => (string) ($foundCategory['key'] ?? $categoryKey),
                'label' => (string) ($foundCategory['label'] ?? ucfirst($categoryKey)),
                'description' => (string) ($foundCategory['description'] ?? ''),
            ];
        }

        if ($openSlug !== '') {
            $tool = $repository->findBySlug($accessToken, $openSlug);

            if (!is_array($tool)) {
                $builtinTool = findBuiltinToolBySlug($openSlug);
                $tool = is_array($builtinTool) ? sanitizeToolForCatalog($builtinTool) : null;
            }

            if (!is_array($tool) || (string) ($tool['category_key'] ?? '') !== $categoryKey) {
                throw new RuntimeException('La herramienta solicitada ya no esta disponible en esta categoria.');
            }

            $privateConfig = getToolLaunchConfig($openSlug);

            if (!is_array($privateConfig)) {
                throw new RuntimeException('La herramienta no tiene configurada su ruta protegida en PHP.');
            }

            $launchToken = bin2hex(random_bytes(24));

            rememberToolLaunch($launchToken, [
                'tool' => sanitizeToolForLaunch(
                    mergeToolWithPrivateConfig($tool, $privateConfig),
                    buildToolsPanelUrl($sectionId)
                ),
                'user_id' => $userId,
                'access_token' => $accessToken,
                'project' => [
                    'id' => $projectId,
                    'name' => $projectName,
                    'logo_url' => $projectLogoUrl,
                ],
                'user' => [
                    'email' => (string) ($serverAuth['email'] ?? ''),
                    'display_name' => (string) ($serverAuth['email'] ?? 'Usuario'),
                    'role' => resolveToolRoleFromEmail((string) ($serverAuth['email'] ?? '')),
                ],
                'created_at' => time(),
            ]);

            header('Location: ' . buildToolsLaunchUrl($launchToken), true, 302);
            exit;
        }

        $tools = array_map('sanitizeToolForCatalog', $repository->listTools($accessToken, $categoryKey));
        $tools = mergeCatalogToolsWithBuiltins($tools, $categoryKey);
    } catch (Throwable $exception) {
        $errorMessage = normalizeToolsExceptionMessage($exception);
        $tools = [];
    }
}

$fragment = renderToolsCatalogFragment($tools, $errorMessage, $category, $categoryKey, $sectionId, $projectId, $projectName, $projectLogoUrl, $isPartial);

if ($isPartial) {
    header('Content-Type: text/html; charset=UTF-8');
    echo $fragment;
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string) ($category['label'] ?? 'Herramientas'), ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,500,0,0">
    <link rel="stylesheet" href="css/modules/tools-catalog.css">
    <style>
        body {
            margin: 0;
            padding: 2rem;
            font-family: Roboto, sans-serif;
            background: #f6f8fc;
            color: #202124;
        }

        .tools-browser-page {
            max-width: 1200px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="tools-browser-page">
        <?= $fragment; ?>
    </div>
</body>
</html>
<?php

function renderToolsCatalogFragment(
    array $tools,
    string $errorMessage,
    array $category,
    string $categoryKey,
    string $sectionId,
    string $projectId,
    string $projectName,
    string $projectLogoUrl,
    bool $isPartial
): string {
    ob_start();
    ?>
    <div class="tools-catalog-browser-shell">
        <?php if ($errorMessage !== ''): ?>
            <div class="tools-catalog-notice tools-catalog-notice--error">
                <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="tools-catalog-grid">
            <?php if ($tools === []): ?>
                <div class="tools-catalog-empty tools-catalog-empty--full">
                    <span class="material-symbols-rounded">apps</span>
                    <h3>Aun no hay herramientas aqui</h3>
                    <p>Cuando un administrador agregue herramientas para esta categoria, apareceran en esta vista.</p>
                </div>
            <?php else: ?>
                <?php foreach ($tools as $tool): ?>
                    <article class="tools-catalog-card">
                        <?= renderToolsCatalogMedia($tool); ?>

                        <div class="tools-catalog-card-copy">
                            <span class="tools-catalog-eyebrow">
                                <?= htmlspecialchars((string) ($category['label'] ?? 'Herramientas'), ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <h3><?= htmlspecialchars((string) ($tool['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p><?= htmlspecialchars((string) ($tool['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>

                        <div class="tools-catalog-card-actions">
                            <?php if ($isPartial): ?>
                                <button
                                    type="button"
                                    class="tools-catalog-primary-button"
                                    data-tools-open-slug="<?= htmlspecialchars((string) ($tool['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                >
                                    <span class="material-symbols-rounded">rocket_launch</span>
                                    <span>Abrir herramienta</span>
                                </button>
                            <?php else: ?>
                                <form method="get" action="tools-browser.php">
                                    <input type="hidden" name="category_key" value="<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="section_id" value="<?= htmlspecialchars($sectionId, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="project_id" value="<?= htmlspecialchars($projectId, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="project_name" value="<?= htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="project_logo_url" value="<?= htmlspecialchars($projectLogoUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="open" value="<?= htmlspecialchars((string) ($tool['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    <button type="submit" class="tools-catalog-primary-button">
                                        <span class="material-symbols-rounded">rocket_launch</span>
                                        <span>Abrir herramienta</span>
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (trim((string) ($tool['tutorial_youtube_url'] ?? '')) !== ''): ?>
                                <a
                                    class="tools-catalog-secondary-button"
                                    href="<?= htmlspecialchars((string) ($tool['tutorial_youtube_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    target="_blank"
                                    rel="noreferrer noopener"
                                >
                                    <span class="material-symbols-rounded">smart_display</span>
                                    <span>Ver tutorial</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php

    return (string) ob_get_clean();
}

function renderToolsCatalogMedia(array $tool): string
{
    $imageUrl = trim((string) ($tool['image_url'] ?? ''));
    $title = trim((string) ($tool['title'] ?? 'Herramienta'));

    if ($imageUrl === '') {
        return '
            <div class="tools-catalog-card-media tools-catalog-card-media--empty" aria-hidden="true">
                <span class="material-symbols-rounded">auto_awesome</span>
            </div>
        ';
    }

    return sprintf(
        '<div class="tools-catalog-card-media"><img src="%s" alt="%s" loading="lazy"></div>',
        htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
    );
}

function mergeCatalogToolsWithBuiltins(array $tools, string $categoryKey): array
{
    $builtins = listBuiltinToolsByCategory($categoryKey);

    if ($builtins === []) {
        return $tools;
    }

    $indexed = [];

    foreach ($tools as $tool) {
        if (!is_array($tool)) {
            continue;
        }

        $indexed[(string) ($tool['slug'] ?? '')] = $tool;
    }

    unset($indexed['validar-mercado']);

    foreach ($builtins as $builtin) {
        $slug = (string) ($builtin['slug'] ?? '');

        if ($slug !== '' && !isset($indexed[$slug])) {
            $indexed[$slug] = sanitizeToolForCatalog($builtin);
        }
    }

    $merged = array_values($indexed);

    usort($merged, static function (array $left, array $right): int {
        $leftOrder = (int) ($left['sort_order'] ?? 0);
        $rightOrder = (int) ($right['sort_order'] ?? 0);

        if ($leftOrder === $rightOrder) {
            return strcmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        }

        return $leftOrder <=> $rightOrder;
    });

    return $merged;
}

function resolveToolRoleFromEmail(string $email): string
{
    $panelConfig = require __DIR__ . '/config/panel.php';
    $bootstrapAdmins = array_map('strtolower', array_map('trim', (array) ($panelConfig['bootstrap_admins'] ?? [])));

    return in_array(strtolower(trim($email)), $bootstrapAdmins, true) ? 'admin' : 'regular';
}
