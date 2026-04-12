<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/pwa.php';

$supabaseConfig = require __DIR__ . '/config/supabase.php';
$projectUrl = trim((string) ($supabaseConfig['project_url'] ?? ''));
$publishableKey = trim((string) ($supabaseConfig['publishable_key'] ?? ''));
$anonKey = trim((string) ($supabaseConfig['anon_key'] ?? ''));
$supabasePublicKey = $publishableKey !== '' && $publishableKey !== 'tu_publishable_key' ? $publishableKey : $anonKey;
$slug = trim((string) ($_GET['slug'] ?? ''));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Articulo - AiScaler Center</title>
    <?= renderPwaHead([
        'description' => 'Lee articulos y recursos publicados en AiScaler Center.',
        'background_color' => '#f5f7fb',
    ]); ?>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: 'Roboto', sans-serif;
            background: #f5f7fb;
            color: #202124;
        }

        .article-shell {
            max-width: 900px;
            margin: 0 auto;
            padding: 32px 20px 56px;
        }

        .article-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .article-topbar img {
            height: 44px;
            width: auto;
            object-fit: contain;
        }

        .article-topbar a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 18px;
            border-radius: 999px;
            background: #1f5fd6;
            color: #ffffff;
            text-decoration: none;
            font-weight: 600;
        }

        .article-card {
            margin-top: 28px;
            padding: 32px;
            border-radius: 28px;
            background: #ffffff;
            border: 1px solid rgba(19, 42, 74, 0.08);
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.06);
        }

        .article-cover {
            width: 100%;
            max-height: 420px;
            object-fit: cover;
            border-radius: 22px;
            background: #e5e7eb;
        }

        .article-card h1 {
            margin: 0;
            font-size: clamp(2.2rem, 6vw, 3.6rem);
            letter-spacing: -0.05em;
        }

        .article-meta {
            margin-top: 14px;
            color: #6b7280;
            line-height: 1.7;
        }

        .article-excerpt {
            margin-top: 18px;
            font-size: 1.05rem;
            line-height: 1.85;
            color: #5f6368;
        }

        .article-body {
            margin-top: 32px;
            display: grid;
            gap: 22px;
        }

        .article-body h2,
        .article-body h3 {
            margin: 0;
            color: #202124;
            letter-spacing: -0.04em;
        }

        .article-body p {
            margin: 0;
            line-height: 1.9;
            color: #3f4a5a;
        }

        .article-figure {
            display: grid;
            gap: 10px;
        }

        .article-figure img {
            width: 100%;
            border-radius: 20px;
            object-fit: cover;
            background: #e5e7eb;
        }

        .article-figure figcaption {
            color: #6b7280;
            font-size: 0.94rem;
        }

        .article-loading,
        .article-error {
            margin-top: 28px;
            padding: 24px;
            border-radius: 24px;
            background: #ffffff;
            border: 1px solid rgba(19, 42, 74, 0.08);
            color: #5f6368;
        }
    </style>
</head>
<body>
    <div class="article-shell">
        <div class="article-topbar">
            <img src="img/logoAiScalerCenter.png" alt="AiScaler Center Logo">
            <a href="<?= htmlspecialchars(appPanelUrl('entradas-del-blog'), ENT_QUOTES, 'UTF-8'); ?>">Volver al panel</a>
        </div>

        <div id="article-loading" class="article-loading">Cargando articulo...</div>
        <div id="article-error" class="article-error" hidden>No pudimos cargar este articulo.</div>
        <article id="article-card" class="article-card" hidden>
            <img id="article-cover" class="article-cover" alt="" hidden>
            <h1 id="article-title"></h1>
            <p id="article-meta" class="article-meta"></p>
            <p id="article-excerpt" class="article-excerpt"></p>
            <div id="article-body" class="article-body"></div>
        </article>
    </div>

    <script>
        window.BLOG_PAGE_CONFIG = <?= json_encode([
            'supabaseUrl' => $projectUrl,
            'supabaseKey' => $supabasePublicKey,
            'slug' => $slug,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    </script>
    <script type="module">
        import { createClient } from 'https://esm.sh/@supabase/supabase-js@2';

        const config = window.BLOG_PAGE_CONFIG ?? {};
        const loading = document.getElementById('article-loading');
        const error = document.getElementById('article-error');
        const card = document.getElementById('article-card');
        const title = document.getElementById('article-title');
        const meta = document.getElementById('article-meta');
        const excerpt = document.getElementById('article-excerpt');
        const cover = document.getElementById('article-cover');
        const body = document.getElementById('article-body');

        if (!config.supabaseUrl || !config.supabaseKey || !config.slug) {
            showError('La configuracion del articulo no esta completa.');
        } else {
            const supabase = createClient(config.supabaseUrl, config.supabaseKey);
            void loadArticle(supabase, config.slug);
        }

        async function loadArticle(supabase, slug) {
            const { data, error: requestError } = await supabase
                .from('blog_entries')
                .select('title, excerpt, cover_image_url, content_blocks, published_at, author_name, view_count')
                .eq('slug', slug)
                .eq('status', 'published')
                .single();

            if (requestError || !data) {
                showError('No encontramos un articulo publicado con ese slug.');
                return;
            }

            title.textContent = data.title ?? 'Articulo';
            excerpt.textContent = data.excerpt ?? '';
            meta.textContent = buildMeta(data);

            if (data.cover_image_url) {
                cover.src = data.cover_image_url;
                cover.hidden = false;
            }

            body.innerHTML = renderBlocks(Array.isArray(data.content_blocks) ? data.content_blocks : []);

            loading.hidden = true;
            error.hidden = true;
            card.hidden = false;

            void supabase.rpc('increment_blog_entry_view', { entry_slug: slug });
        }

        function buildMeta(article) {
            const parts = [];

            if (article.author_name) {
                parts.push(article.author_name);
            }

            if (article.published_at) {
                parts.push(new Intl.DateTimeFormat('es-MX', { dateStyle: 'long' }).format(new Date(article.published_at)));
            }

            if (article.view_count !== null && article.view_count !== undefined) {
                parts.push(`${Number(article.view_count).toLocaleString('es-MX')} visitas`);
            }

            return parts.join(' • ');
        }

        function renderBlocks(blocks) {
            if (blocks.length === 0) {
                return '<p>Este articulo aun no tiene contenido.</p>';
            }

            return blocks.map((block) => {
                if (block.type === 'heading') {
                    const tag = block.level === 'h3' ? 'h3' : 'h2';
                    return `<${tag}>${escapeHtml(block.content ?? '')}</${tag}>`;
                }

                if (block.type === 'image') {
                    if (!block.src) {
                        return '';
                    }

                    return `
                        <figure class="article-figure">
                            <img src="${block.src}" alt="${escapeHtml(block.alt ?? '')}">
                            ${block.caption ? `<figcaption>${escapeHtml(block.caption)}</figcaption>` : ''}
                        </figure>
                    `;
                }

                return `<p>${escapeHtml(block.content ?? '')}</p>`;
            }).join('');
        }

        function escapeHtml(value) {
            return String(value)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        function showError(message) {
            loading.hidden = true;
            card.hidden = true;
            error.hidden = false;
            error.textContent = message;
        }
    </script>
</body>
</html>
