<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/pwa.php';

$supabaseConfig = require __DIR__ . '/config/supabase.php';
$panelConfig = require __DIR__ . '/config/panel.php';

$supabaseProjectUrl = trim((string) ($supabaseConfig['project_url'] ?? ''));
$publishableKey = trim((string) ($supabaseConfig['publishable_key'] ?? ''));
$anonKey = trim((string) ($supabaseConfig['anon_key'] ?? ''));
$supabasePublicKey = $publishableKey !== '' && $publishableKey !== 'tu_publishable_key' ? $publishableKey : $anonKey;

$view = (string) ($_GET['view'] ?? '');

if ($view === '') {
    $requestPath = appCurrentRequestPath();

    if ($requestPath === '/login') {
        $view = 'login';
    } elseif ($requestPath === '/app') {
        $view = 'app';
    }
}

$redirectUrl = appHomeUrl();
$loginUrl = appLoginUrl();
$appUrl = appPanelUrl();
$showLoginView = $view === 'login';
$showAppView = $view === 'app';
$hasSupabaseConfig = $supabaseProjectUrl !== ''
    && $supabaseProjectUrl !== 'https://tu-project-ref.supabase.co'
    && $supabasePublicKey !== ''
    && $supabasePublicKey !== 'tu_publishable_key'
    && $supabasePublicKey !== 'tu_anon_key';

$authClientConfig = [
    'supabaseUrl' => $supabaseProjectUrl,
    'supabaseKey' => $supabasePublicKey,
    'landingUrl' => $redirectUrl,
    'loginUrl' => $loginUrl,
    'appUrl' => $appUrl,
    'hasSupabaseConfig' => $hasSupabaseConfig,
    'panel' => $panelConfig,
];

