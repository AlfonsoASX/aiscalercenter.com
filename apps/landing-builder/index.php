<?php
declare(strict_types=1);

use AiScaler\LandingPages\LandingPageRepository;

require_once __DIR__ . '/../../modules/landing-pages/bootstrap.php';

$toolContext = is_array($toolRuntimeContext ?? null) ? $toolRuntimeContext : [];
$accessToken = trim((string) ($toolContext['access_token'] ?? ''));
$userId = trim((string) ($toolContext['user_id'] ?? ''));
$userEmail = trim((string) ($toolContext['user_email'] ?? ''));
$projectContext = is_array($toolContext['project'] ?? null) ? $toolContext['project'] : [];
$activeProjectId = trim((string) ($projectContext['id'] ?? ''));
$activeProjectName = '';
$repository = new LandingPageRepository();
$notice = null;
$error = null;
$project = null;
$pages = [];
$mode = trim((string) ($_GET['builder'] ?? 'list'));
$currentPage = null;

try {
    if ($accessToken === '' || $userId === '') {
        throw new RuntimeException('No encontramos la sesion segura para guardar landing pages. Vuelve a abrir la herramienta desde el panel.');
    }

    if ($activeProjectId === '') {
        throw new RuntimeException('Selecciona un proyecto antes de crear landing pages.');
    }

    $project = $repository->findProject($accessToken, $activeProjectId);

    if (!is_array($project)) {
        throw new RuntimeException('No encontramos el proyecto activo para guardar landing pages.');
    }

    $activeProjectName = (string) ($project['name'] ?? 'Proyecto');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $postAction = trim((string) ($_POST['page_action'] ?? ''));

        if ($postAction === 'delete_page') {
            $pageId = trim((string) ($_POST['page_id'] ?? ''));

            if ($pageId === '') {
                throw new InvalidArgumentException('No encontramos la landing que intentas eliminar.');
            }

            $repository->softDeletePage($accessToken, $pageId, $activeProjectId);
            $notice = ['type' => 'success', 'message' => 'Landing eliminada correctamente.'];
            $mode = 'list';
        } else {
            $currentPage = landingBuilderStateFromPost($_POST);
            $builderAction = trim((string) ($_POST['builder_action'] ?? 'save'));
            $status = $builderAction === 'publish' ? 'published' : (string) ($currentPage['status'] ?? 'draft');
            $payload = landingBuilderPayloadForSave($currentPage, $activeProjectId, $userId, $status);
            $currentPage = $repository->savePage($accessToken, $payload);
            $notice = [
                'type' => 'success',
                'message' => $status === 'published' ? 'Landing publicada y lista para compartir.' : 'Landing guardada correctamente.',
            ];
            $mode = 'edit';
        }
    }

    if ($mode === 'new' && $currentPage === null) {
        $currentPage = landingBuilderEmptyPage();
    }

    if ($mode === 'edit' && $currentPage === null) {
        $pageId = trim((string) ($_GET['id'] ?? ''));

        if ($pageId === '') {
            $currentPage = landingBuilderEmptyPage();
        } else {
            $loadedPage = $repository->findPage($accessToken, $pageId, $activeProjectId);

            if (!is_array($loadedPage)) {
                throw new RuntimeException('No encontramos la landing solicitada.');
            }

            $currentPage = landingBuilderNormalizePage($loadedPage);
        }
    }

    $pages = $repository->listPages($accessToken, $activeProjectId);
} catch (Throwable $exception) {
    $error = normalizeLandingBuilderException($exception);
    $mode = $mode === 'edit' || $mode === 'new' ? $mode : 'list';
}

