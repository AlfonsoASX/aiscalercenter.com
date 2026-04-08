<?php
declare(strict_types=1);

use AiScaler\Analytics\AnalyticsRepository;

require_once __DIR__ . '/../../modules/analytics/bootstrap.php';

$toolContext = is_array($toolRuntimeContext ?? null) ? $toolRuntimeContext : [];
$repository = new AnalyticsRepository();
$error = null;
$project = null;
$postRows = [];
$targetRows = [];
$registryRows = [];

try {
    $resolvedContext = analyticsResolveToolContext($toolContext, 'Rastreador Inteligente', $repository);
    $project = $resolvedContext['project'];
    $postRows = $repository->listScheduledPosts($resolvedContext['access_token'], $resolvedContext['project_id']);
    $targetRows = $repository->listScheduledTargets($resolvedContext['access_token'], $postRows);
    $registryRows = $repository->listUtmRegistry($resolvedContext['access_token'], $resolvedContext['project_id']);
} catch (Throwable $exception) {
    $error = normalizeAnalyticsException($exception);
}

$utmRows = utmTrackerBuildRows($postRows, $targetRows, $registryRows);
$trackedChannels = array_values(array_unique(array_filter(array_map(static fn(array $row): string => (string) ($row['provider_label'] ?? ''), $utmRows))));
?>
<div class="analytics-app analytics-app--utm">
    <section class="analytics-hero">
        <div class="analytics-hero-copy">
            <p class="analytics-eyebrow">Analiza</p>
            <h1>Rastreador Inteligente</h1>
            <p>Genera y ordena los enlaces con UTM para descubrir exactamente que post, canal o pieza creativa se llevo la atencion del cliente.</p>
        </div>

        <?php if ($project !== null): ?>
            <span class="analytics-chip"><?= htmlspecialchars((string) ($project['name'] ?? 'Proyecto'), ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
    </section>

    <?php if ($error !== null): ?>
        <section class="analytics-empty">
            <strong><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></strong>
            <p>El rastreador empezara a mostrar enlaces enriquecidos en cuanto el proyecto tenga publicaciones y destinos rastreables.</p>
        </section>
    <?php else: ?>
        <section class="analytics-grid analytics-grid--four">
            <article class="analytics-card">
                <p class="analytics-eyebrow">Enlaces listos</p>
                <strong class="analytics-kpi-value"><?= analyticsFormatNumber(count($utmRows)); ?></strong>
                <p class="analytics-kpi-subtitle">Publicaciones con URL util para rastreo inteligente.</p>
            </article>

            <article class="analytics-card">
                <p class="analytics-eyebrow">Canales</p>
                <strong class="analytics-kpi-value"><?= analyticsFormatNumber(count($trackedChannels)); ?></strong>
                <p class="analytics-kpi-subtitle"><?= $trackedChannels === [] ? 'Aun no hay canales con links rastreables.' : implode(', ', $trackedChannels); ?></p>
            </article>

            <article class="analytics-card">
                <p class="analytics-eyebrow">Publicaciones</p>
                <strong class="analytics-kpi-value"><?= analyticsFormatNumber(count($postRows)); ?></strong>
                <p class="analytics-kpi-subtitle">Piezas programadas dentro del proyecto.</p>
            </article>

            <article class="analytics-card">
                <p class="analytics-eyebrow">Modo</p>
                <strong class="analytics-kpi-value"><?= $registryRows !== [] ? 'Live' : 'Auto'; ?></strong>
                <p class="analytics-kpi-subtitle"><?= $registryRows !== [] ? 'Usando registros persistidos.' : 'Generando UTMs sobre la marcha desde Ejecutar.'; ?></p>
            </article>
        </section>

        <section class="analytics-table-shell">
            <div class="analytics-table-head">
                <div>
                    <h2>Links rastreados</h2>
                    <p>Todo listo para leer luego los resultados sin que la pyme tenga que pelearse con parametros manuales.</p>
                </div>
            </div>

            <?php if ($utmRows === []): ?>
                <div class="analytics-empty">
                    <strong>Aun no hay URLs para rastrear</strong>
                    <p>Agrega enlaces a tus publicaciones en Ejecutar para que este generador comience a etiquetarlos automaticamente.</p>
                </div>
            <?php else: ?>
                <table class="analytics-table">
                    <thead>
                        <tr>
                            <th>Publicacion</th>
                            <th>Canal</th>
                            <th>Campania</th>
                            <th>URL rastreada</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($utmRows as $row): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars((string) ($row['title'] ?? 'Publicacion'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <div class="analytics-meta"><?= htmlspecialchars((string) ($row['publication_type'] ?? 'post'), ENT_QUOTES, 'UTF-8'); ?></div>
                                </td>
                                <td><?= htmlspecialchars((string) ($row['provider_label'] ?? 'Canal'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <div class="analytics-inline-list">
                                        <span class="analytics-pill"><?= htmlspecialchars((string) ($row['utm_source'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="analytics-pill"><?= htmlspecialchars((string) ($row['utm_campaign'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="analytics-pill"><?= htmlspecialchars((string) ($row['utm_content'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </td>
                                <td><code class="analytics-code"><?= htmlspecialchars((string) ($row['tracked_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
<?php

function utmTrackerBuildRows(array $posts, array $targets, array $registry): array
{
    if ($registry !== []) {
        return array_values(array_map(static function (array $row): array {
            return [
                'title' => (string) ($row['content_name'] ?? $row['campaign_name'] ?? 'Publicacion'),
                'provider_label' => analyticsProviderLabel((string) ($row['provider_key'] ?? '')),
                'publication_type' => (string) ($row['metadata']['publication_type'] ?? 'post'),
                'tracked_url' => (string) ($row['tracked_url'] ?? ''),
                'utm_source' => (string) ($row['utm_source'] ?? ''),
                'utm_campaign' => (string) ($row['utm_campaign'] ?? ''),
                'utm_content' => (string) ($row['utm_content'] ?? ''),
            ];
        }, $registry));
    }

    $postsById = [];

    foreach ($posts as $post) {
        $postId = trim((string) ($post['id'] ?? ''));

        if ($postId !== '') {
            $postsById[$postId] = $post;
        }
    }

    $rows = [];

    foreach ($targets as $target) {
        $postId = trim((string) ($target['post_id'] ?? ''));
        $post = $postsById[$postId] ?? null;
        $destinationUrl = analyticsExtractTargetUrl($target);

        if (!is_array($post) || $destinationUrl === '') {
            continue;
        }

        $providerKey = trim((string) ($target['provider_key'] ?? ''));
        $providerLabel = analyticsProviderLabel($providerKey);
        $postTitle = trim((string) ($post['title'] ?? '')) ?: 'Publicacion programada';
        $scheduledAt = trim((string) ($post['scheduled_at'] ?? ''));
        $campaign = analyticsSlugify($postTitle, 'campania');
        $content = analyticsSlugify(((string) ($target['publication_type'] ?? 'post')) . '-' . ($scheduledAt !== '' ? date('Ymd', strtotime($scheduledAt) ?: time()) : 'sin-fecha'), 'contenido');
        $utmSource = analyticsSlugify($providerLabel, 'canal');
        $utmMedium = utmTrackerMediumForProvider($providerKey);
        $trackedUrl = analyticsAppendQueryParams($destinationUrl, [
            'utm_source' => $utmSource,
            'utm_medium' => $utmMedium,
            'utm_campaign' => $campaign,
            'utm_content' => $content,
        ]);

        $rows[] = [
            'title' => $postTitle,
            'provider_label' => $providerLabel,
            'publication_type' => (string) ($target['publication_type'] ?? 'post'),
            'tracked_url' => $trackedUrl,
            'utm_source' => $utmSource,
            'utm_campaign' => $campaign,
            'utm_content' => $content,
        ];
    }

    return $rows;
}

function utmTrackerMediumForProvider(string $providerKey): string
{
    $normalized = strtolower(trim($providerKey));

    if (str_contains($normalized, 'google')) {
        return 'local';
    }

    if (str_contains($normalized, 'mercado') || str_contains($normalized, 'amazon')) {
        return 'marketplace';
    }

    return 'social';
}
