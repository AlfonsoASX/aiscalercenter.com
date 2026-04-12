<?php
declare(strict_types=1);

use AiScaler\LandingPages\LandingPageRepository;

require_once __DIR__ . '/lib/pwa.php';
require_once __DIR__ . '/modules/landing-pages/bootstrap.php';

$repository = new LandingPageRepository();
$identifier = trim((string) ($_GET['p'] ?? ''));
$page = null;
$error = null;

try {
    if ($identifier === '') {
        throw new InvalidArgumentException('No encontramos la landing que quieres ver.');
    }

    $page = $repository->getPublicPage($identifier);

    if (!is_array($page)) {
        throw new RuntimeException('Esta landing no existe o aun no esta publicada.');
    }
} catch (Throwable $exception) {
    $error = normalizeLandingBuilderException($exception);
}

$pageTitle = is_array($page) ? (string) ($page['title'] ?? 'Landing') : 'Landing';
$pageDescription = is_array($page) ? (string) ($page['description'] ?? '') : '';
$settings = landingPublicSettings($page);
$blocks = landingPublicBlocks($page);
$seoTitle = trim((string) ($settings['seo_title'] ?? '')) ?: $pageTitle;
$seoDescription = trim((string) ($settings['seo_description'] ?? '')) ?: $pageDescription;
$ogImage = landingPublicSanitizeImageUrl((string) ($settings['og_image'] ?? ''));
$canonicalUrl = trim((string) ($settings['canonical_url'] ?? ''));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($seoTitle, ENT_QUOTES, 'UTF-8'); ?> - AiScaler Center</title>
    <?php if ($seoDescription !== ''): ?>
        <meta name="description" content="<?= htmlspecialchars($seoDescription, ENT_QUOTES, 'UTF-8'); ?>">
        <meta property="og:description" content="<?= htmlspecialchars($seoDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <meta property="og:title" content="<?= htmlspecialchars($seoTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="website">
    <?php if ($ogImage !== ''): ?>
        <meta property="og:image" content="<?= htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php if ($canonicalUrl !== ''): ?>
        <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
        <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?= renderPwaHead([
        'theme_color' => (string) ($settings['primary_color'] ?? '#2f7cef'),
        'background_color' => (string) ($settings['background_color'] ?? '#f5f7fb'),
    ]); ?>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,500,0,0">
    <style>
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            --landing-primary: <?= htmlspecialchars($settings['primary_color'], ENT_QUOTES, 'UTF-8'); ?>;
            --landing-accent: <?= htmlspecialchars($settings['accent_color'], ENT_QUOTES, 'UTF-8'); ?>;
            --landing-bg: <?= htmlspecialchars($settings['background_color'], ENT_QUOTES, 'UTF-8'); ?>;
            --landing-surface: <?= htmlspecialchars($settings['surface_color'], ENT_QUOTES, 'UTF-8'); ?>;
            --landing-text: <?= htmlspecialchars($settings['text_color'], ENT_QUOTES, 'UTF-8'); ?>;
            margin: 0;
            min-height: 100vh;
            font-family: Roboto, sans-serif;
            background: var(--landing-bg);
            color: var(--landing-text);
        }
        .public-landing-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            min-height: 4.4rem;
            padding: 0 clamp(1rem, 5vw, 4rem);
            border-bottom: 1px solid rgba(32, 33, 36, 0.08);
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(18px);
        }
        .public-landing-nav strong {
            font-size: 1.1rem;
            letter-spacing: -0.03em;
        }
        .public-landing-nav nav {
            display: flex;
            gap: 1rem;
            color: #5f6368;
            font-weight: 700;
        }
        .public-landing-nav a,
        .public-landing-footer a {
            color: inherit;
            font-weight: 800;
            text-decoration: none;
        }
        .public-landing-nav-brand {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
        }
        .public-landing-nav-brand img {
            width: auto;
            height: 2.1rem;
            object-fit: contain;
        }
        .public-landing-nav-mark {
            display: inline-grid;
            place-items: center;
            width: 2rem;
            height: 2rem;
            border-radius: 0.7rem;
            background: var(--landing-primary);
            color: #ffffff;
            font-weight: 900;
        }
        .public-landing-section {
            padding: clamp(2.5rem, 7vw, 6rem) clamp(1rem, 6vw, 5rem);
        }
        .public-landing-block {
            position: relative;
            isolation: isolate;
            overflow: hidden;
        }
        .public-landing-block::before,
        .public-landing-block::after {
            content: "";
            position: absolute;
            inset: 0;
            z-index: 0;
            pointer-events: none;
        }
        .public-landing-block::before {
            background-image: var(--block-bg-image, none);
            background-position: center;
            background-size: cover;
        }
        .public-landing-block::after {
            background: var(--block-bg-color, transparent);
            opacity: var(--block-bg-opacity, 0);
        }
        .public-landing-block > * {
            position: relative;
            z-index: 1;
        }
        .public-landing-hero,
        .public-landing-split {
            display: grid;
            align-items: center;
            gap: clamp(1.5rem, 5vw, 4rem);
            grid-template-columns: minmax(0, 1fr) minmax(280px, 0.9fr);
        }
        .public-landing-split.is-reversed {
            grid-template-columns: minmax(280px, 0.9fr) minmax(0, 1fr);
        }
        .public-landing-split.is-reversed .public-landing-copy {
            order: 2;
        }
        .public-landing-copy,
        .public-landing-cta,
        .public-landing-testimonial {
            display: grid;
            gap: 1rem;
        }
        .public-landing-copy span,
        .public-landing-cta span {
            color: var(--landing-primary);
            font-size: 0.78rem;
            font-weight: 900;
            letter-spacing: 0.09em;
            text-transform: uppercase;
        }
        .public-landing-copy h1,
        .public-landing-copy h2,
        .public-landing-cta h2 {
            margin: 0;
            max-width: 13ch;
            color: var(--landing-text);
            line-height: 0.96;
            letter-spacing: -0.065em;
        }
        .public-landing-copy h1 {
            font-size: clamp(3rem, 8vw, 7rem);
        }
        .public-landing-copy h2,
        .public-landing-cta h2 {
            font-size: clamp(2.2rem, 6vw, 5rem);
        }
        .public-landing-copy p,
        .public-landing-feature-card p,
        .public-landing-cta p,
        .public-landing-testimonial small {
            margin: 0;
            color: #5f6368;
            line-height: 1.72;
        }
        .public-landing-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        .public-landing-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: fit-content;
            min-height: 3.2rem;
            border-radius: 999px;
            background: var(--landing-primary);
            color: #ffffff;
            padding: 0 1.15rem;
            font-weight: 900;
            text-decoration: none;
        }
        .public-landing-button--secondary {
            background: rgba(255, 255, 255, 0.72);
            color: var(--landing-text);
            border: 1px solid rgba(32, 33, 36, 0.12);
        }
        .public-landing-media {
            display: grid;
            place-items: center;
            overflow: hidden;
            width: 100%;
            aspect-ratio: 16 / 9;
            border-radius: 1.8rem;
            background:
                radial-gradient(circle at 20% 20%, rgba(217, 48, 37, 0.24), transparent 10rem),
                linear-gradient(135deg, rgba(255, 255, 255, 0.8), rgba(26, 115, 232, 0.12));
            box-shadow: 0 22px 54px rgba(24, 39, 75, 0.11);
        }
        .public-landing-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .public-landing-media.is-empty {
            color: #5f6368;
            font-weight: 800;
        }
        .public-landing-media .material-symbols-rounded {
            color: var(--landing-primary);
            font-size: 2.7rem;
        }
        .public-landing-section-heading {
            display: grid;
            gap: 0.8rem;
            max-width: 760px;
            margin-bottom: 1.5rem;
        }
        .public-landing-section-heading span {
            color: var(--landing-primary);
            font-size: 0.78rem;
            font-weight: 900;
            letter-spacing: 0.09em;
            text-transform: uppercase;
        }
        .public-landing-section-heading h2 {
            margin: 0;
            font-size: clamp(2.2rem, 6vw, 5rem);
            line-height: 0.98;
            letter-spacing: -0.06em;
        }
        .public-landing-feature-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        .public-landing-feature-card,
        .public-landing-cta,
        .public-landing-testimonial {
            border: 1px solid rgba(32, 33, 36, 0.08);
            border-radius: 1.5rem;
            background: var(--landing-surface);
            box-shadow: 0 16px 40px rgba(24, 39, 75, 0.08);
        }
        .public-landing-feature-card {
            display: grid;
            gap: 0.75rem;
            padding: 1.35rem;
        }
        .public-landing-feature-card .material-symbols-rounded {
            color: var(--landing-primary);
        }
        .public-landing-feature-card h3 {
            margin: 0;
        }
        .public-landing-testimonial {
            align-items: center;
            justify-items: center;
            padding: clamp(2rem, 5vw, 4rem);
            text-align: center;
        }
        .public-landing-testimonial blockquote {
            margin: 0;
            font-size: clamp(1.6rem, 5vw, 4rem);
            line-height: 1.08;
            letter-spacing: -0.05em;
        }
        .public-landing-cta {
            align-items: center;
            justify-items: center;
            padding: clamp(2rem, 5vw, 4rem);
            text-align: center;
        }
        .public-landing-error {
            width: min(760px, calc(100% - 2rem));
            margin: 2rem auto;
            padding: 1.25rem;
            border-radius: 1.2rem;
            background: rgba(217, 48, 37, 0.08);
            color: #b3261e;
            font-weight: 800;
        }
        .public-landing-footer {
            display: grid;
            gap: 1rem;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: start;
            padding: clamp(2rem, 5vw, 4rem) clamp(1rem, 6vw, 5rem);
            border-top: 1px solid rgba(32, 33, 36, 0.08);
            background: rgba(255, 255, 255, 0.88);
        }
        .public-landing-footer strong {
            display: block;
            margin-bottom: 0.55rem;
            font-size: 1.2rem;
            letter-spacing: -0.035em;
        }
        .public-landing-footer p,
        .public-landing-footer small {
            margin: 0;
            color: #5f6368;
            line-height: 1.7;
        }
        .public-landing-footer nav {
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
        }
        .public-landing-footer small {
            grid-column: 1 / -1;
        }
        @media (max-width: 820px) {
            .public-landing-nav nav {
                display: none;
            }
            .public-landing-nav,
            .public-landing-footer,
            .public-landing-footer nav {
                align-items: stretch;
                flex-direction: column;
            }
            .public-landing-hero,
            .public-landing-split,
            .public-landing-split.is-reversed,
            .public-landing-feature-grid,
            .public-landing-footer {
                grid-template-columns: 1fr;
            }
            .public-landing-split.is-reversed .public-landing-copy {
                order: initial;
            }
            .public-landing-button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php if ($error !== null): ?>
        <main class="public-landing-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></main>
    <?php elseif (is_array($page)): ?>
        <main>
            <?php foreach ($blocks as $block): ?>
                <?= landingPublicRenderBlock($block); ?>
            <?php endforeach; ?>
        </main>
    <?php endif; ?>
</body>
</html>

<?php
function landingPublicSettings(?array $page): array
{
    $rawSettings = is_array($page) ? ($page['settings'] ?? []) : [];
    $settings = is_array($rawSettings) ? $rawSettings : [];

    if (is_string($rawSettings)) {
        $decoded = json_decode($rawSettings, true);
        $settings = is_array($decoded) ? $decoded : [];
    }

    $defaults = [
        'brand_name' => 'AiScaler',
        'primary_color' => '#d93025',
        'accent_color' => '#1a73e8',
        'background_color' => '#fff7f5',
        'surface_color' => '#ffffff',
        'text_color' => '#202124',
        'seo_title' => '',
        'seo_description' => '',
        'og_image' => '',
        'canonical_url' => '',
    ];

    $merged = array_merge($defaults, array_intersect_key($settings, $defaults));

    foreach (['primary_color', 'accent_color', 'background_color', 'surface_color', 'text_color'] as $key) {
        $merged[$key] = landingPublicColor((string) ($merged[$key] ?? ''), $defaults[$key]);
    }

    return $merged;
}

function landingPublicColor(string $value, string $fallback): string
{
    $trimmed = trim($value);

    return preg_match('/^#[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?$/', $trimmed) === 1 ? $trimmed : $fallback;
}

function landingPublicBlocks(?array $page): array
{
    $rawBlocks = is_array($page) ? ($page['blocks'] ?? []) : [];
    $blocks = is_array($rawBlocks) ? $rawBlocks : [];

    if (is_string($rawBlocks)) {
        $decoded = json_decode($rawBlocks, true);
        $blocks = is_array($decoded) ? $decoded : [];
    }

    return array_values(array_filter($blocks, static fn ($block): bool => is_array($block)));
}

function landingPublicRenderBlock(array $block): string
{
    $type = (string) ($block['type'] ?? 'hero');

    if ($type === 'top') {
        $items = is_array($block['items'] ?? null) ? $block['items'] : [];

        ob_start();
        ?>
        <header <?= landingPublicBlockAttributes($block, 'public-landing-nav'); ?>>
            <a href="#inicio" class="public-landing-nav-brand">
                <?= landingPublicRenderLogo((string) ($block['logo_url'] ?? '')); ?>
                <strong><?= htmlspecialchars((string) ($block['brand_name'] ?? 'AiScaler'), ENT_QUOTES, 'UTF-8'); ?></strong>
            </a>
            <nav>
                <?php foreach ($items as $item): ?>
                    <?php $item = is_array($item) ? $item : []; ?>
                    <?php if (trim((string) ($item['title'] ?? '')) !== ''): ?>
                        <a href="<?= htmlspecialchars((string) (($item['body'] ?? '') ?: '#'), ENT_QUOTES, 'UTF-8'); ?>">
                            <?= htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
            <?= landingPublicRenderButton((string) ($block['button_label'] ?? ''), (string) ($block['button_url'] ?? ''), true); ?>
        </header>
        <?php

        return (string) ob_get_clean();
    }

    if ($type === 'feature_grid') {
        $items = is_array($block['items'] ?? null) ? $block['items'] : [];

        ob_start();
        ?>
        <section <?= landingPublicBlockAttributes($block, 'public-landing-section', 'id="beneficios"'); ?>>
            <div class="public-landing-section-heading">
                <span><?= htmlspecialchars((string) ($block['eyebrow'] ?? 'Beneficios'), ENT_QUOTES, 'UTF-8'); ?></span>
                <h2><?= htmlspecialchars((string) ($block['heading'] ?? 'Beneficios principales'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <p><?= htmlspecialchars((string) ($block['body'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="public-landing-feature-grid">
                <?php foreach ($items as $item): ?>
                    <?php $item = is_array($item) ? $item : []; ?>
                    <article class="public-landing-feature-card">
                        <span class="material-symbols-rounded">check_circle</span>
                        <h3><?= htmlspecialchars((string) ($item['title'] ?? 'Beneficio'), ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p><?= htmlspecialchars((string) ($item['body'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    if ($type === 'split') {
        $reversed = (string) ($block['media_position'] ?? 'right') === 'left';

        return '<section ' . landingPublicBlockAttributes($block, 'public-landing-section') . '><div class="public-landing-split' . ($reversed ? ' is-reversed' : '') . '">'
            . landingPublicRenderMedia((string) ($block['image_url'] ?? ''))
            . '<div class="public-landing-copy"><span>' . htmlspecialchars((string) ($block['eyebrow'] ?? 'Como funciona'), ENT_QUOTES, 'UTF-8') . '</span>'
            . '<h2>' . htmlspecialchars((string) ($block['heading'] ?? 'Titulo del bloque'), ENT_QUOTES, 'UTF-8') . '</h2>'
            . '<p>' . htmlspecialchars((string) ($block['body'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>'
            . landingPublicRenderButton((string) ($block['button_label'] ?? ''), (string) ($block['button_url'] ?? ''), false)
            . '</div></div></section>';
    }

    if ($type === 'testimonial') {
        return '<section ' . landingPublicBlockAttributes($block, 'public-landing-section') . '><div class="public-landing-testimonial">'
            . '<span class="material-symbols-rounded">format_quote</span>'
            . '<blockquote>' . htmlspecialchars((string) ($block['quote'] ?? ''), ENT_QUOTES, 'UTF-8') . '</blockquote>'
            . '<strong>' . htmlspecialchars((string) ($block['author'] ?? ''), ENT_QUOTES, 'UTF-8') . '</strong>'
            . '<small>' . htmlspecialchars((string) ($block['role'] ?? ''), ENT_QUOTES, 'UTF-8') . '</small>'
            . '</div></section>';
    }

    if ($type === 'cta') {
        return '<section ' . landingPublicBlockAttributes($block, 'public-landing-section', 'id="contacto"') . '><div class="public-landing-cta">'
            . '<span>' . htmlspecialchars((string) ($block['eyebrow'] ?? 'Siguiente paso'), ENT_QUOTES, 'UTF-8') . '</span>'
            . '<h2>' . htmlspecialchars((string) ($block['heading'] ?? 'Toma accion'), ENT_QUOTES, 'UTF-8') . '</h2>'
            . '<p>' . htmlspecialchars((string) ($block['body'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>'
            . landingPublicRenderButton((string) ($block['button_label'] ?? ''), (string) ($block['button_url'] ?? ''), true)
            . '</div></section>';
    }

    if ($type === 'footer') {
        $items = is_array($block['items'] ?? null) ? $block['items'] : [];

        ob_start();
        ?>
        <footer <?= landingPublicBlockAttributes($block, 'public-landing-footer'); ?>>
            <div>
                <strong><?= htmlspecialchars((string) ($block['brand_name'] ?? 'AiScaler'), ENT_QUOTES, 'UTF-8'); ?></strong>
                <p><?= htmlspecialchars((string) ($block['body'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <nav>
                <?php foreach ($items as $item): ?>
                    <?php $item = is_array($item) ? $item : []; ?>
                    <?php if (trim((string) ($item['title'] ?? '')) !== ''): ?>
                        <a href="<?= htmlspecialchars((string) (($item['body'] ?? '') ?: '#'), ENT_QUOTES, 'UTF-8'); ?>">
                            <?= htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
            <?php if (trim((string) ($block['copyright'] ?? '')) !== ''): ?>
                <small><?= htmlspecialchars((string) ($block['copyright'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
            <?php endif; ?>
        </footer>
        <?php

        return (string) ob_get_clean();
    }

    return '<section ' . landingPublicBlockAttributes($block, 'public-landing-section', 'id="inicio"') . '><div class="public-landing-hero">'
        . '<div class="public-landing-copy"><span>' . htmlspecialchars((string) ($block['eyebrow'] ?? 'Nueva oferta'), ENT_QUOTES, 'UTF-8') . '</span>'
        . '<h1>' . htmlspecialchars((string) ($block['heading'] ?? 'Titulo principal'), ENT_QUOTES, 'UTF-8') . '</h1>'
        . '<p>' . htmlspecialchars((string) ($block['body'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>'
        . '<div class="public-landing-actions">'
        . landingPublicRenderButton((string) ($block['primary_label'] ?? ''), (string) ($block['primary_url'] ?? ''), true)
        . landingPublicRenderButton((string) ($block['secondary_label'] ?? ''), (string) ($block['secondary_url'] ?? ''), false)
        . '</div></div>'
        . landingPublicRenderMedia((string) ($block['image_url'] ?? ''))
        . '</div></section>';
}

function landingPublicBlockAttributes(array $block, string $classes, string $extraAttributes = ''): string
{
    $style = landingPublicBlockStyle($block);
    $attributes = 'class="' . htmlspecialchars(trim($classes . ' public-landing-block'), ENT_QUOTES, 'UTF-8') . '"';

    if ($style !== '') {
        $attributes .= ' style="' . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . '"';
    }

    if (trim($extraAttributes) !== '') {
        $attributes .= ' ' . trim($extraAttributes);
    }

    return $attributes;
}

function landingPublicBlockStyle(array $block): string
{
    $imageUrl = landingPublicSanitizeImageUrl((string) ($block['background_image_url'] ?? ''));
    $color = landingPublicColor((string) ($block['background_color'] ?? ''), '#ffffff');
    $opacity = landingPublicOpacity((string) ($block['background_opacity'] ?? '0')) / 100;

    return implode('; ', [
        '--block-bg-image: ' . ($imageUrl !== '' ? 'url("' . landingPublicCssUrl($imageUrl) . '")' : 'none'),
        '--block-bg-color: ' . $color,
        '--block-bg-opacity: ' . number_format($opacity, 2, '.', ''),
    ]);
}

function landingPublicOpacity(string $value): int
{
    $opacity = filter_var($value, FILTER_VALIDATE_INT);

    if ($opacity === false) {
        return 0;
    }

    return max(0, min(100, $opacity));
}

function landingPublicSanitizeImageUrl(string $value): string
{
    $trimmed = trim($value);

    if ($trimmed === '') {
        return '';
    }

    if (
        str_starts_with(strtolower($trimmed), 'data:')
        || !appStorageIsManagedPublicUrl($trimmed)
    ) {
        return '';
    }

    return $trimmed;
}

function landingPublicCssUrl(string $value): string
{
    return str_replace(["\\", '"', "\n", "\r"], ['\\\\', '\\"', '', ''], $value);
}

function landingPublicRenderLogo(string $url): string
{
    $trimmed = landingPublicSanitizeImageUrl($url);

    if ($trimmed === '') {
        return '<span class="public-landing-nav-mark" aria-hidden="true">A</span>';
    }

    return '<img src="' . htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8') . '" alt="" loading="lazy">';
}

function landingPublicRenderMedia(string $url): string
{
    $trimmed = landingPublicSanitizeImageUrl($url);

    if ($trimmed === '') {
        return '<div class="public-landing-media is-empty"><span class="material-symbols-rounded">image</span></div>';
    }

    return '<div class="public-landing-media"><img src="' . htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8') . '" alt="" loading="lazy"></div>';
}

function landingPublicRenderButton(string $label, string $url, bool $primary): string
{
    $trimmedLabel = trim($label);

    if ($trimmedLabel === '') {
        return '';
    }

    $class = $primary ? 'public-landing-button' : 'public-landing-button public-landing-button--secondary';
    $href = trim($url) !== '' ? trim($url) : '#';

    return '<a class="' . $class . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($trimmedLabel, ENT_QUOTES, 'UTF-8') . '</a>';
}
