<?php
declare(strict_types=1);

use AiScaler\Analytics\AnalyticsRepository;

require_once __DIR__ . '/../../modules/analytics/bootstrap.php';

$toolContext = is_array($toolRuntimeContext ?? null) ? $toolRuntimeContext : [];
$repository = new AnalyticsRepository();
$error = null;
$project = null;
$trafficRows = [];
$leadRows = [];
$landingRows = [];
$formRows = [];
$postRows = [];
$targetRows = [];

try {
    $resolvedContext = analyticsResolveToolContext($toolContext, 'Semaforo de Trafico', $repository);
    $project = $resolvedContext['project'];
    $trafficRows = trafficSemaforoRecentRows($repository->listTrafficSnapshots($resolvedContext['access_token'], $resolvedContext['project_id']));
    $leadRows = $repository->listLeads($resolvedContext['access_token'], $resolvedContext['project_id']);
    $landingRows = $repository->listLandingPages($resolvedContext['access_token'], $resolvedContext['project_id']);
    $formRows = $repository->listForms($resolvedContext['access_token'], $resolvedContext['project_id']);
    $postRows = $repository->listScheduledPosts($resolvedContext['access_token'], $resolvedContext['project_id']);
    $targetRows = $repository->listScheduledTargets($resolvedContext['access_token'], $postRows);
} catch (Throwable $exception) {
    $error = normalizeAnalyticsException($exception);
}

$sourceRows = $trafficRows !== []
    ? trafficSemaforoAggregateSources($trafficRows)
    : trafficSemaforoFallbackSources($leadRows, $targetRows);