$isEditorMode = ($mode === 'edit' || $mode === 'new') && is_array($currentPage);
?>
<div class="landing-builder-page">
    <?php if (!$isEditorMode): ?>
        <header class="landing-builder-hero">
            <div>
                <p class="landing-builder-eyebrow">Diseñar</p>
                <h1>Creador de landing pages</h1>
                <p>Construye una pagina de aterrizaje visualmente, con bloques editables, vista final en vivo y publicacion sin login.</p>
            </div>

            <a href="tool.php?launch=<?= htmlspecialchars(rawurlencode((string) ($toolContext['launch_token'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>&builder=new" class="landing-builder-primary">
                <span class="material-symbols-rounded">add_circle</span>
                <span>Nueva landing</span>
            </a>
        </header>
    <?php endif; ?>

    <?php if ($error !== null): ?>
        <div class="landing-builder-notice landing-builder-notice--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (is_array($notice)): ?>
        <div class="landing-builder-notice landing-builder-notice--<?= htmlspecialchars((string) ($notice['type'] ?? 'success'), ENT_QUOTES, 'UTF-8'); ?>">
            <?= htmlspecialchars((string) ($notice['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if ($isEditorMode): ?>
        <?= landingBuilderRenderEditor($currentPage, $toolContext); ?>
    <?php else: ?>
        <?= landingBuilderRenderList($pages, $toolContext); ?>
    <?php endif; ?>
</div>

<?php
function landingBuilderEmptyPage(): array
{
    return [
        'id' => '',
        'title' => 'Landing para mi oferta',
        'description' => 'Pagina de aterrizaje lista para publicar.',
        'slug' => '',
        'status' => 'draft',
        'public_id' => '',
        'blocks' => landingBuilderDefaultBlocks(),
        'settings' => landingBuilderDefaultSettings(),
    ];
}

function landingBuilderDefaultSettings(): array
{
    return [
        'brand_name' => 'AiScaler',
        'primary_color' => '#d93025',
        'accent_color' => '#1a73e8',
        'background_color' => '#fff7f5',
        'surface_color' => '#ffffff',
        'text_color' => '#202124',
        'font_family' => 'Roboto',
        'seo_title' => '',
        'seo_description' => '',
        'og_image' => '',
        'canonical_url' => '',
    ];
}

function landingBuilderDefaultBlocks(): array
{
    return [
        [
            'id' => generateLandingBlockId(),
            'type' => 'top',
            'brand_name' => 'AiScaler',
            'logo_url' => '',
            'items' => [
                ['title' => 'Inicio', 'body' => '#inicio'],
                ['title' => 'Beneficios', 'body' => '#beneficios'],
                ['title' => 'Contacto', 'body' => '#contacto'],
            ],
            'button_label' => 'Empezar',
            'button_url' => '#contacto',
            'background_image_url' => '',
            'background_color' => '#ffffff',
            'background_opacity' => '0',
        ],
        [
            'id' => generateLandingBlockId(),
            'type' => 'hero',
            'eyebrow' => 'Nueva oferta',
            'heading' => 'Convierte mas visitantes en clientes',
            'body' => 'Presenta tu propuesta con una landing clara, visual y lista para compartir.',
            'primary_label' => 'Quiero empezar',
            'primary_url' => '#contacto',
            'secondary_label' => 'Ver beneficios',
            'secondary_url' => '#beneficios',
            'image_url' => '',
            'background_image_url' => '',
            'background_color' => '#fff7f5',
            'background_opacity' => '0',
        ],
        [
            'id' => generateLandingBlockId(),
            'type' => 'feature_grid',
            'eyebrow' => 'Beneficios',
            'heading' => 'Todo lo importante en una sola pagina',
            'body' => 'Explica por que tu oferta importa sin obligar al usuario a pensar demasiado.',
            'items' => [
                ['title' => 'Mensaje claro', 'body' => 'Una promesa principal visible desde el primer scroll.'],
                ['title' => 'Prueba rapida', 'body' => 'Bloques para mostrar beneficios, diferenciadores y siguiente paso.'],
                ['title' => 'Accion directa', 'body' => 'Botones y llamadas a la accion listas para convertir.'],
            ],
            'background_image_url' => '',
            'background_color' => '#ffffff',
            'background_opacity' => '0',
        ],
        [
            'id' => generateLandingBlockId(),
            'type' => 'cta',
            'eyebrow' => 'Siguiente paso',
            'heading' => 'Publica una version simple y mejora con datos reales',
            'body' => 'Evita construir de mas. Lanza, mide y ajusta la pagina con claridad.',
            'button_label' => 'Solicitar informacion',
            'button_url' => '#contacto',
            'background_image_url' => '',
            'background_color' => '#fff7f5',
            'background_opacity' => '0',
        ],
        [
            'id' => generateLandingBlockId(),
            'type' => 'footer',
            'brand_name' => 'AiScaler',
            'body' => 'Una landing clara para presentar tu oferta y convertir visitantes en oportunidades.',
            'items' => [
                ['title' => 'Terminos', 'body' => 'terminos-y-condiciones.php'],
                ['title' => 'Privacidad', 'body' => 'aviso-de-privacidad.php'],
            ],
            'copyright' => '(c) 2026 AiScaler. Todos los derechos reservados.',
            'background_image_url' => '',
            'background_color' => '#ffffff',
            'background_opacity' => '0',
        ],
    ];
}

function landingBuilderNormalizePage(array $page): array
{
    $blocks = $page['blocks'] ?? [];
    $settings = $page['settings'] ?? [];

    if (is_string($blocks)) {
        $decoded = json_decode($blocks, true);
        $blocks = is_array($decoded) ? $decoded : [];
    }

    if (is_string($settings)) {
        $decoded = json_decode($settings, true);
        $settings = is_array($decoded) ? $decoded : [];
    }

    $normalized = landingBuilderEmptyPage();

    return [
        ...$normalized,
        'id' => (string) ($page['id'] ?? ''),
        'title' => (string) ($page['title'] ?? ''),
        'description' => (string) ($page['description'] ?? ''),
        'slug' => (string) ($page['slug'] ?? ''),
        'status' => (string) ($page['status'] ?? 'draft'),
        'public_id' => (string) ($page['public_id'] ?? ''),
        'blocks' => landingBuilderNormalizeBlocks(is_array($blocks) ? $blocks : []),
        'settings' => landingBuilderNormalizeSettings(is_array($settings) ? $settings : []),
    ];
}

function landingBuilderNormalizeBlocks(array $blocks): array
{
    $normalizedBlocks = [];

    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }

        $type = (string) ($block['type'] ?? '');

        if (!in_array($type, ['top', 'hero', 'feature_grid', 'split', 'testimonial', 'cta', 'footer'], true)) {
            continue;
        }

        $normalized = [
            'id' => trim((string) ($block['id'] ?? '')) ?: generateLandingBlockId(),
            'type' => $type,
        ];

        foreach ($block as $key => $value) {
            if (in_array($key, ['id', 'type'], true)) {
                continue;
            }

            if ($key === 'items') {
                $normalized[$key] = landingBuilderNormalizeItems(is_array($value) ? $value : []);
                continue;
            }

            if (!is_scalar($value)) {
                $normalized[$key] = '';
                continue;
            }

            $normalized[$key] = landingBuilderNormalizeBlockScalar($key, (string) $value);
        }

        $normalizedBlocks[] = $normalized;
    }

    return $normalizedBlocks !== [] ? $normalizedBlocks : landingBuilderDefaultBlocks();
}

function landingBuilderNormalizeSettings(array $settings): array
{
    $normalized = array_merge(landingBuilderDefaultSettings(), $settings);

    if (isset($normalized['og_image'])) {
        $normalized['og_image'] = landingBuilderSanitizeImageUrl((string) $normalized['og_image']);
    }

    return $normalized;
}

function landingBuilderNormalizeBlockScalar(string $key, string $value): string
{
    if (in_array($key, ['image_url', 'logo_url', 'background_image_url'], true)) {
        return landingBuilderSanitizeImageUrl($value);
    }

    if ($key === 'background_opacity') {
        return (string) max(0, min(100, (int) $value));
    }

    return trim($value);
}

function landingBuilderSanitizeImageUrl(string $value): string
{
    $trimmed = trim($value);

    if ($trimmed === '') {
        return '';
    }

    if (str_starts_with(strtolower($trimmed), 'data:') || !landingBuilderIsManagedAssetUrl($trimmed)) {
        return '';
    }

    return $trimmed;
}

function landingBuilderIsManagedAssetUrl(string $value): bool
{
    return appStorageIsManagedPublicUrl($value);
}

function landingBuilderNormalizeItems(array $items): array
{
    $normalizedItems = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $title = trim((string) ($item['title'] ?? ''));
        $body = trim((string) ($item['body'] ?? ''));

        if ($title === '' && $body === '') {
            continue;
        }

        $normalizedItems[] = [
            'title' => $title,
            'body' => $body,
        ];
    }

    return $normalizedItems;
}

