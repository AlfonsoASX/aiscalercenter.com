<?php
declare(strict_types=1);

use AiScaler\Analytics\AnalyticsRepository;

require_once __DIR__ . '/../../modules/analytics/bootstrap.php';

$toolContext = is_array($toolRuntimeContext ?? null) ? $toolRuntimeContext : [];
$repository = new AnalyticsRepository();
$error = null;
$project = null;
$landingRows = [];
$heatmapRows = [];

try {
    $resolvedContext = analyticsResolveToolContext($toolContext, 'Vision de Rayos X', $repository);
    $project = $resolvedContext['project'];
    $landingRows = $repository->listLandingPages($resolvedContext['access_token'], $resolvedContext['project_id']);
    $heatmapRows = $repository->listHeatmapPages($resolvedContext['access_token'], $resolvedContext['project_id']);
} catch (Throwable $exception) {
    $error = normalizeAnalyticsException($exception);
}

$heatmapMap = rayosXIndexSnapshots($heatmapRows);
$pagesWithInsights = 0;
$recordingsCount = 0;
$scrollDepths = [];

foreach ($landingRows as $landing) {
    $snapshot = $heatmapMap[(string) ($landing['id'] ?? '')] ?? null;

    if (is_array($snapshot)) {
        $pagesWithInsights++;
        $recordingsCount += count(rayosXNormalizeArray($snapshot['session_recordings'] ?? []));
        $scrollDepth = is_numeric($snapshot['avg_scroll_depth'] ?? null) ? (float) $snapshot['avg_scroll_depth'] : null;

        if ($scrollDepth !== null) {
            $scrollDepths[] = $scrollDepth;
        }
    }
}