$topSources = array_slice($sourceRows, 0, 6);
$totalClicks = trafficSemaforoSum($trafficRows, 'clicks');
$totalSessions = trafficSemaforoSum($trafficRows, 'sessions');
$totalBounces = trafficSemaforoSum($trafficRows, 'bounces');
$bounceRate = $totalSessions > 0 ? ($totalBounces / $totalSessions) * 100 : null;
$connectedProviders = trafficSemaforoConnectedProviders($targetRows);
$publishedLandings = count(array_filter($landingRows, static fn(array $row): bool => (string) ($row['status'] ?? '') === 'published'));
$activeForms = count(array_filter($formRows, static fn(array $row): bool => in_array((string) ($row['status'] ?? ''), ['draft', 'published'], true)));
?>
<div class="analytics-app analytics-app--traffic">
    <section class="analytics-hero">
        <div class="analytics-hero-copy">
            <p class="analytics-eyebrow">Analiza</p>
            <h1>Semaforo de Trafico</h1>
            <p>Consolida el pulso del proyecto en una sola vista: fuentes detectadas, landings activas y senales de rebote para que no tengas que brincar entre herramientas.</p>
        </div>

        <?php if ($project !== null): ?>
            <span class="analytics-chip"><?= htmlspecialchars((string) ($project['name'] ?? 'Proyecto'), ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
    </section>

    <?php if ($error !== null): ?>
        <section class="analytics-empty">
            <strong><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></strong>
            <p>Cuando la estructura de analitica este lista, aqui veras el consolidado del proyecto.</p>
        </section>
    <?php else: ?>
        <section class="analytics-grid analytics-grid--four">
            <article class="analytics-card">
                <p class="analytics-eyebrow">Clics</p>
                <strong class="analytics-kpi-value"><?= $totalClicks > 0 ? analyticsFormatNumber($totalClicks) : '--'; ?></strong>
                <p class="analytics-kpi-subtitle"><?= $trafficRows !== [] ? 'Ultimos 14 dias capturados' : 'Pendiente de telemetria de trafico'; ?></p>
            </article>

            <article class="analytics-card">
                <p class="analytics-eyebrow">Sesiones</p>
                <strong class="analytics-kpi-value"><?= $totalSessions > 0 ? analyticsFormatNumber($totalSessions) : '--'; ?></strong>
                <p class="analytics-kpi-subtitle">Visitas consolidadas hacia landings y activos rastreados.</p>
            </article>

            <article class="analytics-card">
                <p class="analytics-eyebrow">Rebote</p>
                <strong class="analytics-kpi-value"><?= analyticsFormatPercent($bounceRate); ?></strong>
                <p class="analytics-kpi-subtitle">Gente que entra y sale sin profundizar en tu activo.</p>
            </article>

            <article class="analytics-card">
                <p class="analytics-eyebrow">Fuentes listas</p>
                <strong class="analytics-kpi-value"><?= analyticsFormatNumber(count($connectedProviders)); ?></strong>
                <p class="analytics-kpi-subtitle"><?= analyticsFormatNumber($publishedLandings); ?> landings publicadas y <?= analyticsFormatNumber($activeForms); ?> formularios activos.</p>
            </article>
        </section>

        <section class="analytics-split">
            <article class="analytics-card">
                <div class="analytics-card-head">
                    <div>
                        <h2>Top Fuentes de Trafico</h2>
                        <p><?= $trafficRows !== [] ? 'Ordenadas por clicks y sesiones.' : 'Mientras conectamos trafico real, te mostramos las fuentes que ya generan senal dentro del proyecto.'; ?></p>
                    </div>
                    <span class="analytics-pill analytics-pill--<?= $trafficRows !== [] ? 'success' : 'warning'; ?>">
                        <?= $trafficRows !== [] ? 'Live' : 'Modo preparacion'; ?>
                    </span>
                </div>

                <?php if ($topSources === []): ?>
                    <div class="analytics-empty">
                        <strong>Aun no hay fuentes registradas</strong>
                        <p>Programa publicaciones, activa formularios o conecta capturas para empezar a poblar este semaforo.</p>
                    </div>
                <?php else: ?>
                    <div class="analytics-bars">
                        <?php $maxVolume = max(array_map(static fn(array $row): int => (int) ($row['volume'] ?? 0), $topSources)); ?>
                        <?php foreach ($topSources as $row): ?>
                            <?php $width = $maxVolume > 0 ? (((int) ($row['volume'] ?? 0) / $maxVolume) * 100) : 0; ?>
                            <div class="analytics-bar">
                                <div class="analytics-bar-head">
                                    <strong><?= htmlspecialchars((string) ($row['label'] ?? 'Fuente'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <span class="analytics-muted">
                                        <?= analyticsProviderLabel((string) ($row['channel'] ?? '')); ?>
                                        ·
                                        <?= analyticsFormatNumber((int) ($row['volume'] ?? 0)); ?>
                                    </span>
                                </div>
                                <div class="analytics-bar-track">
                                    <div class="analytics-bar-fill" style="width: <?= number_format($width, 2, '.', ''); ?>%;"></div>
                                </div>
                                <span class="analytics-meta">
                                    <?= $trafficRows !== []
                                        ? analyticsFormatPercent($row['bounce_rate'] ?? null, 'Rebote N/D')
                                        : 'Senal detectada desde leads, formularios o publicaciones del proyecto'; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>

            <article class="analytics-card">
                <div class="analytics-card-head">
                    <div>
                        <h2>Estado del Ecosistema</h2>
                        <p>Todo lo que hoy esta listo para recibir y medir trafico.</p>
                    </div>
                </div>

                <div class="analytics-zone-list">
                    <div class="analytics-zone-item">
                        <strong><?= analyticsFormatNumber(count($connectedProviders)); ?> canales detectados</strong>
                        <span class="analytics-meta"><?= $connectedProviders === [] ? 'Todavia no hay canales usados por publicaciones del proyecto.' : implode(', ', $connectedProviders); ?></span>
                    </div>

                    <div class="analytics-zone-item">
                        <strong><?= analyticsFormatNumber($publishedLandings); ?> landings publicadas</strong>
                        <span class="analytics-meta">Cuantas paginas ya pueden recibir trafico en este proyecto.</span>
                    </div>

                    <div class="analytics-zone-item">
                        <strong><?= analyticsFormatNumber($activeForms); ?> formularios activos</strong>
                        <span class="analytics-meta">Puntos de captura listos para convertir visitas en leads.</span>
                    </div>

                    <div class="analytics-zone-item">
                        <strong><?= analyticsFormatNumber(count($postRows)); ?> publicaciones programadas</strong>
                        <span class="analytics-meta">Sirven como base para rastrear que fuente empuja mejores resultados.</span>
                    </div>
                </div>
            </article>
        </section>

        <section class="analytics-table-shell">
            <div class="analytics-table-head">
                <div>
                    <h2>Detalle por Fuente</h2>
                    <p>Vista limpia para identificar rapidamente que origen necesita atencion.</p>
                </div>
            </div>

            <?php if ($topSources === []): ?>
                <div class="analytics-empty">
                    <strong>Sin detalle por mostrar</strong>
                    <p>Las filas apareceran aqui en cuanto el proyecto empiece a registrar trafico o leads con origen.</p>
                </div>
            <?php else: ?>
                <table class="analytics-table">
                    <thead>
                        <tr>
                            <th>Fuente</th>
                            <th>Canal</th>
                            <th>Volumen</th>
                            <th>Rebote</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topSources as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($row['label'] ?? 'Fuente'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars(analyticsProviderLabel((string) ($row['channel'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= analyticsFormatNumber((int) ($row['volume'] ?? 0)); ?></td>
                                <td><?= $trafficRows !== [] ? analyticsFormatPercent($row['bounce_rate'] ?? null) : 'N/D'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
<?php

function trafficSemaforoRecentRows(array $rows): array
{
    $limitTimestamp = strtotime('-14 days');

    return array_values(array_filter($rows, static function (array $row) use ($limitTimestamp): bool {
        $metricDate = trim((string) ($row['metric_date'] ?? ''));

        if ($metricDate === '') {
            return true;
        }

        $timestamp = strtotime($metricDate);

        return $timestamp === false || $timestamp >= $limitTimestamp;
    }));
}

function trafficSemaforoAggregateSources(array $rows): array
{
    $grouped = [];

    foreach ($rows as $row) {
        $label = trim((string) ($row['source_label'] ?? '')) ?: trim((string) ($row['source_key'] ?? '')) ?: 'Fuente sin nombre';
        $channel = trim((string) ($row['channel'] ?? '')) ?: trim((string) ($row['source_key'] ?? '')) ?: 'trafico';
        $key = $label . '|' . $channel;

        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'label' => $label,
                'channel' => $channel,
                'clicks' => 0,
                'sessions' => 0,
                'bounces' => 0,
                'volume' => 0,
                'bounce_rate' => null,
            ];
        }

        $grouped[$key]['clicks'] += (int) ($row['clicks'] ?? 0);
        $grouped[$key]['sessions'] += (int) ($row['sessions'] ?? 0);
        $grouped[$key]['bounces'] += (int) ($row['bounces'] ?? 0);
        $grouped[$key]['volume'] = max($grouped[$key]['volume'], $grouped[$key]['clicks'], $grouped[$key]['sessions']);
    }

    foreach ($grouped as &$entry) {
        $entry['bounce_rate'] = $entry['sessions'] > 0
            ? ($entry['bounces'] / $entry['sessions']) * 100
            : null;
    }
    unset($entry);

    usort($grouped, static function (array $left, array $right): int {
        return ($right['volume'] <=> $left['volume']) ?: strcmp((string) $left['label'], (string) $right['label']);
    });

    return array_values($grouped);
}

function trafficSemaforoFallbackSources(array $leads, array $targets): array
{
    $grouped = [];

    foreach ($leads as $lead) {
        $label = trim((string) ($lead['source_label'] ?? '')) ?: 'Lead directo';
        $channel = trim((string) ($lead['source_type'] ?? '')) ?: 'lead';
        $key = $label . '|' . $channel;

        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'label' => $label,
                'channel' => $channel,
                'volume' => 0,
                'bounce_rate' => null,
            ];
        }

        $grouped[$key]['volume']++;
    }

    if ($grouped === []) {
        foreach ($targets as $target) {
            $channel = trim((string) ($target['provider_key'] ?? '')) ?: 'social';
            $label = trim((string) ($target['connection_label'] ?? '')) ?: analyticsProviderLabel($channel);
            $key = $label . '|' . $channel;

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'label' => $label,
                    'channel' => $channel,
                    'volume' => 0,
                    'bounce_rate' => null,
                ];
            }

            $grouped[$key]['volume']++;
        }
    }

    usort($grouped, static function (array $left, array $right): int {
        return ($right['volume'] <=> $left['volume']) ?: strcmp((string) $left['label'], (string) $right['label']);
    });

    return array_values($grouped);
}

function trafficSemaforoConnectedProviders(array $targets): array
{
    $providers = [];

    foreach ($targets as $target) {
        $provider = trim((string) ($target['provider_key'] ?? ''));

        if ($provider !== '') {
            $providers[$provider] = analyticsProviderLabel($provider);
        }
    }

    return array_values($providers);
}

function trafficSemaforoSum(array $rows, string $field): int
{
    $total = 0;

    foreach ($rows as $row) {
        $total += (int) ($row[$field] ?? 0);
    }

    return $total;
}