function appAssetUrl(string $path): string
{
    $normalizedPath = ltrim($path, '/');
    $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);

    if (!is_file($absolutePath)) {
        return $normalizedPath;
    }

    return $normalizedPath . '?v=' . (string) filemtime($absolutePath);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $showAppView ? 'AiScaler Center - Panel de control' : ($showLoginView ? 'AiScaler Center - Acceso' : 'AiScaler Center - Transforma tu Futuro Profesional'); ?></title>
    <?= renderPwaHead([
        'description' => 'Plataforma de AiScaler Center para aprender, ejecutar proyectos y operar herramientas de IA desde cualquier dispositivo.',
        'background_color' => '#f5f7fb',
    ]); ?>

    <script src="https://cdn.tailwindcss.com"></script>

    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;800&family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,500,0,0">
    <link rel="stylesheet" href="<?= htmlspecialchars(appAssetUrl('css/modules/blog-entries.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(appAssetUrl('css/modules/connect.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(appAssetUrl('css/modules/courses.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(appAssetUrl('css/modules/execute.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(appAssetUrl('css/modules/learn.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(appAssetUrl('css/modules/project-settings.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(appAssetUrl('css/modules/projects.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(appAssetUrl('css/modules/research.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(appAssetUrl('css/modules/tools-catalog.css'), ENT_QUOTES, 'UTF-8'); ?>">

    <style>
        :root {
            --md-primary: #2f7cef;
            --md-primary-strong: #1f5fd6;
            --md-primary-container: #dce8ff;
            --md-secondary: #163b7a;
            --md-tertiary: #0f9d58;
            --md-error: #ea4335;
            --md-warning: #fbbc04;
            --md-surface: #f5f7fb;
            --md-surface-elevated: rgba(255, 255, 255, 0.82);
            --md-surface-container: #ffffff;
            --md-surface-container-low: #eef3ff;
            --md-surface-container-high: #e7edf8;
            --md-outline: rgba(36, 52, 71, 0.12);
            --md-outline-strong: rgba(36, 52, 71, 0.22);
            --md-shadow: 0 24px 60px rgba(24, 39, 75, 0.08);
            --md-text: #202124;
            --md-text-muted: #5f6368;
            --md-text-subtle: #738093;
            --md-inverse: #101418;
            --workspace-accent: #2f7cef;
            --workspace-accent-rgb: 47, 124, 239;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --md-primary: #aac7ff;
                --md-primary-strong: #6ea4ff;
                --md-primary-container: rgba(47, 124, 239, 0.18);
                --md-secondary: #c4d7ff;
                --md-tertiary: #8de0b4;
                --md-error: #ffb4ab;
                --md-warning: #ffd45a;
                --md-surface: #0f141b;
                --md-surface-elevated: rgba(22, 28, 36, 0.84);
                --md-surface-container: #151c25;
                --md-surface-container-low: #18212d;
                --md-surface-container-high: #1f2935;
                --md-outline: rgba(213, 221, 235, 0.12);
                --md-outline-strong: rgba(213, 221, 235, 0.24);
                --md-shadow: 0 24px 80px rgba(0, 0, 0, 0.36);
                --md-text: #f3f6fb;
                --md-text-muted: #c4ccd8;
                --md-text-subtle: #9aa4b2;
                --md-inverse: #ffffff;
            }
        }

        body { font-family: 'Open Sans', sans-serif; }
        h1, h2, h3, h4, .btn-font { font-family: 'Montserrat', sans-serif; }

        .bg-brand-blue { background-color: #2F7CEF; }
        .text-brand-blue { color: #2F7CEF; }
        .bg-brand-dark { background-color: #0F172A; }
        .bg-brand-amber { background-color: #FBBF24; }

        .hero-bg {
            background-image: linear-gradient(rgba(15, 23, 42, 0.85), rgba(15, 23, 42, 0.8)), url('https://images.unsplash.com/photo-1519389950473-47ba0277781c?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80');
            background-size: cover;
            background-position: center;
        }

        .platform-shell {
            background:
                radial-gradient(circle at top left, rgba(47, 124, 239, 0.3), transparent 30%),
                radial-gradient(circle at bottom right, rgba(14, 165, 233, 0.18), transparent 26%),
                #020617;
        }

        .platform-panel {
            background: rgba(15, 23, 42, 0.72);
            backdrop-filter: blur(18px);
        }

        .platform-grid {
            background:
                linear-gradient(rgba(15, 23, 42, 0.82), rgba(15, 23, 42, 0.92)),
                linear-gradient(90deg, rgba(148, 163, 184, 0.08) 1px, transparent 1px),
                linear-gradient(rgba(148, 163, 184, 0.08) 1px, transparent 1px);
            background-size: auto, 40px 40px, 40px 40px;
        }

        .material-symbols-rounded {
            font-variation-settings:
                'FILL' 0,
                'wght' 500,
                'GRAD' 0,
                'opsz' 24;
            font-size: 1.25rem;
            line-height: 1;
        }

        [data-view="app"] {
            font-family: 'Roboto', 'Open Sans', sans-serif;
            background:
                radial-gradient(circle at top left, rgba(47, 124, 239, 0.14), transparent 32%),
                radial-gradient(circle at top right, rgba(234, 67, 53, 0.09), transparent 22%),
                radial-gradient(circle at bottom left, rgba(251, 188, 4, 0.1), transparent 24%),
                linear-gradient(180deg, rgba(255, 255, 255, 0.85), rgba(241, 245, 251, 0.98)),
                var(--md-surface);
            color: var(--md-text);
        }

        [data-view="app"] h1,
        [data-view="app"] h2,
        [data-view="app"] h3,
        [data-view="app"] h4,
        [data-view="app"] .btn-font {
            font-family: 'Roboto', sans-serif;
        }

        .md3-shell {
            min-height: 100vh;
            padding: 1rem;
        }

        .md3-layout {
            margin: 0 auto;
            display: flex;
            gap: 1.25rem;
            max-width: 1680px;
        }

        .md3-rail {
            display: none;
            width: 112px;
            flex-shrink: 0;
            flex-direction: column;
            justify-content: space-between;
            padding: 1.25rem 0.875rem 1.1rem;
            border-radius: 2rem;
            border: 1px solid var(--md-outline);
            background: var(--md-surface-elevated);
            backdrop-filter: blur(22px);
            box-shadow: var(--md-shadow);
            position: sticky;
            top: 1rem;
            max-height: calc(100vh - 2rem);
        }

        .md3-rail-brand {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.875rem;
        }

        .md3-rail-logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 4rem;
            height: 4rem;
            border-radius: 1.5rem;
            background: linear-gradient(145deg, rgba(47, 124, 239, 0.18), rgba(47, 124, 239, 0.08));
            border: 1px solid rgba(47, 124, 239, 0.12);
        }

        .md3-rail-logo img {
            width: 2.5rem;
            height: auto;
        }

        .md3-brand-copy {
            text-align: center;
        }

        .md3-brand-copy strong {
            display: block;
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--md-text);
        }

        .md3-brand-copy span {
            display: block;
            margin-top: 0.2rem;
            font-size: 0.72rem;
            color: var(--md-text-muted);
        }

        .md3-rail-nav,
        .md3-bottom-nav {
            display: grid;
            gap: 0.55rem;
        }

        .md3-rail-nav {
            margin-top: 1.5rem;
            flex: 1;
            align-content: start;
        }

        .md3-nav-button {
            border: 0;
            background: transparent;
            color: var(--md-text-muted);
            border-radius: 1.6rem;
            transition: background-color 180ms ease, color 180ms ease, transform 180ms ease, box-shadow 180ms ease;
            cursor: pointer;
        }

        .md3-nav-button:focus-visible,
        .md3-chip-button:focus-visible,
        .md3-fab:focus-visible,
        .md3-icon-button:focus-visible {
            outline: 3px solid rgba(47, 124, 239, 0.35);
            outline-offset: 2px;
        }

        .md3-rail-button {
            width: 100%;
            padding: 0.75rem 0.35rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.45rem;
        }

        .md3-rail-button .material-symbols-rounded,
        .md3-bottom-button .material-symbols-rounded,
        .md3-fab .material-symbols-rounded,
        .md3-icon-button .material-symbols-rounded {
            font-size: 1.35rem;
        }

        .md3-nav-label {
            font-size: 0.72rem;
            line-height: 1.2;
            font-weight: 600;
            text-align: center;
        }

        .md3-nav-button.is-active {
            color: var(--md-primary-strong);
            background: var(--md-primary-container);
            box-shadow: inset 0 0 0 1px rgba(47, 124, 239, 0.12);
            transform: translateY(-1px);
        }

        .md3-rail-footer {
            margin-top: 1rem;
            padding: 0.9rem 0.85rem;
            border-radius: 1.6rem;
            background: var(--md-surface-container-low);
            border: 1px solid var(--md-outline);
            text-align: center;
        }

        .md3-rail-footer p {
            font-size: 0.72rem;
            line-height: 1.45;
            color: var(--md-text-subtle);
        }

        .md3-main {
            min-width: 0;
            flex: 1;
        }

        .md3-topbar {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            padding: 1rem 1.15rem;
            border-radius: 2rem;
            border: 1px solid var(--md-outline);
            background: var(--md-surface-elevated);
            backdrop-filter: blur(22px);
            box-shadow: var(--md-shadow);
        }

        .md3-topbar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .md3-topbar-copy small,
        .md3-section-label,
        .md3-card-label {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--md-text-subtle);
        }

        .md3-topbar-copy h1 {
            margin-top: 0.28rem;
            font-size: clamp(1.6rem, 3vw, 2rem);
            font-weight: 700;
            letter-spacing: -0.03em;
            color: var(--md-text);
        }

        .md3-search {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            min-height: 3.5rem;
            width: 100%;
            padding: 0.95rem 1.05rem;
            border-radius: 999px;
            background: var(--md-surface-container-low);
            border: 1px solid var(--md-outline);
            color: var(--md-text-muted);
        }

        .md3-search strong {
            display: block;
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--md-text);
        }

        .md3-search span:last-child {
            font-size: 0.82rem;
            color: var(--md-text-subtle);
        }

        .md3-top-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        .md3-chip-button,
        .md3-icon-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            min-height: 2.85rem;
            padding: 0 1rem;
            border: 1px solid var(--md-outline);
            border-radius: 999px;
            background: var(--md-surface-container);
            color: var(--md-text);
            transition: background-color 180ms ease, border-color 180ms ease, transform 180ms ease;
        }

        .md3-chip-button:hover,
        .md3-icon-button:hover,
        .md3-nav-button:hover,
        .md3-fab:hover {
            transform: translateY(-1px);
        }

        .md3-icon-button {
            padding: 0 0.95rem;
            font-weight: 600;
        }

        .md3-avatar {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 3rem;
            height: 3rem;
            border-radius: 1rem;
            background: linear-gradient(145deg, rgba(47, 124, 239, 0.15), rgba(22, 59, 122, 0.08));
            border: 1px solid var(--md-outline);
            overflow: hidden;
            color: var(--md-primary-strong);
            font-weight: 700;
        }

        .md3-avatar img {
            width: 2rem;
            height: auto;
        }

        .md3-content {
            margin-top: 1.25rem;
            padding-bottom: 7rem;
        }

        .md3-notice {
            border-radius: 1.35rem;
        }

        .md3-loading,
        .md3-card {
            border: 1px solid var(--md-outline);
            border-radius: 2rem;
            background: var(--md-surface-elevated);
            backdrop-filter: blur(18px);
            box-shadow: var(--md-shadow);
        }

        .md3-loading {
            min-height: 22rem;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2.5rem;
            color: var(--md-text);
        }

        .md3-loading p {
            color: var(--md-text-muted);
        }

        .md3-shell-grid {
            display: grid;
            gap: 1.25rem;
        }

        .md3-hero-card {
            padding: clamp(1.35rem, 2vw, 2rem);
            position: relative;
            overflow: hidden;
        }

        .md3-hero-card::before {
            content: '';
            position: absolute;
            inset: auto -4rem -5rem auto;
            width: 18rem;
            height: 18rem;
            border-radius: 999px;
            background: radial-gradient(circle, rgba(47, 124, 239, 0.18), transparent 62%);
            pointer-events: none;
        }

        .md3-hero-layout {
            display: grid;
            gap: 1.25rem;
            align-items: start;
        }

        .md3-hero-copy h2 {
            margin-top: 0.65rem;
            font-size: clamp(2rem, 4vw, 3.35rem);
            line-height: 1;
            font-weight: 700;
            letter-spacing: -0.045em;
            color: var(--md-text);
        }

        .md3-hero-copy p {
            margin-top: 0.95rem;
            max-width: 58rem;
            font-size: 1rem;
            line-height: 1.8;
            color: var(--md-text-muted);
        }

        .md3-chip-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1.35rem;
        }

        .md3-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            min-height: 2.4rem;
            padding: 0.55rem 0.9rem;
            border-radius: 999px;
            background: var(--md-surface-container-low);
            border: 1px solid var(--md-outline);
            color: var(--md-text);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .md3-chip--primary {
            background: var(--md-primary-container);
            color: var(--md-primary-strong);
            border-color: rgba(47, 124, 239, 0.18);
        }

        .md3-chip--accent-red {
            background: rgba(234, 67, 53, 0.12);
            color: #b3261e;
            border-color: rgba(234, 67, 53, 0.18);
        }

        .md3-chip--accent-yellow {
            background: rgba(251, 188, 4, 0.16);
            color: #835b00;
            border-color: rgba(251, 188, 4, 0.24);
        }

        .md3-chip--accent-green {
            background: rgba(52, 168, 83, 0.15);
            color: #0c6a33;
            border-color: rgba(52, 168, 83, 0.22);
        }

        @media (prefers-color-scheme: dark) {
            .md3-chip--accent-red { color: #ffcdc7; }
            .md3-chip--accent-yellow { color: #ffe28b; }
            .md3-chip--accent-green { color: #b8f0ce; }
        }

        .md3-main-grid {
            display: grid;
            gap: 1.25rem;
        }

        .md3-module-card,
        .md3-side-card {
            padding: 1.4rem;
        }

        .md3-section-header {
            display: flex;
            align-items: center;
            gap: 0.95rem;
        }

        .md3-section-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 3.25rem;
            height: 3.25rem;
            border-radius: 1.15rem;
            background: var(--md-surface-container-high);
            color: var(--md-primary-strong);
        }

        .md3-section-header h3 {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--md-text);
        }

        .md3-section-header p,
        .md3-card-description,
        .md3-side-card p {
            margin-top: 0.18rem;
            color: var(--md-text-muted);
            line-height: 1.75;
        }

        .md3-feature-grid {
            display: grid;
            gap: 1rem;
            margin-top: 1.4rem;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }

        .md3-feature-card {
            padding: 1rem;
            border-radius: 1.55rem;
            border: 1px solid var(--md-outline);
            background: var(--md-surface-container-low);
        }

        .md3-feature-card strong {
            display: block;
            font-size: 0.98rem;
            font-weight: 700;
            color: var(--md-text);
        }

        .md3-feature-card p {
            margin-top: 0.6rem;
            font-size: 0.9rem;
            line-height: 1.65;
            color: var(--md-text-muted);
        }

        .md3-side-stack {
            display: grid;
            gap: 1.25rem;
        }

        .md3-info-grid {
            display: grid;
            gap: 0.85rem;
            margin-top: 1rem;
        }

        .md3-info-item {
            padding: 0.95rem 1rem;
            border-radius: 1.4rem;
            border: 1px solid var(--md-outline);
            background: var(--md-surface-container-low);
        }

        .md3-info-item small {
            display: block;
            font-size: 0.74rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--md-text-subtle);
        }

        .md3-info-item strong {
            display: block;
            margin-top: 0.4rem;
            font-size: 0.98rem;
            font-weight: 600;
            color: var(--md-text);
        }

        .md3-highlight-card {
            margin-top: 1.1rem;
            padding: 1rem;
            border-radius: 1.55rem;
            border: 1px solid rgba(47, 124, 239, 0.14);
            background: linear-gradient(145deg, rgba(47, 124, 239, 0.1), rgba(255, 255, 255, 0.72));
        }

        @media (prefers-color-scheme: dark) {
            .md3-highlight-card {
                background: linear-gradient(145deg, rgba(47, 124, 239, 0.16), rgba(21, 28, 37, 0.8));
            }
        }

        .md3-bottom-nav {
            position: fixed;
            left: 1rem;
            right: 1rem;
            bottom: 1rem;
            z-index: 40;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 0.45rem;
            padding: 0.45rem;
            border-radius: 1.85rem;
            border: 1px solid var(--md-outline);
            background: var(--md-surface-elevated);
            backdrop-filter: blur(22px);
            box-shadow: var(--md-shadow);
        }

        .md3-bottom-button {
            display: flex;
            min-width: 0;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.32rem;
            padding: 0.75rem 0.4rem;
        }

        .md3-bottom-button .md3-nav-label {
            font-size: 0.68rem;
        }

        .md3-fab {
            position: fixed;
            right: 1rem;
            bottom: 6.25rem;
            z-index: 50;
            display: inline-flex;
            align-items: center;
            gap: 0.7rem;
            min-height: 3.5rem;
            padding: 0 1.25rem;
            border: 0;
            border-radius: 1.4rem;
            background: linear-gradient(135deg, var(--md-primary), var(--md-primary-strong));
            color: #ffffff;
            box-shadow: 0 16px 40px rgba(47, 124, 239, 0.28);
            font-weight: 700;
            letter-spacing: 0.01em;
        }

        .md3-fab-label {
            display: none;
            font-size: 0.94rem;
        }

        @media (min-width: 640px) {
            .md3-fab-label {
                display: inline;
            }

            .md3-topbar {
                padding: 1.05rem 1.35rem;
            }
        }

        @media (min-width: 1024px) {
            .md3-shell {
                padding: 1.25rem;
            }

            .md3-layout {
                align-items: flex-start;
            }

            .md3-rail {
                display: flex;
            }

            .md3-content {
                padding-bottom: 3rem;
            }

            .md3-topbar {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
            }

            .md3-topbar-header {
                flex: 0 0 auto;
            }

            .md3-search {
                max-width: 34rem;
                flex: 1;
            }

            .md3-main-grid {
                grid-template-columns: minmax(0, 1.45fr) minmax(320px, 0.9fr);
                align-items: start;
            }

            .md3-hero-layout {
                grid-template-columns: minmax(0, 1.25fr) minmax(280px, 0.75fr);
                align-items: start;
            }

            .md3-bottom-nav {
                display: none;
            }

            .md3-fab {
                right: 2rem;
                bottom: 2rem;
            }
        }

        .workspace-app {
            min-height: 100vh;
            display: flex;
            background: #ffffff;
            color: var(--md-text);
        }

        .workspace-sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 50;
            width: 19.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            padding: 0.75rem;
            background: #eef3fb;
            border-right: 1px solid rgba(19, 42, 74, 0.08);
            transform: translateX(-100%);
            transition: width 180ms ease, transform 180ms ease;
        }

        .workspace-sidebar.is-open {
            transform: translateX(0);
        }

        .workspace-sidebar-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            padding: 0 0.25rem 0.5rem;
        }

        .workspace-sidebar-project {
            display: grid;
            justify-items: center;
            gap: 0.55rem;
            min-width: 0;
            padding: 0.15rem 0.35rem 0.45rem;
            background: transparent;
            cursor: default;
        }

        .workspace-sidebar-project.hidden {
            display: none;
        }

        .workspace-sidebar-project-logo {
            width: 4rem;
            height: 4rem;
            flex: 0 0 auto;
            display: grid;
            place-items: center;
            overflow: hidden;
            border-radius: 999px;
            background: #ffffff;
            color: #1f5fd6;
            font-size: 1.2rem;
            font-weight: 800;
            box-shadow:
                inset 0 0 0 1px rgba(19, 42, 74, 0.08),
                0 10px 24px rgba(19, 42, 74, 0.08);
        }

        .workspace-sidebar-project-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 0.24rem;
        }

        .workspace-sidebar-project-copy {
            width: 100%;
            min-width: 0;
        }

        .workspace-sidebar-project-copy strong {
            display: -webkit-box;
            overflow: hidden;
            color: #202124;
            font-size: 0.88rem;
            font-weight: 700;
            line-height: 1.3;
            text-align: center;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
        }

        .workspace-icon-button,
        .workspace-logout-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            min-height: 2.9rem;
            border: 0;
            background: transparent;
            color: #3c4043;
            transition: background-color 160ms ease, color 160ms ease;
        }

        .workspace-icon-button {
            width: 2.9rem;
            border-radius: 999px;
        }

        .workspace-icon-button:hover,
        .workspace-logout-button:hover {
            background: rgba(47, 124, 239, 0.08);
            color: #163b7a;
        }

        .workspace-icon-button.is-active {
            background: #dbe8ff;
            color: #1f5fd6;
        }

        .workspace-icon-button:focus-visible,
        .workspace-logout-button:focus-visible,
        .workspace-nav-button:focus-visible {
            outline: 3px solid rgba(47, 124, 239, 0.28);
            outline-offset: 2px;
        }

        .workspace-nav {
            display: grid;
            gap: 0.12rem;
        }

        .workspace-nav-button {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 0.95rem;
            padding: 0.68rem 0.9rem;
            border: 0;
            border-radius: 1rem;
            background: transparent;
            color: #3c4043;
            text-align: left;
            cursor: pointer;
            transition: background-color 160ms ease, color 160ms ease;
        }

        .workspace-nav-button:hover {
            background: rgba(47, 124, 239, 0.08);
            color: #163b7a;
        }

        .workspace-nav-button.is-active {
            background: #dbe8ff;
            color: #1f5fd6;
            font-weight: 600;
        }

        .workspace-nav-icon {
            width: 2.75rem;
            height: 2.75rem;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.66);
        }

        .workspace-nav-icon img {
            width: 1.35rem;
            height: 1.35rem;
            object-fit: contain;
        }

        .workspace-nav-copy {
            position: relative;
            display: block;
            flex: 1;
            min-height: 1.5rem;
            overflow: hidden;
        }

        .workspace-nav-text {
            position: absolute;
            inset: 0 auto auto 0;
            white-space: nowrap;
            font-size: 0.98rem;
            line-height: 1.5rem;
            transition: opacity 160ms ease, transform 160ms ease;
        }

        .workspace-nav-text--default {
            opacity: 1;
            transform: translateY(0);
        }

        .workspace-nav-text--hover {
            opacity: 0;
            transform: translateY(0.45rem);
        }

        .workspace-nav-button:hover .workspace-nav-text--default,
        .workspace-nav-button:focus-visible .workspace-nav-text--default {
            opacity: 0;
            transform: translateY(-0.45rem);
        }

        .workspace-nav-button:hover .workspace-nav-text--hover,
        .workspace-nav-button:focus-visible .workspace-nav-text--hover {
            opacity: 1;
            transform: translateY(0);
        }

        .workspace-main {
            min-width: 0;
            flex: 1;
            display: flex;
            flex-direction: column;
            background:
                radial-gradient(circle at top right, rgba(var(--workspace-accent-rgb), 0.18), transparent 28rem),
                linear-gradient(180deg, rgba(var(--workspace-accent-rgb), 0.11), rgba(var(--workspace-accent-rgb), 0.03) 24%, #f8fafc 100%);
            transition: background 220ms ease;
        }

        .workspace-app.is-home-screen .workspace-sidebar {
            display: none;
        }

        .workspace-app.is-home-screen .workspace-main {
            width: 100%;
        }

        .workspace-app.is-home-screen .workspace-mobile-toggle {
            display: none;
        }

        .workspace-header {
            position: sticky;
            top: 0;
            z-index: 20;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.25rem;
            background: rgba(255, 255, 255, 0.92);
            border-bottom: 1px solid rgba(19, 42, 74, 0.08);
            backdrop-filter: blur(14px);
        }

        .workspace-header-left {
            display: flex;
            align-items: center;
            gap: 0.9rem;
            min-width: 0;
            flex-shrink: 0;
        }

        .workspace-logo-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border: 0;
            background: transparent;
            cursor: pointer;
        }

        .workspace-logo-button:focus-visible {
            outline: 3px solid rgba(47, 124, 239, 0.28);
            outline-offset: 4px;
            border-radius: 0.75rem;
        }

        .workspace-header-logo {
            width: auto;
            height: 2.6rem;
            object-fit: contain;
        }

        .workspace-header-copy {
            min-width: 0;
        }

        .workspace-search-shell {
            flex: 1;
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 0.7rem;
            min-height: 3rem;
            padding: 0 1rem;
            border: 1px solid rgba(19, 42, 74, 0.08);
            border-radius: 999px;
            background: #f8fafc;
            color: #6b7280;
        }

        .workspace-search-shell .material-symbols-rounded {
            flex-shrink: 0;
        }

        .workspace-search-input {
            width: 100%;
            border: 0;
            background: transparent;
            color: #202124;
        }

        .workspace-search-input:focus {
            outline: none;
        }

        .workspace-search-input::placeholder {
            color: #9ca3af;
        }

        .workspace-mobile-toggle {
            flex-shrink: 0;
        }

        .workspace-user-menu {
            position: relative;
            flex-shrink: 0;
        }

        .workspace-user-button {
            display: inline-flex;
            align-items: center;
            gap: 0.7rem;
            min-height: 3rem;
            padding: 0 1rem;
            border: 1px solid rgba(19, 42, 74, 0.08);
            border-radius: 999px;
            background: #ffffff;
            color: #202124;
            transition: border-color 160ms ease, background-color 160ms ease;
        }

        .workspace-user-button:hover {
            background: rgba(47, 124, 239, 0.05);
            border-color: rgba(47, 124, 239, 0.14);
        }

        .workspace-user-button:focus-visible,
        .workspace-user-link:focus-visible {
            outline: 3px solid rgba(47, 124, 239, 0.28);
            outline-offset: 2px;
        }

        .workspace-user-name {
            max-width: 12rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-weight: 600;
        }

        .workspace-user-panel {
            position: absolute;
            top: calc(100% + 0.7rem);
            right: 0;
            width: min(21rem, calc(100vw - 2rem));
            padding: 0.65rem;
            border: 1px solid rgba(19, 42, 74, 0.08);
            border-radius: 1.2rem;
            background: #ffffff;
            box-shadow: 0 22px 50px rgba(15, 23, 42, 0.12);
        }

        .workspace-user-panel.hidden {
            display: none;
        }

        .workspace-user-panel-head {
            padding: 0.65rem 0.75rem 0.8rem;
            border-bottom: 1px solid rgba(19, 42, 74, 0.08);
        }

        .workspace-user-panel-head strong,
        .workspace-user-panel-head span {
            display: block;
        }

        .workspace-user-panel-head strong {
            color: #202124;
            font-weight: 700;
        }

        .workspace-user-panel-head span {
            margin-top: 0.28rem;
            color: #6b7280;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .workspace-user-links {
            margin-top: 0.45rem;
            display: grid;
            gap: 0.15rem;
        }

        .workspace-user-link {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.8rem 0.75rem;
            border: 0;
            border-radius: 0.95rem;
            background: transparent;
            color: #374151;
            text-align: left;
            text-decoration: none;
            transition: background-color 160ms ease, color 160ms ease;
        }

        .workspace-user-link:hover {
            background: rgba(47, 124, 239, 0.08);
            color: #163b7a;
        }

        .workspace-user-link--danger:hover {
            background: rgba(234, 67, 53, 0.08);
            color: #b3261e;
        }

        .workspace-content {
            flex: 1;
            padding: 1.5rem;
            background: transparent;
        }

        .workspace-breadcrumb-shell {
            padding: 0.9rem 1.5rem 0;
        }

        .workspace-breadcrumbs {
            margin: 0;
            padding: 0;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.35rem;
            list-style: none;
            color: #5f6368;
        }

        .workspace-breadcrumb-item,
        .workspace-breadcrumb-separator {
            display: inline-flex;
            align-items: center;
        }

        .workspace-breadcrumb-link,
        .workspace-breadcrumb-current {
            min-height: 1.9rem;
            display: inline-flex;
            align-items: center;
            font-size: 0.92rem;
            line-height: 1.4;
        }

        .workspace-breadcrumb-link {
            padding: 0;
            border: 0;
            background: transparent;
            color: #5f6368;
            cursor: pointer;
            transition: color 160ms ease;
        }

        .workspace-breadcrumb-link:hover {
            color: #163b7a;
        }

        .workspace-breadcrumb-link:focus-visible {
            outline: 3px solid rgba(47, 124, 239, 0.22);
            outline-offset: 3px;
            border-radius: 0.5rem;
        }

        .workspace-breadcrumb-separator {
            color: #9aa0a6;
        }

        .workspace-breadcrumb-separator .material-symbols-rounded {
            font-size: 1rem;
        }

        .workspace-breadcrumb-current {
            color: #202124;
            font-weight: 700;
        }

        .workspace-notice {
            margin-bottom: 1rem;
            border-radius: 1rem;
        }

        .workspace-notice-shell {
            position: relative;
        }

        .workspace-dismissible-notice-content {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .workspace-notice-dismiss {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            width: 2rem;
            height: 2rem;
            margin-top: -0.1rem;
            border: 0;
            border-radius: 999px;
            background: transparent;
            color: inherit;
            cursor: pointer;
            opacity: 0.82;
            transition: background-color 160ms ease, opacity 160ms ease;
        }

        .workspace-notice-dismiss:hover,
        .workspace-notice-dismiss:focus-visible {
            background: rgba(15, 23, 42, 0.08);
            opacity: 1;
            outline: none;
        }

        .workspace-notice-message {
            flex: 1;
            min-width: 0;
            padding-top: 0.22rem;
            line-height: 1.6;
        }

        .workspace-loading,
        .workspace-section-card {
            border: 1px solid rgba(19, 42, 74, 0.08);
            background: #ffffff;
            box-shadow: 0 10px 30px rgba(16, 24, 40, 0.04);
        }

        .workspace-loading {
            min-height: calc(100vh - 10rem);
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            border-radius: 1.5rem;
            color: #4b5563;
            padding: 2rem;
        }

        .workspace-sections-stack {
            display: grid;
        }

        .workspace-section {
            display: none;
        }

        .workspace-section.is-active {
            display: block;
        }

        .workspace-section-card {
            min-height: calc(100vh - 10rem);
            border-radius: 1.5rem;
            padding: 2rem;
        }

        .workspace-section-card h2 {
            font-size: clamp(2rem, 5vw, 3rem);
            line-height: 1;
            font-weight: 500;
            color: #202124;
            letter-spacing: -0.05em;
        }

        .workspace-section-subtitle {
            margin-top: 0.85rem;
            max-width: 44rem;
            color: #6b7280;
            line-height: 1.7;
        }

        .workspace-dashboard-grid {
            margin-top: 1.75rem;
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }

        .workspace-home-layout {
            display: grid;
            gap: 1.25rem;
        }

        .workspace-home-layout > .workspace-section-card,
        .workspace-projects-card {
            min-height: auto;
        }

        .workspace-dashboard-card {
            display: flex;
            align-items: center;
            gap: 0.9rem;
            width: 100%;
            padding: 1rem;
            border: 1px solid rgba(19, 42, 74, 0.08);
            border-radius: 1.1rem;
            background: #f8fafc;
            color: #202124;
            text-align: left;
            transition: transform 160ms ease, border-color 160ms ease, background-color 160ms ease;
        }

        .workspace-dashboard-card:hover {
            transform: translateY(-1px);
            border-color: rgba(47, 124, 239, 0.18);
            background: #f1f6ff;
        }

        .workspace-dashboard-card:focus-visible {
            outline: 3px solid rgba(47, 124, 239, 0.28);
            outline-offset: 2px;
        }

        .workspace-dashboard-card img {
            width: 2.1rem;
            height: 2.1rem;
            object-fit: contain;
            flex-shrink: 0;
        }

        .workspace-dashboard-card strong {
            display: block;
            font-size: 1rem;
            font-weight: 600;
        }

        .workspace-dashboard-card p {
            margin-top: 0.3rem;
            color: #6b7280;
            line-height: 1.55;
        }

        .workspace-form-grid {
            margin-top: 1.75rem;
            display: grid;
            gap: 1.25rem;
        }

        .workspace-form-card {
            border: 1px solid rgba(19, 42, 74, 0.08);
            border-radius: 1.25rem;
            background: #f8fafc;
            padding: 1.25rem;
        }

        .workspace-form-card h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #202124;
            letter-spacing: -0.02em;
        }

        .workspace-form-copy {
            margin-top: 0.45rem;
            color: #6b7280;
            line-height: 1.65;
        }

        .workspace-form {
            margin-top: 1rem;
            display: grid;
            gap: 1rem;
        }

        .workspace-field-block {
            display: grid;
            gap: 0.45rem;
        }

        .workspace-field-label {
            font-size: 0.92rem;
            font-weight: 600;
            color: #374151;
        }

        .workspace-field {
            width: 100%;
            min-height: 3.2rem;
            padding: 0.85rem 1rem;
            border: 1px solid rgba(19, 42, 74, 0.14);
            border-radius: 0.95rem;
            background: #ffffff;
            color: #111827;
            transition: border-color 160ms ease, box-shadow 160ms ease;
        }

        .workspace-field:focus {
            outline: none;
            border-color: rgba(47, 124, 239, 0.48);
            box-shadow: 0 0 0 4px rgba(47, 124, 239, 0.12);
        }

        .workspace-field::placeholder {
            color: #9ca3af;
        }

        .workspace-primary-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            min-height: 3.1rem;
            padding: 0 1.15rem;
            border: 0;
            border-radius: 999px;
            background: #1f5fd6;
            color: #ffffff;
            font-weight: 600;
            transition: background-color 160ms ease, transform 160ms ease;
        }

        .workspace-primary-button:hover {
            background: #194fb0;
            transform: translateY(-1px);
        }

        .workspace-primary-button:focus-visible {
            outline: 3px solid rgba(47, 124, 239, 0.28);
            outline-offset: 2px;
        }

        .workspace-helper-text {
            font-size: 0.84rem;
            color: #6b7280;
            line-height: 1.6;
        }

        .workspace-backdrop {
            position: fixed;
            inset: 0;
            z-index: 45;
            border: 0;
            background: rgba(15, 23, 42, 0.32);
        }

        .workspace-backdrop.hidden {
            display: none;
        }

        .workspace-app.is-sidebar-collapsed .workspace-sidebar {
            width: 5.5rem;
        }

        .workspace-app.is-sidebar-collapsed .workspace-nav-button {
            justify-content: center;
            gap: 0;
            padding-inline: 0.5rem;
        }

        .workspace-app.is-sidebar-collapsed .workspace-nav-copy {
            width: 0;
            min-width: 0;
            opacity: 0;
        }

        .workspace-app.is-sidebar-collapsed .workspace-sidebar-project {
            justify-content: center;
            padding-inline: 0.1rem;
        }

        .workspace-app.is-sidebar-collapsed .workspace-sidebar-project-copy {
            display: none;
        }

        .workspace-app.is-sidebar-collapsed .workspace-nav-button:hover .workspace-nav-text--default,
        .workspace-app.is-sidebar-collapsed .workspace-nav-button:hover .workspace-nav-text--hover,
        .workspace-app.is-sidebar-collapsed .workspace-nav-button:focus-visible .workspace-nav-text--default,
        .workspace-app.is-sidebar-collapsed .workspace-nav-button:focus-visible .workspace-nav-text--hover {
            opacity: 0;
        }

        @media (min-width: 1024px) {
            .workspace-sidebar {
                position: sticky;
                top: 0;
                inset: auto;
                min-height: 100vh;
                transform: none;
            }

            .workspace-form-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                align-items: start;
            }

            .workspace-mobile-toggle,
            .workspace-backdrop {
                display: none;
            }
        }

        @media (max-width: 1023px) {
            .workspace-content {
                padding: 1rem;
            }

            .workspace-header {
                padding: 0.85rem 1rem;
                flex-wrap: wrap;
            }

            .workspace-section-card,
            .workspace-loading {
                min-height: calc(100vh - 8.5rem);
                border-radius: 1.25rem;
                padding: 1.5rem;
            }

            .workspace-header-logo {
                height: 2.1rem;
            }

            .workspace-user-name {
                max-width: 8rem;
            }

            .workspace-search-shell {
                order: 3;
                flex: 0 0 100%;
            }
        }

        html { scroll-behavior: smooth; }
    </style>

    <?php if ($showLoginView || $showAppView): ?>
        <script>
            window.AISCALER_AUTH_CONFIG = <?= json_encode($authClientConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        </script>
        <script type="module" src="<?= htmlspecialchars(appAssetUrl('js/app.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <?php endif; ?>
</head>
<?php if ($showAppView): ?>
<body data-view="app" class="antialiased">
    <div id="app-layout" class="workspace-app">
        <aside id="app-sidebar" class="workspace-sidebar" aria-label="Menú lateral">
            <div class="workspace-sidebar-head">
                <button id="sidebar-toggle" type="button" class="workspace-icon-button" aria-label="Comprimir menú lateral">
                    <span class="material-symbols-rounded">menu</span>
                </button>

                <button id="dashboard-link" type="button" class="workspace-icon-button" data-menu-id="inicio" aria-label="Ir a inicio" title="Inicio">
                    <span class="material-symbols-rounded">home</span>
                </button>
            </div>

            <div id="workspace-active-project" class="workspace-sidebar-project hidden" aria-live="polite">
                <span id="workspace-active-project-logo" class="workspace-sidebar-project-logo" aria-hidden="true"></span>
                <span class="workspace-sidebar-project-copy">
                    <strong id="workspace-active-project-name"></strong>
                </span>
            </div>

            <nav id="app-rail-nav" class="workspace-nav" aria-label="Menú principal"></nav>
        </aside>

        <button id="app-sidebar-backdrop" type="button" class="workspace-backdrop hidden" aria-label="Cerrar menú"></button>

        <div class="workspace-main">
            <header class="workspace-header">
                <div class="workspace-header-left">
                    <button id="mobile-menu-toggle" type="button" class="workspace-icon-button workspace-mobile-toggle" aria-label="Abrir menú lateral">
                        <span class="material-symbols-rounded">menu</span>
                    </button>

                    <button type="button" class="workspace-logo-button" data-menu-id="inicio" aria-label="Ir a inicio">
                        <img class="workspace-header-logo" src="img/logoAiScalerCenter.png" alt="AiScaler Center Logo">
                    </button>
                </div>

                <div class="workspace-search-shell" role="search" aria-label="Buscar en el panel">
                    <span class="material-symbols-rounded">search</span>
                    <input class="workspace-search-input" type="search" placeholder="Buscar en AiScaler" aria-label="Buscar en AiScaler">
                </div>

                <div class="workspace-user-menu">
                    <button id="user-menu-toggle" type="button" class="workspace-user-button" aria-haspopup="menu" aria-expanded="false">
                        <span id="app-user-name" class="workspace-user-name">Usuario</span>
                        <span class="material-symbols-rounded">expand_more</span>
                    </button>

                    <div id="user-menu-panel" class="workspace-user-panel hidden" role="menu">
                        <div class="workspace-user-panel-head">
                            <strong id="app-user-panel-name">Usuario</strong>
                            <span id="app-user-email">correo@empresa.com</span>
                        </div>

                        <div class="workspace-user-links">
                            <button type="button" class="workspace-user-link" data-menu-id="configuracion">
                                <span class="material-symbols-rounded">settings</span>
                                <span>Configuracion</span>
                            </button>

                            <button id="logout-button" type="button" class="workspace-user-link workspace-user-link--danger" aria-label="Cerrar sesion">
                                <span class="material-symbols-rounded">logout</span>
                                <span>Cerrar sesion</span>
                            </button>

                            <a href="<?= htmlspecialchars(appTermsUrl(), ENT_QUOTES, 'UTF-8'); ?>" class="workspace-user-link">
                                <span class="material-symbols-rounded">gavel</span>
                                <span>Terminos y condiciones</span>
                            </a>

                            <a href="<?= htmlspecialchars(appPrivacyUrl(), ENT_QUOTES, 'UTF-8'); ?>" class="workspace-user-link">
                                <span class="material-symbols-rounded">policy</span>
                                <span>Aviso de privacidad</span>
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <nav class="workspace-breadcrumb-shell" aria-label="Migas de pan">
                <ol id="app-breadcrumbs" class="workspace-breadcrumbs"></ol>
            </nav>

            <main class="workspace-content">
                <div id="app-notice" class="workspace-notice hidden px-4 py-3 text-sm font-semibold"></div>

                <section id="app-loading" class="workspace-loading">
                    <div>
                        <div class="mx-auto mb-6 inline-flex h-16 w-16 items-center justify-center rounded-full bg-[var(--md-primary-container)] text-[var(--md-primary-strong)]">
                            <span class="material-symbols-rounded text-3xl animate-spin">progress_activity</span>
                        </div>
                        <h2 class="text-3xl font-medium tracking-tight text-[var(--md-text)]">Preparando tu menú</h2>
                    </div>
                </section>

                <section id="app-shell" class="workspace-sections hidden">
                    <div id="app-sections" class="workspace-sections-stack"></div>
                </section>
            </main>
        </div>
    </div>
</body>
<?php elseif ($showLoginView): ?>
<body data-view="login" class="bg-slate-950 text-slate-100 antialiased">
    <div class="min-h-screen hero-bg">
        <nav class="bg-slate-950/55 backdrop-blur-md">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-20 items-center">
                    <div class="flex-shrink-0 flex items-center">
                        <img class="h-12 w-auto" src="img/logoAiScalerCenter.png" alt="AiScaler Center Logo">
                    </div>

                    <a href="<?= htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn-font rounded-full border border-white/20 bg-white/10 px-5 py-2.5 text-sm font-bold text-white transition duration-300 hover:bg-white/20">
                        Volver
                    </a>
                </div>
            </div>
        </nav>

        <main class="px-4 py-10 sm:px-6 lg:px-8">
            <div class="mx-auto grid max-w-6xl gap-8 pt-10 lg:grid-cols-[1.1fr,0.9fr] lg:items-center lg:pt-16">
                <section class="max-w-2xl">
                    <p class="text-sm font-bold uppercase tracking-[0.35em] text-sky-300">Acceso privado</p>
                    <h1 class="mt-6 text-4xl font-extrabold text-white md:text-6xl leading-tight">
                        Entra de la forma mas simple y comoda posible.
                    </h1>
                    <p class="mt-6 max-w-xl text-lg leading-8 text-slate-200">
                        Esta pantalla ya usa Supabase Auth real para que el usuario pueda crear cuenta, entrar con contrasena, recibir un magic link, recuperar acceso y volver directo al panel.
                    </p>

                    <div class="mt-10 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-3xl border border-white/10 bg-white/10 p-5 backdrop-blur-sm">
                            <p class="text-sm font-semibold text-sky-200">Inicio de sesion real</p>
                            <p class="mt-2 text-sm leading-7 text-slate-300">El acceso ya no depende de una sesion temporal del sitio, sino de la identidad real del usuario en Supabase.</p>
                        </div>
                        <div class="rounded-3xl border border-white/10 bg-white/10 p-5 backdrop-blur-sm">
                            <p class="text-sm font-semibold text-sky-200">UX preparada</p>
                            <p class="mt-2 text-sm leading-7 text-slate-300">Confirmacion por correo, reenvio de email, recuperacion y sesion persistente ya forman parte del flujo.</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-[2rem] border border-white/10 bg-slate-950/75 p-6 shadow-2xl shadow-slate-950/50 backdrop-blur-xl sm:p-8">
                    <div id="auth-notice" class="hidden mb-6 rounded-2xl px-4 py-3 text-sm font-semibold"></div>

                    <div class="mb-8">
                        <p class="text-sm uppercase tracking-[0.3em] text-slate-400">Supabase Auth</p>
                        <h2 class="mt-3 text-3xl font-extrabold text-white">Bienvenido a AiScaler Center</h2>
                        <p id="auth-settings-hint" class="mt-3 text-sm leading-7 text-slate-300">
                            El sistema detectara si necesitas confirmar tu correo antes de entrar.
                        </p>
                    </div>

                    <div class="grid grid-cols-3 gap-2 rounded-2xl border border-white/10 bg-white/5 p-2">
                        <button type="button" data-auth-target="signin" data-auth-tab class="btn-font rounded-2xl px-3 py-3 text-sm font-bold text-white transition duration-300 hover:bg-white/10">
                            Entrar
                        </button>
                        <button type="button" data-auth-target="signup" data-auth-tab class="btn-font rounded-2xl px-3 py-3 text-sm font-bold text-white transition duration-300 hover:bg-white/10">
                            Crear cuenta
                        </button>
                        <button type="button" data-auth-target="magic" data-auth-tab class="btn-font rounded-2xl px-3 py-3 text-sm font-bold text-white transition duration-300 hover:bg-white/10">
                            Magic link
                        </button>
                    </div>

                    <div id="oauth-section" class="hidden mt-6">
                        <p class="mb-3 text-xs uppercase tracking-[0.25em] text-slate-500">Otros accesos habilitados</p>
                        <div id="oauth-provider-list" class="grid gap-3"></div>
                    </div>

                    <div class="mt-6 space-y-6">
                        <div data-auth-panel="signin">
                            <h3 class="text-2xl font-extrabold text-white">Entrar con correo y contrasena</h3>
                            <p class="mt-2 text-sm leading-7 text-slate-300">
                                Si ya tienes cuenta, entra directo al panel con tu correo confirmado.
                            </p>

                            <form id="signin-form" class="mt-6 space-y-5">
                                <div>
                                    <label for="signin-email" class="mb-2 block text-sm font-semibold text-slate-200">Correo electronico</label>
                                    <input
                                        id="signin-email"
                                        name="email"
                                        type="email"
                                        required
                                        class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition duration-300 placeholder:text-slate-500 focus:border-sky-400 focus:bg-white/10"
                                        placeholder="tu@empresa.com"
                                    >
                                </div>

                                <div>
                                    <label for="signin-password" class="mb-2 block text-sm font-semibold text-slate-200">Contrasena</label>
                                    <input
                                        id="signin-password"
                                        name="password"
                                        type="password"
                                        minlength="8"
                                        required
                                        class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition duration-300 placeholder:text-slate-500 focus:border-sky-400 focus:bg-white/10"
                                        placeholder="********"
                                    >
                                </div>

                                <button type="submit" class="btn-font w-full rounded-2xl bg-brand-blue px-6 py-3.5 text-base font-bold text-white transition duration-300 hover:bg-blue-700">
                                    Entrar al panel
                                </button>
                            </form>

                            <div class="mt-6 flex flex-wrap gap-3 text-sm">
                                <button type="button" data-auth-target="forgot" class="font-semibold text-sky-300 transition duration-300 hover:text-sky-200">
                                    Olvide mi contrasena
                                </button>
                                <button type="button" data-auth-target="resend" class="font-semibold text-slate-300 transition duration-300 hover:text-white">
                                    Reenviar confirmacion
                                </button>
                            </div>
                        </div>

                        <div data-auth-panel="signup" class="hidden">
                            <h3 class="text-2xl font-extrabold text-white">Crear cuenta nueva</h3>
                            <p class="mt-2 text-sm leading-7 text-slate-300">
                                Registra al usuario y deja listo el correo de confirmacion para su primer acceso.
                            </p>

                            <form id="signup-form" class="mt-6 space-y-5">
                                <div>
                                    <label for="signup-name" class="mb-2 block text-sm font-semibold text-slate-200">Nombre</label>
                                    <input
                                        id="signup-name"
                                        name="full_name"
                                        type="text"
                                        class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition duration-300 placeholder:text-slate-500 focus:border-sky-400 focus:bg-white/10"
                                        placeholder="Tu nombre"
                                    >
                                </div>

                                <div>
                                    <label for="signup-email" class="mb-2 block text-sm font-semibold text-slate-200">Correo electronico</label>
                                    <input
                                        id="signup-email"
                                        name="email"
                                        type="email"
                                        required
                                        class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition duration-300 placeholder:text-slate-500 focus:border-sky-400 focus:bg-white/10"
                                        placeholder="tu@empresa.com"
                                    >
                                </div>

                                <div>
                                    <label for="signup-password" class="mb-2 block text-sm font-semibold text-slate-200">Contrasena</label>
                                    <input
                                        id="signup-password"
                                        name="password"
                                        type="password"
                                        minlength="8"
                                        required
                                        class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition duration-300 placeholder:text-slate-500 focus:border-sky-400 focus:bg-white/10"
                                        placeholder="Minimo 8 caracteres"
                                    >
                                </div>

                                <button type="submit" class="btn-font w-full rounded-2xl bg-brand-blue px-6 py-3.5 text-base font-bold text-white transition duration-300 hover:bg-blue-700">
                                    Crear cuenta
                                </button>
                            </form>

                            <div class="mt-6 text-sm">
                                <button type="button" data-auth-target="signin" class="font-semibold text-sky-300 transition duration-300 hover:text-sky-200">
                                    Ya tengo cuenta
                                </button>
                            </div>
                        </div>

                        <div data-auth-panel="magic" class="hidden">
                            <h3 class="text-2xl font-extrabold text-white">Entrar sin contrasena</h3>
                            <p class="mt-2 text-sm leading-7 text-slate-300">
                                Supabase puede enviarte un enlace magico para entrar con un solo clic desde tu correo.
                            </p>

                            <form id="magic-form" class="mt-6 space-y-5">
                                <div>
                                    <label for="magic-email" class="mb-2 block text-sm font-semibold text-slate-200">Correo electronico</label>
                                    <input
                                        id="magic-email"
                                        name="email"
                                        type="email"
                                        required
                                        class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition duration-300 placeholder:text-slate-500 focus:border-sky-400 focus:bg-white/10"
                                        placeholder="tu@empresa.com"
                                    >
                                </div>

                                <button type="submit" class="btn-font w-full rounded-2xl bg-brand-blue px-6 py-3.5 text-base font-bold text-white transition duration-300 hover:bg-blue-700">
                                    Enviar magic link
                                </button>
                            </form>

                            <div class="mt-6 text-sm">
                                <button type="button" data-auth-target="signin" class="font-semibold text-sky-300 transition duration-300 hover:text-sky-200">
                                    Prefiero usar contrasena
                                </button>
                            </div>
                        </div>

                        <div data-auth-panel="forgot" class="hidden">
                            <h3 class="text-2xl font-extrabold text-white">Recuperar contrasena</h3>
                            <p class="mt-2 text-sm leading-7 text-slate-300">
                                Te enviaremos un enlace para crear una nueva contrasena sin salir del flujo de acceso.
                            </p>

                            <form id="forgot-form" class="mt-6 space-y-5">
                                <div>
                                    <label for="forgot-email" class="mb-2 block text-sm font-semibold text-slate-200">Correo electronico</label>
                                    <input
                                        id="forgot-email"
                                        name="email"
                                        type="email"
                                        required
                                        class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition duration-300 placeholder:text-slate-500 focus:border-sky-400 focus:bg-white/10"
                                        placeholder="tu@empresa.com"
                                    >
                                </div>

                                <button type="submit" class="btn-font w-full rounded-2xl bg-brand-blue px-6 py-3.5 text-base font-bold text-white transition duration-300 hover:bg-blue-700">
                                    Enviar enlace de recuperacion
                                </button>
                            </form>

                            <div class="mt-6 text-sm">
                                <button type="button" data-auth-target="signin" class="font-semibold text-sky-300 transition duration-300 hover:text-sky-200">
                                    Volver a entrar
                                </button>
                            </div>
                        </div>

                        <div data-auth-panel="resend" class="hidden">
                            <h3 class="text-2xl font-extrabold text-white">Reenviar confirmacion</h3>
                            <p class="mt-2 text-sm leading-7 text-slate-300">
                                Si el usuario no encontro el correo de confirmacion, puedes reenviarlo desde aqui.
                            </p>

                            <form id="resend-form" class="mt-6 space-y-5">
                                <div>
                                    <label for="resend-email" class="mb-2 block text-sm font-semibold text-slate-200">Correo electronico</label>
                                    <input
                                        id="resend-email"
                                        name="email"
                                        type="email"
                                        required
                                        class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition duration-300 placeholder:text-slate-500 focus:border-sky-400 focus:bg-white/10"
                                        placeholder="tu@empresa.com"
                                    >
                                </div>

                                <button type="submit" class="btn-font w-full rounded-2xl bg-brand-blue px-6 py-3.5 text-base font-bold text-white transition duration-300 hover:bg-blue-700">
                                    Reenviar correo
                                </button>
                            </form>

                            <div class="mt-6 text-sm">
                                <button type="button" data-auth-target="signin" class="font-semibold text-sky-300 transition duration-300 hover:text-sky-200">
                                    Volver a entrar
                                </button>
                            </div>
                        </div>

                        <div data-auth-panel="reset" class="hidden">
                            <h3 class="text-2xl font-extrabold text-white">Crear nueva contrasena</h3>
                            <p class="mt-2 text-sm leading-7 text-slate-300">
                                Estas dentro del flujo de recuperacion. Define una nueva contrasena para continuar al panel.
                            </p>

                            <form id="reset-form" class="mt-6 space-y-5">
                                <div>
                                    <label for="reset-password" class="mb-2 block text-sm font-semibold text-slate-200">Nueva contrasena</label>
                                    <input
                                        id="reset-password"
                                        name="password"
                                        type="password"
                                        minlength="8"
                                        required
                                        class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition duration-300 placeholder:text-slate-500 focus:border-sky-400 focus:bg-white/10"
                                        placeholder="Minimo 8 caracteres"
                                    >
                                </div>

                                <div>
                                    <label for="reset-password-confirm" class="mb-2 block text-sm font-semibold text-slate-200">Confirmar contrasena</label>
                                    <input
                                        id="reset-password-confirm"
                                        name="password_confirm"
                                        type="password"
                                        minlength="8"
                                        required
                                        class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition duration-300 placeholder:text-slate-500 focus:border-sky-400 focus:bg-white/10"
                                        placeholder="Repite la contrasena"
                                    >
                                </div>

                                <button type="submit" class="btn-font w-full rounded-2xl bg-brand-blue px-6 py-3.5 text-base font-bold text-white transition duration-300 hover:bg-blue-700">
                                    Guardar nueva contrasena
                                </button>
                            </form>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>
</body>
<?php else: ?>
<body class="bg-gray-50 text-gray-800 antialiased">
    <nav class="bg-white shadow-md fixed w-full z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20">
                <div class="flex-shrink-0 flex items-center">
                    <img class="h-12 w-auto" src="img/logoAiScalerCenter.png" alt="AiScaler Center Logo">
                </div>

                <div class="flex items-center gap-3">
                    <a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn-font rounded-full bg-brand-dark px-5 py-2.5 text-sm font-bold text-white transition duration-300 hover:bg-slate-800">
                        Iniciar sesion
                    </a>

                    <a href="#registro" class="hidden rounded-full bg-brand-blue px-6 py-2.5 text-sm font-bold text-white transition duration-300 hover:bg-blue-700 md:block btn-font">
                        Reservar mi Lugar
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <header class="hero-bg flex h-screen items-center justify-center px-4 pt-20 text-center">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-4xl md:text-6xl font-extrabold text-white mb-6 leading-tight">
                No dejes que la IA te reemplace.<br>
                <span class="text-brand-blue bg-white px-2 rounded">Conviértete en quien la domina.</span>
            </h1>
            <p class="text-xl text-gray-200 mb-10 max-w-2xl mx-auto">
                El mundo no necesita más "usuarios" de ChatGPT. Las empresas buscan desesperadamente <strong>AiScalers</strong>: estrategas capaces de escalar negocios usando Inteligencia Artificial.
            </p>

            <a href="#registro" class="inline-block bg-brand-amber text-gray-900 font-extrabold text-xl py-4 px-10 rounded-lg shadow-lg hover:bg-yellow-500 transform hover:scale-105 transition duration-300 btn-font">
                QUIERO SER UN AISCALER
            </a>

            <p class="mt-4 text-sm text-gray-400">Entrenamiento Exclusivo | Plazas Limitadas</p>
        </div>
    </header>

    <section class="py-20 bg-white">
        <div class="max-w-5xl mx-auto px-4 text-center">
            <h2 class="text-3xl font-bold text-gray-900 mb-8">La dura realidad del mercado actual</h2>
            <div class="grid md:grid-cols-3 gap-8">
                <div class="p-6 bg-gray-50 rounded-xl shadow-sm hover:shadow-md transition">
                    <i class="fas fa-robot text-5xl text-brand-blue mb-4"></i>
                    <h3 class="text-xl font-bold mb-2">Automatización Masiva</h3>
                    <p class="text-gray-600">Las tareas repetitivas están desapareciendo. Si tu trabajo es operativo, estás en riesgo.</p>
                </div>
                <div class="p-6 bg-gray-50 rounded-xl shadow-sm hover:shadow-md transition">
                    <i class="fas fa-chart-line text-5xl text-red-500 mb-4"></i>
                    <h3 class="text-xl font-bold mb-2">Brecha de Habilidades</h3>
                    <p class="text-gray-600">Las empresas tienen la tecnología pero no saben cómo implementarla para crecer.</p>
                </div>
                <div class="p-6 bg-gray-50 rounded-xl shadow-sm hover:shadow-md transition">
                    <i class="fas fa-user-slash text-5xl text-gray-400 mb-4"></i>
                    <h3 class="text-xl font-bold mb-2">Irrelevancia</h3>
                    <p class="text-gray-600">Quienes no se adapten hoy, serán invisibles para el mercado laboral mañana.</p>
                </div>
            </div>
            <div class="mt-12">
                <a href="#registro" class="text-brand-blue font-bold text-lg hover:underline underline-offset-4">
                    Prefiero tomar el control de mi futuro &rarr;
                </a>
            </div>
        </div>
    </section>

    <section class="py-20 bg-gray-900 text-white">
        <div class="max-w-6xl mx-auto px-4">
            <div class="flex flex-col md:flex-row items-center">
                <div class="md:w-1/2 mb-10 md:mb-0 pr-0 md:pr-10">
                    <h2 class="text-3xl md:text-4xl font-bold mb-6">¿Qué es un <span class="text-brand-blue">AiScaler</span>?</h2>
                    <p class="text-lg text-gray-300 mb-6">
                        Un AiScaler no es un programador. Es un arquitecto de crecimiento. Es el profesional que entiende cómo orquestar la tecnología para multiplicar resultados.
                    </p>
                    <p class="text-lg text-gray-300 mb-8">
                        A través de nuestra metodología <strong>I.D.E.A.</strong> (Idea, Diseño, Ejecución, Automatización), aprenderás a crear sistemas que trabajan solos, volviéndote el activo más valioso de cualquier compañía.
                    </p>
                    <ul class="space-y-4 mb-8">
                        <li class="flex items-center"><i class="fas fa-check-circle text-green-500 mr-3"></i> Domina la IA Estratégica</li>
                        <li class="flex items-center"><i class="fas fa-check-circle text-green-500 mr-3"></i> Escala operaciones empresariales</li>
                        <li class="flex items-center"><i class="fas fa-check-circle text-green-500 mr-3"></i> Asegura tu relevancia profesional</li>
                    </ul>
                    <a href="#registro" class="inline-block bg-white text-gray-900 font-bold py-3 px-8 rounded hover:bg-gray-100 transition">
                        Aplicar al Programa
                    </a>
                </div>
                <div class="md:w-1/2 flex justify-center">
                    <div class="grid grid-cols-2 gap-4 w-full max-w-sm">
                        <div class="bg-blue-600 h-32 rounded-lg flex items-center justify-center text-2xl font-bold shadow-lg shadow-blue-500/50">I</div>
                        <div class="bg-red-500 h-32 rounded-lg flex items-center justify-center text-2xl font-bold shadow-lg shadow-red-500/50">D</div>
                        <div class="bg-yellow-400 h-32 rounded-lg flex items-center justify-center text-2xl font-bold text-black shadow-lg shadow-yellow-400/50">E</div>
                        <div class="bg-green-600 h-32 rounded-lg flex items-center justify-center text-2xl font-bold shadow-lg shadow-green-500/50">A</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-20 bg-white relative overflow-hidden">
        <div class="absolute top-0 right-0 -mr-20 -mt-20 opacity-5">
            <i class="fas fa-arrow-up text-9xl"></i>
        </div>

        <div class="max-w-4xl mx-auto px-4 text-center">
            <span class="bg-blue-100 text-brand-blue px-4 py-1 rounded-full font-bold text-sm tracking-wide uppercase">Beneficio Exclusivo</span>
            <h2 class="text-4xl font-bold text-gray-900 mt-6 mb-6">El Directorio Oficial AiScaler</h2>
            <p class="text-xl text-gray-600 mb-10">
                Al graduarte, no te damos solo un diploma. Te damos visibilidad.
                <br><br>
                Las empresas ya no buscan currículums, buscan resultados. Al completar tu formación, ingresarás a nuestro <strong>Directorio Certificado</strong>, donde las compañías que necesitan escalar buscan talento calificado.
            </p>

            <div class="bg-gray-50 border-2 border-dashed border-gray-300 rounded-xl p-8 max-w-2xl mx-auto mb-10">
                <div class="flex items-center justify-center mb-4">
                    <i class="fas fa-search text-3xl text-gray-400 mr-4"></i>
                    <span class="text-2xl font-bold text-gray-700">Tu Perfil Profesional</span>
                </div>
                <p class="text-gray-500 italic">"Deja que las oportunidades te encuentren a ti, en lugar de tú perseguirlas."</p>
            </div>

            <a href="#registro" class="bg-brand-blue text-white font-extrabold text-xl py-4 px-12 rounded-lg shadow-xl hover:bg-blue-800 transition duration-300 btn-font">
                QUIERO ESTAR EN EL DIRECTORIO
            </a>
        </div>
    </section>

    <section id="registro" class="py-24 bg-gray-100">
        <div class="max-w-3xl mx-auto px-4">
            <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
                <div class="bg-gray-900 py-6 px-8 text-center">
                    <h2 class="text-2xl md:text-3xl font-bold text-white">Da el primer paso ahora</h2>
                    <p class="text-gray-400 mt-2">Regístrate para recibir el acceso a la formación en tu correo y WhatsApp.</p>
                </div>

                <div class="p-8 md:p-12">
                    <div class="hubspot-container">
                        <script src="https://js.hsforms.net/forms/embed/50539613.js" defer></script>
                        <div class="hs-form-frame" data-region="na1" data-form-id="f1c8fbc5-56cb-4a92-b1f0-152bd49cb06a" data-portal-id="50539613"></div>
                    </div>
                    <div class="mt-6 text-center">
                        <p class="text-xs text-gray-500">
                            <i class="fas fa-lock mr-1"></i> Tus datos están 100% seguros. No hacemos spam.
                            Al registrarte, recibirás el enlace para unirte a la revolución AiScaler.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-white border-t border-gray-200 pt-12 pb-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col items-center">
            <img class="h-10 w-auto mb-4 opacity-80" src="img/logoAiScalerCenter.png" alt="AiScaler Logo Footer">
            <p class="text-gray-500 text-sm text-center mb-4">
                © 2026 AiScaler Center. Todos los derechos reservados.<br>
                La metodología oficial para escalar negocios con Inteligencia Artificial.
            </p>
            <div class="flex space-x-6">
                <a href="#" class="text-gray-400 hover:text-gray-500"><i class="fab fa-linkedin text-xl"></i></a>
                <a href="#" class="text-gray-400 hover:text-gray-500"><i class="fab fa-instagram text-xl"></i></a>
                <a href="#" class="text-gray-400 hover:text-gray-500"><i class="fab fa-twitter text-xl"></i></a>
            </div>
        </div>
    </footer>
</body>
<?php endif; ?>
</html>
