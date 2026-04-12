<?php
declare(strict_types=1);

require_once __DIR__ . '/app_routing.php';

function pwaRootDir(): string
{
    return dirname(__DIR__);
}

function pwaAssetUrl(string $path): string
{
    $normalizedPath = ltrim($path, '/');
    $absolutePath = pwaRootDir() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);

    if (!is_file($absolutePath)) {
        return $normalizedPath;
    }

    return $normalizedPath . '?v=' . (string) filemtime($absolutePath);
}

function renderPwaHead(array $options = []): string
{
    $applicationName = trim((string) ($options['application_name'] ?? 'AiScaler Center'));
    $shortName = trim((string) ($options['short_name'] ?? 'AiScaler'));
    $themeColor = trim((string) ($options['theme_color'] ?? '#2f7cef'));
    $darkThemeColor = trim((string) ($options['dark_theme_color'] ?? '#0f141b'));
    $backgroundColor = trim((string) ($options['background_color'] ?? '#f5f7fb'));
    $description = trim((string) ($options['description'] ?? ''));
    $manifestHref = pwaAssetUrl('manifest.php');
    $faviconHref = pwaAssetUrl('img/pwa/favicon-32.png');
    $iconHref = pwaAssetUrl('img/pwa/icon-192.png');
    $appleTouchIconHref = pwaAssetUrl('img/pwa/apple-touch-icon.png');
    $registrationScriptHref = pwaAssetUrl('js/pwa-register.js');

    ob_start();
    ?>
<?php if ($description !== ''): ?>
    <meta name="description" content="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>">
<?php endif; ?>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= htmlspecialchars($faviconHref, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= htmlspecialchars($iconHref, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="theme-color" content="<?= htmlspecialchars($themeColor, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="theme-color" media="(prefers-color-scheme: dark)" content="<?= htmlspecialchars($darkThemeColor, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="msapplication-TileColor" content="<?= htmlspecialchars($backgroundColor, ENT_QUOTES, 'UTF-8'); ?>">
<?php if (appShouldEnablePwa()): ?>
    <link rel="manifest" href="<?= htmlspecialchars($manifestHref, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($appleTouchIconHref, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="application-name" content="<?= htmlspecialchars($applicationName, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars($shortName, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="mobile-web-app-capable" content="yes">
    <script type="module" src="<?= htmlspecialchars($registrationScriptHref, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endif; ?>
    <?php

    return (string) ob_get_clean();
}
