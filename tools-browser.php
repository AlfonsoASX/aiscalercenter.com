<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/supabase_api.php';
require_once __DIR__ . '/modules/tools/bootstrap.php';

ensureToolsSessionStarted();
cleanupExpiredToolLaunches();

$isPartial = trim((string) ($_GET['partial'] ?? '')) === '1';
$serverAuth = getToolsServerAuth();
$accessToken = trim((string) ($serverAuth['access_token'] ?? ''));
$userId = trim((string) ($serverAuth['user_id'] ?? ''));
$serverUser = [
    'email' => (string) ($serverAuth['email'] ?? ''),
];
$categoryKey = trim((string) ($_GET['category_key'] ?? ''));
$sectionId = trim((string) ($_GET['section_id'] ?? ''));
$projectId = trim((string) ($_GET['project_id'] ?? ''));
$projectName = trim((string) ($_GET['project_name'] ?? ''));
$projectLogoUrl = trim((string) ($_GET['project_logo_url'] ?? ''));
$openSlug = trim((string) ($_GET['open'] ?? ''));
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
        $categories = listToolCategories();
        $foundCategory = findToolCategory($categories, $categoryKey);

        if (is_array($foundCategory)) {
            $category = [
                'key' => (string) ($foundCategory['key'] ?? $categoryKey),
                'label' => (string) ($foundCategory['label'] ?? ucfirst($categoryKey)),
                'description' => (string) ($foundCategory['description'] ?? ''),
            ];
        }

        if ($openSlug !== '') {
            if (isRetiredToolSlug($openSlug)) {
                throw new RuntimeException('La herramienta solicitada ya no esta disponible en esta categoria.');
            }

            $tool = findAppToolBySlug($openSlug, $serverUser);

            if (!is_array($tool) || ($categoryKey !== 'all' && (string) ($tool['category_key'] ?? '') !== $categoryKey)) {
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

        $categoryMetaMap = resolveToolCategoryMetaMap();
        $tools = array_map(static function (array $tool) use ($categoryMetaMap): array {
            $toolCategoryKey = (string) ($tool['category_key'] ?? '');
            $categoryMeta = $categoryMetaMap[$toolCategoryKey] ?? null;
            $tool['category_label'] = (string) ($categoryMeta['label'] ?? humanizeToolCategoryKey($toolCategoryKey));
            $tool['category_color'] = (string) ($categoryMeta['color'] ?? '#5F6368');
            $tool['category_sort_order'] = (int) ($categoryMeta['sort_order'] ?? 1000);
            return sanitizeToolForCatalog($tool);
        }, listAppToolsByCategory($categoryKey, $serverUser));
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

        <?php if ($tools === []): ?>
            <div class="tools-catalog-grid">
                <div class="tools-catalog-empty tools-catalog-empty--full">
                    <span class="material-symbols-rounded">apps</span>
                    <h3>Aun no hay herramientas aqui</h3>
                    <p>Cuando exista un archivo <code>tool.php</code> activo dentro de una app de esta categoria, aparecera en esta vista.</p>
                </div>
            </div>
        <?php elseif ($categoryKey === 'all'): ?>
            <?= renderAllToolsCatalogGroups($tools, $categoryKey, $sectionId, $projectId, $projectName, $projectLogoUrl, $isPartial); ?>
        <?php else: ?>
            <div class="tools-catalog-grid">
                <?php foreach ($tools as $tool): ?>
                    <?= renderToolsCatalogCard($tool, $categoryKey, $sectionId, $projectId, $projectName, $projectLogoUrl, $isPartial, (string) ($category['label'] ?? 'Herramientas')); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php

    return (string) ob_get_clean();
}

function renderAllToolsCatalogGroups(
    array $tools,
    string $categoryKey,
    string $sectionId,
    string $projectId,
    string $projectName,
    string $projectLogoUrl,
    bool $isPartial
): string {
    $groups = [];

    foreach ($tools as $tool) {
        if (!is_array($tool)) {
            continue;
        }

        $groupKey = (string) ($tool['category_key'] ?? 'general');

        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                'key' => $groupKey,
                'label' => (string) ($tool['category_label'] ?? humanizeToolCategoryKey($groupKey)),
                'color' => (string) ($tool['category_color'] ?? '#5F6368'),
                'sort_order' => (int) ($tool['category_sort_order'] ?? 1000),
                'tools' => [],
            ];
        }

        $groups[$groupKey]['tools'][] = $tool;
    }

    usort($groups, static function (array $left, array $right): int {
        $leftOrder = (int) ($left['sort_order'] ?? 1000);
        $rightOrder = (int) ($right['sort_order'] ?? 1000);

        if ($leftOrder === $rightOrder) {
            return strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        }

        return $leftOrder <=> $rightOrder;
    });

    ob_start();
    ?>
    <div class="tools-catalog-group-stack">
        <?php foreach ($groups as $group): ?>
            <section class="tools-catalog-group">
                <header class="tools-catalog-group-head">
                    <h3><?= htmlspecialchars((string) ($group['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
                </header>

                <div class="tools-catalog-grid">
                    <?php foreach ((array) ($group['tools'] ?? []) as $tool): ?>
                        <?= renderToolsCatalogCard($tool, $categoryKey, $sectionId, $projectId, $projectName, $projectLogoUrl, $isPartial, (string) ($group['label'] ?? 'Herramientas')); ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
    <?php

    return (string) ob_get_clean();
}

function renderToolsCatalogCard(
    array $tool,
    string $categoryKey,
    string $sectionId,
    string $projectId,
    string $projectName,
    string $projectLogoUrl,
    bool $isPartial,
    string $fallbackCategoryLabel
): string {
    $slug = (string) ($tool['slug'] ?? '');
    $title = (string) ($tool['title'] ?? '');
    $description = (string) ($tool['description'] ?? '');
    $categoryLabel = (string) (($tool['category_label'] ?? '') !== '' ? $tool['category_label'] : $fallbackCategoryLabel);
    $categoryColor = trim((string) ($tool['category_color'] ?? '#5F6368'));
    $openUrl = buildToolCardUrl($categoryKey, $sectionId, $projectId, $projectName, $projectLogoUrl, $slug);

    ob_start();
    ?>
    <a
        class="tools-catalog-card-link"
        href="<?= htmlspecialchars($openUrl, ENT_QUOTES, 'UTF-8'); ?>"
        <?= $isPartial ? 'data-tools-open-slug="' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>
    >
        <article class="tools-catalog-card" style="--tools-category-color: <?= htmlspecialchars($categoryColor, ENT_QUOTES, 'UTF-8'); ?>;">
            <?= renderToolsCatalogMedia($tool); ?>

            <div class="tools-catalog-card-copy">
                <h3><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h3>
                <p class="tools-catalog-card-meta"><?= htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                <p><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </article>
    </a>
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

function resolveToolRoleFromEmail(string $email): string
{
    $panelConfig = require __DIR__ . '/config/panel.php';
    $bootstrapAdmins = array_map('strtolower', array_map('trim', (array) ($panelConfig['bootstrap_admins'] ?? [])));

    return in_array(strtolower(trim($email)), $bootstrapAdmins, true) ? 'admin' : 'regular';
}

function buildToolCardUrl(
    string $categoryKey,
    string $sectionId,
    string $projectId,
    string $projectName,
    string $projectLogoUrl,
    string $slug
): string {
    $query = http_build_query([
        'category_key' => $categoryKey,
        'section_id' => $sectionId,
        'project_id' => $projectId,
        'project_name' => $projectName,
        'project_logo_url' => $projectLogoUrl,
        'open' => $slug,
    ]);

    return 'tools-browser.php?' . $query;
}

function resolveToolCategoryMetaMap(): array
{
    $panelConfig = require __DIR__ . '/config/panel.php';
    $items = (array) ($panelConfig['menus']['regular'] ?? []);
    $map = [];

    foreach ($items as $index => $item) {
        if (!is_array($item)) {
            continue;
        }

        $key = trim((string) ($item['tool_category_key'] ?? ''));

        if ($key === '' || isset($map[$key])) {
            continue;
        }

        $map[$key] = [
            'label' => (string) ($item['section_title'] ?? $item['label'] ?? humanizeToolCategoryKey($key)),
            'color' => (string) ($item['color'] ?? '#5F6368'),
            'sort_order' => $index * 10,
        ];
    }

    foreach (listToolCategories() as $category) {
        if (!is_array($category)) {
            continue;
        }

        $key = (string) ($category['key'] ?? '');

        if ($key === '' || isset($map[$key])) {
            continue;
        }

        $map[$key] = [
            'label' => (string) ($category['label'] ?? humanizeToolCategoryKey($key)),
            'color' => '#5F6368',
            'sort_order' => (int) ($category['sort_order'] ?? 1000),
        ];
    }

    return $map;
}