$averageScroll = $scrollDepths === [] ? null : (array_sum($scrollDepths) / count($scrollDepths));
?>
<div class="analytics-app analytics-app--rayos-x">
    <section class="analytics-hero">
        <div class="analytics-hero-copy">
            <p class="analytics-eyebrow">Analiza</p>
            <h1>Vision de Rayos X</h1>
            <p>Descubre que pasa dentro de tus landings: donde hacen clic, hasta donde llegan y en que parte del recorrido se empiezan a enfriar.</p>
        </div>

        <?php if ($project !== null): ?>
            <span class="analytics-chip"><?= htmlspecialchars((string) ($project['name'] ?? 'Proyecto'), ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
    </section>

    <?php if ($error !== null): ?>
        <section class="analytics-empty">
            <strong><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></strong>
            <p>Cuando actives la captura de mapas de calor, aqui veras la lectura completa de cada landing.</p>
        </section>
    <?php else: ?>
        <section class="analytics-grid analytics-grid--four">
            <article class="analytics-card">
                <p class="analytics-eyebrow">Landings</p>
                <strong class="analytics-kpi-value"><?= analyticsFormatNumber(count($landingRows)); ?></strong>
                <p class="analytics-kpi-subtitle">Paginas disponibles para inspeccionar dentro del proyecto.</p>
            </article>

            <article class="analytics-card">
                <p class="analytics-eyebrow">Con Rayos X</p>
                <strong class="analytics-kpi-value"><?= analyticsFormatNumber($pagesWithInsights); ?></strong>
                <p class="analytics-kpi-subtitle">Paginas que ya tienen por lo menos una lectura de comportamiento.</p>
            </article>

            <article class="analytics-card">
                <p class="analytics-eyebrow">Scroll promedio</p>
                <strong class="analytics-kpi-value"><?= analyticsFormatPercent($averageScroll); ?></strong>
                <p class="analytics-kpi-subtitle">Profundidad promedio alcanzada antes de abandonar la pagina.</p>
            </article>

            <article class="analytics-card">
                <p class="analytics-eyebrow">Grabaciones</p>
                <strong class="analytics-kpi-value"><?= analyticsFormatNumber($recordingsCount); ?></strong>
                <p class="analytics-kpi-subtitle">Sesiones anonimas disponibles para revisar el recorrido.</p>
            </article>
        </section>

        <?php if ($landingRows === []): ?>
            <section class="analytics-empty">
                <strong>Todavia no hay landings en este proyecto</strong>
                <p>Publica al menos una landing desde Diseñar para comenzar a observar mapas de calor y grabaciones.</p>
            </section>
        <?php else: ?>
            <section class="analytics-grid analytics-grid--two">
                <?php foreach ($landingRows as $landing): ?>
                    <?php
                    $landingId = (string) ($landing['id'] ?? '');
                    $snapshot = $heatmapMap[$landingId] ?? null;
                    $viewCount = is_array($snapshot) ? (int) ($snapshot['total_views'] ?? 0) : (int) ($landing['view_count'] ?? 0);
                    $scrollDepth = is_array($snapshot) && is_numeric($snapshot['avg_scroll_depth'] ?? null)
                        ? (float) $snapshot['avg_scroll_depth']
                        : null;
                    $zones = is_array($snapshot) ? array_slice(rayosXNormalizeArray($snapshot['top_click_zones'] ?? []), 0, 4) : [];
                    $recordings = is_array($snapshot) ? rayosXNormalizeArray($snapshot['session_recordings'] ?? []) : [];
                    ?>
                    <article class="analytics-card">
                        <div class="analytics-card-head">
                            <div>
                                <h2><?= htmlspecialchars((string) ($landing['title'] ?? 'Landing'), ENT_QUOTES, 'UTF-8'); ?></h2>
                                <p><?= htmlspecialchars((string) ($landing['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <span class="analytics-pill analytics-pill--<?= (string) ($landing['status'] ?? '') === 'published' ? 'success' : 'warning'; ?>">
                                <?= htmlspecialchars((string) ucfirst((string) ($landing['status'] ?? 'draft')), ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </div>

                        <div class="analytics-zone-list">
                            <div class="analytics-zone-item">
                                <strong><?= analyticsFormatNumber($viewCount); ?> vistas</strong>
                                <span class="analytics-meta">Conteo total observado en la pagina.</span>
                            </div>
                            <div class="analytics-zone-item">
                                <strong><?= analyticsFormatPercent($scrollDepth, 'Sin lectura'); ?></strong>
                                <span class="analytics-meta">Profundidad media antes de salir de la pagina.</span>
                            </div>
                            <div class="analytics-zone-item">
                                <strong><?= analyticsFormatNumber(count($recordings)); ?> grabaciones</strong>
                                <span class="analytics-meta">Sesiones listas para revision anonima.</span>
                            </div>
                        </div>

                        <?php if ($zones === []): ?>
                            <div class="analytics-empty">
                                <strong>Sin zonas calientes aun</strong>
                                <p>Esta landing todavia no tiene suficientes eventos almacenados para mostrar hotspots.</p>
                            </div>
                        <?php else: ?>
                            <div class="analytics-zone-list">
                                <?php foreach ($zones as $zone): ?>
                                    <div class="analytics-zone-item">
                                        <strong><?= htmlspecialchars((string) ($zone['label'] ?? 'Zona activa'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <span class="analytics-meta">
                                            <?= analyticsFormatNumber((int) ($zone['clicks'] ?? 0)); ?> clics
                                            <?php if (trim((string) ($zone['area'] ?? '')) !== ''): ?>
                                                · <?= htmlspecialchars((string) ($zone['area'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php

function rayosXIndexSnapshots(array $rows): array
{
    $indexed = [];

    foreach ($rows as $row) {
        $landingId = trim((string) ($row['landing_page_id'] ?? ''));
        $key = $landingId !== '' ? $landingId : trim((string) ($row['page_path'] ?? ''));

        if ($key === '' || isset($indexed[$key])) {
            continue;
        }

        $indexed[$key] = $row;
    }

    return $indexed;
}

function rayosXNormalizeArray($value): array
{
    return is_array($value) ? $value : [];
}