function landingBuilderStateFromPost(array $post): array
{
    $blocksJson = (string) ($post['blocks_json'] ?? '[]');
    $settingsJson = (string) ($post['settings_json'] ?? '{}');
    $blocks = json_decode($blocksJson, true);
    $settings = json_decode($settingsJson, true);

    return landingBuilderNormalizePage([
        'id' => trim((string) ($post['id'] ?? '')),
        'title' => trim((string) ($post['title'] ?? '')),
        'description' => trim((string) ($post['description'] ?? '')),
        'slug' => trim((string) ($post['slug'] ?? '')),
        'status' => trim((string) ($post['status'] ?? 'draft')) ?: 'draft',
        'public_id' => trim((string) ($post['public_id'] ?? '')),
        'blocks' => is_array($blocks) ? $blocks : [],
        'settings' => is_array($settings) ? $settings : [],
    ]);
}

function landingBuilderPayloadForSave(array $page, string $projectId, string $userId, string $status): array
{
    $title = trim((string) ($page['title'] ?? ''));
    $slug = landingBuilderResolveInternalSlug($page, $title);

    if ($title === '') {
        throw new InvalidArgumentException('La landing necesita un titulo.');
    }

    $payload = [
        'project_id' => trim($projectId),
        'owner_user_id' => $userId,
        'slug' => $slug,
        'title' => $title,
        'description' => trim((string) ($page['description'] ?? '')),
        'status' => in_array($status, ['draft', 'published', 'archived'], true) ? $status : 'draft',
        'blocks' => landingBuilderNormalizeBlocks((array) ($page['blocks'] ?? [])),
        'settings' => landingBuilderNormalizeSettings((array) ($page['settings'] ?? [])),
        'metadata' => [
            'builder' => 'landing-builder',
            'saved_at' => gmdate('c'),
        ],
    ];

    if ($payload['status'] === 'published') {
        $payload['published_at'] = gmdate('c');
    }

    $id = trim((string) ($page['id'] ?? ''));

    if ($id !== '') {
        $payload['id'] = $id;
    }

    return $payload;
}

function landingBuilderResolveInternalSlug(array $page, string $title): string
{
    $existingSlug = trim((string) ($page['slug'] ?? ''));

    if ($existingSlug !== '') {
        return normalizeLandingSlug($existingSlug);
    }

    $seed = trim((string) ($page['id'] ?? '')) ?: trim((string) ($page['public_id'] ?? '')) ?: bin2hex(random_bytes(4));

    return normalizeLandingSlug($title) . '-' . substr(hash('sha1', $seed), 0, 8);
}

function landingBuilderRenderList(array $pages, array $toolContext): string
{
    $launch = rawurlencode((string) ($toolContext['launch_token'] ?? ''));

    ob_start();
    ?>
    <section class="landing-builder-list">
        <?php if ($pages === []): ?>
            <div class="landing-builder-empty">
                <span class="material-symbols-rounded">web</span>
                <h2>Aun no tienes landing pages</h2>
                <p>Crea una primera version con bloques visuales y publicala cuando este lista.</p>
                <a href="tool.php?launch=<?= htmlspecialchars($launch, ENT_QUOTES, 'UTF-8'); ?>&builder=new" class="landing-builder-primary">
                    <span class="material-symbols-rounded">add_circle</span>
                    <span>Crear landing</span>
                </a>
            </div>
        <?php else: ?>
            <div class="landing-builder-page-grid">
                <?php foreach ($pages as $page): ?>
                    <?php
                    $normalized = landingBuilderNormalizePage($page);
                    $publicId = (string) ($normalized['public_id'] ?? '');
                    $isPublished = (string) ($normalized['status'] ?? 'draft') === 'published';
                    ?>
                    <article class="landing-builder-page-card">
                        <div class="landing-builder-page-preview">
                            <span><?= htmlspecialchars((string) ($normalized['settings']['brand_name'] ?? 'AiScaler'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <strong><?= htmlspecialchars((string) ($normalized['title'] ?? 'Landing'), ENT_QUOTES, 'UTF-8'); ?></strong>
                        </div>
                        <div>
                            <span class="landing-builder-status <?= $isPublished ? 'is-published' : ''; ?>">
                                <?= $isPublished ? 'Publicada' : 'Borrador'; ?>
                            </span>
                            <h2><?= htmlspecialchars((string) ($normalized['title'] ?? 'Landing'), ENT_QUOTES, 'UTF-8'); ?></h2>
                            <p><?= htmlspecialchars((string) ($normalized['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <div class="landing-builder-card-actions">
                            <a href="tool.php?launch=<?= htmlspecialchars($launch, ENT_QUOTES, 'UTF-8'); ?>&builder=edit&id=<?= htmlspecialchars(rawurlencode((string) ($page['id'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>" class="landing-builder-secondary">
                                <span class="material-symbols-rounded">edit</span>
                                <span>Editar</span>
                            </a>
                            <?php if ($isPublished && $publicId !== ''): ?>
                                <a href="<?= htmlspecialchars(landingShareUrl($publicId), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noreferrer noopener" class="landing-builder-secondary">
                                    <span class="material-symbols-rounded">open_in_new</span>
                                    <span>Ver</span>
                                </a>
                            <?php endif; ?>
                            <form method="post" onsubmit="return confirm('¿Eliminar esta landing?');">
                                <input type="hidden" name="page_action" value="delete_page">
                                <input type="hidden" name="page_id" value="<?= htmlspecialchars((string) ($page['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit" class="landing-builder-danger">
                                    <span class="material-symbols-rounded">delete</span>
                                    <span>Borrar</span>
                                </button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php

    return (string) ob_get_clean();
}

function landingBuilderRenderEditor(array $page, array $toolContext): string
{
    $launch = rawurlencode((string) ($toolContext['launch_token'] ?? ''));
    $publicId = (string) ($page['public_id'] ?? '');
    $status = (string) ($page['status'] ?? 'draft');
    $isPublished = $status === 'published' && $publicId !== '';
    $shareUrl = $isPublished ? landingShareUrl($publicId) : '';
    $blocksJson = json_encode($page['blocks'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    $settingsJson = json_encode(landingBuilderNormalizeSettings((array) ($page['settings'] ?? [])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';

    ob_start();
    ?>
    <section
        class="landing-builder-editor"
        data-landing-builder
        data-upload-url="tool-action.php?launch=<?= htmlspecialchars($launch, ENT_QUOTES, 'UTF-8'); ?>"
        data-storage-public-base="<?= htmlspecialchars(appStoragePublicBaseUrl(), ENT_QUOTES, 'UTF-8'); ?>"
    >
        <form method="post" class="landing-builder-form" data-landing-form>
            <input type="hidden" name="page_action" value="save_page">
            <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($page['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="status" value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="public_id" value="<?= htmlspecialchars($publicId, ENT_QUOTES, 'UTF-8'); ?>">
            <textarea id="landing-blocks-json" name="blocks_json" hidden><?= htmlspecialchars($blocksJson, ENT_QUOTES, 'UTF-8'); ?></textarea>
            <textarea id="landing-settings-json" name="settings_json" hidden><?= htmlspecialchars($settingsJson, ENT_QUOTES, 'UTF-8'); ?></textarea>

            <header class="landing-builder-topbar">
                <div class="landing-builder-doc">
                    <a href="tool.php?launch=<?= htmlspecialchars($launch, ENT_QUOTES, 'UTF-8'); ?>" class="landing-builder-icon-action" aria-label="Volver a landings">
                        <span class="material-symbols-rounded">arrow_back</span>
                    </a>
                    <div>
                        <strong data-landing-doc-title><?= htmlspecialchars((string) ($page['title'] ?? 'Landing sin titulo'), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <small><?= $isPublished ? 'Publicada' : 'Borrador'; ?></small>
                    </div>
                </div>

                <div class="landing-builder-top-actions">
                    <?php if ($shareUrl !== ''): ?>
                        <a href="<?= htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noreferrer noopener" class="landing-builder-secondary landing-builder-secondary--compact">
                            <span class="material-symbols-rounded">visibility</span>
                            <span>Preview</span>
                        </a>
                    <?php endif; ?>
                    <button type="submit" name="builder_action" value="save" class="landing-builder-secondary landing-builder-secondary--compact">
                        <span class="material-symbols-rounded">save</span>
                        <span>Guardar</span>
                    </button>
                    <button type="submit" name="builder_action" value="publish" class="landing-builder-primary landing-builder-primary--compact">
                        <span class="material-symbols-rounded">rocket_launch</span>
                        <span>Publicar</span>
                    </button>
                </div>
            </header>

            <div class="landing-builder-meta-strip">
                <label>
                    <span>Titulo</span>
                    <input type="text" name="title" value="<?= htmlspecialchars((string) ($page['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Landing para mi oferta" data-landing-title-input required>
                </label>
                <label>
                    <span>Descripcion interna</span>
                    <input type="text" name="description" value="<?= htmlspecialchars((string) ($page['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Para identificarla en el panel">
                </label>
            </div>

            <div class="landing-builder-workbench">
                <aside class="landing-builder-palette" aria-label="Bloques disponibles">
                    <strong>Bloques</strong>
                    <button type="button" data-landing-add-block="top"><span class="material-symbols-rounded">web_asset</span><span>Top</span></button>
                    <button type="button" data-landing-add-block="hero"><span class="material-symbols-rounded">auto_awesome</span><span>Hero</span></button>
                    <button type="button" data-landing-add-block="feature_grid"><span class="material-symbols-rounded">grid_view</span><span>Beneficios</span></button>
                    <button type="button" data-landing-add-block="split"><span class="material-symbols-rounded">view_column</span><span>Texto + imagen</span></button>
                    <button type="button" data-landing-add-block="testimonial"><span class="material-symbols-rounded">reviews</span><span>Testimonio</span></button>
                    <button type="button" data-landing-add-block="cta"><span class="material-symbols-rounded">ads_click</span><span>CTA</span></button>
                    <button type="button" data-landing-add-block="footer"><span class="material-symbols-rounded">vertical_align_bottom</span><span>Footer</span></button>
                </aside>

                <div class="landing-builder-preview-shell">
                    <div class="landing-builder-browserbar">
                        <span></span><span></span><span></span>
                        <strong>Vista en vivo</strong>
                    </div>
                    <div class="landing-builder-preview-frame">
                        <div class="landing-builder-canvas" data-landing-canvas></div>
                    </div>
                </div>

                <aside class="landing-builder-inspector" data-landing-inspector aria-label="Configuracion del bloque">
                    <div class="landing-builder-inspector-empty">
                        <span class="material-symbols-rounded">touch_app</span>
                        <h3>Selecciona un bloque</h3>
                        <p>Al hacer clic en una seccion, aqui apareceran sus controles.</p>
                    </div>
                </aside>
            </div>
        </form>
    </section>
    <script src="tool-asset.php?launch=<?= htmlspecialchars($launch, ENT_QUOTES, 'UTF-8'); ?>&asset=app.js"></script>
    <?php

    return (string) ob_get_clean();
}
