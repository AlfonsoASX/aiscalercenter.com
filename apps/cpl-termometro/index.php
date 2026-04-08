<?php
declare(strict_types=1);

use AiScaler\Analytics\AnalyticsRepository;

require_once __DIR__ . '/../../modules/analytics/bootstrap.php';

$toolContext = is_array($toolRuntimeContext ?? null) ? $toolRuntimeContext : [];
$repository = new AnalyticsRepository();
$error = null;
$project = null;
$cplRows = [];
$leadRows = [];
$formRows = [];
$postRows = [];

try {
    $resolvedContext = analyticsResolveToolContext($toolContext, 'Termometro de Costo por Lead', $repository);
    $project = $resolvedContext['project'];
    $cplRows = cplTermometroRecentRows($repository->listCplSnapshots($resolvedContext['access_token'], $resolvedContext['project_id']));
    $leadRows = $repository->listLeads($resolvedContext['access_token'], $resolvedContext['project_id']);
    $formRows = $repository->listForms($resolvedContext['access_token'], $resolvedContext['project_id']);
    $postRows = $repository->listScheduledPosts($resolvedContext['access_token'], $resolvedContext['project_id']);
} catch (Throwable $exception) {
    $error = normalizeAnalyticsException($exception);
}

$leadCount30 = cplTermometroRecentLeadCount($leadRows, 30);
$formResponses = array_sum(array_map(static fn(array $row): int => (int) ($row['response_count'] ?? 0), $formRows));
$trackedPosts = count(array_filter($postRows, static fn(array $row): bool => (string) ($row['status'] ?? '') !== 'draft'));
$latestSnapshot = $cplRows[0] ?? null;
$currentCpl = cplTermometroCurrentCpl($latestSnapshot);
$averageCpl = cplTermometroAverageCpl($cplRows);
$gaugeProgress = cplTermometroGaugeProgress($currentCpl);
$gaugeTone = cplTermometroGaugeTone($currentCpl);
?>
<div class="analytics-app analytics-app--cpl">
    <section class="analytics-hero">
        <div class="analytics-hero-copy">
            <p class="analytics-eyebrow">Analiza</p>
            <h1>Termometro de Costo por Lead</h1>
            <p>Controla si tu inversion se mantiene sana. Cuando conectes gasto publicitario, esta vista te dira en segundos si estas comprando leads baratos o quemando presupuesto.</p>
        </div>

        <?php if ($project !== null): ?>
            <span class="analytics-chip"><?= htmlspecialchars((string) ($project['name'] ?? 'Proyecto'), ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
    </section>

    <?php if ($error !== null): ?>
        <section class="analytics-empty">
            <strong><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></strong>
            <p>El termometro se encendera en cuanto la base de analitica este disponible para este proyecto.</p>
        </section>
    <?php else: ?>
        <section class="analytics-grid analytics-grid--four">
            <article class="analytics-card">
                <p class="analytics-eyebrow">CPL actual</p>
                <strong class="analytics-kpi-value"><?= analyticsFormatMoney($currentCpl); ?></strong>
                <p class="analytics-kpi-subtitle"><?= $currentCpl !== null ? 'Ultimo corte disponible' : 'Conecta gasto para calcularlo'; ?></p>
            </article>

            <article class="analytics-card">
                <p class="analytics-eyebrow">Promedio</p>
                <strong class="analytics-kpi-value"><?= analyticsFormatMoney($averageCpl); ?></strong>
                <p class="analytics-kpi-subtitle">Promedio de los ultimos cortes capturados.</p>
            </article>

            <article class="analytics-card">
                <p class="analytics-eyebrow">Leads 30 dias</p>
                <strong class="analytics-kpi-value"><?= analyticsFormatNumber($leadCount30); ?></strong>
                <p class="analytics-kpi-subtitle">Oportunidades registradas en seguimiento comercial.</p>
            </article>

            <article class="analytics-card">
                <p class="analytics-eyebrow">Capturas activas</p>
                <strong class="analytics-kpi-value"><?= analyticsFormatNumber($formResponses); ?></strong>
                <p class="analytics-kpi-subtitle"><?= analyticsFormatNumber($trackedPosts); ?> publicaciones activas listas para medirse.</p>
            </article>
        </section>

        <section class="analytics-card">
            <div class="analytics-card-head">
                <div>
                    <h2>Velocimetro de CPL</h2>
                    <p>Verde para costos sanos, amarillo para revisar y rojo para actuar rapido.</p>
                </div>
                <span class="analytics-pill analytics-pill--<?= htmlspecialchars($gaugeTone, ENT_QUOTES, 'UTF-8'); ?>">
                    <?= htmlspecialchars(cplTermometroGaugeLabel($currentCpl), ENT_QUOTES, 'UTF-8'); ?>
                </span>
            </div>

            <div class="analytics-gauge-shell">
                <div class="analytics-gauge" style="--gauge-progress: <?= number_format($gaugeProgress, 2, '.', ''); ?>;">
                    <div class="analytics-gauge-center">
                        <span class="analytics-meta">Costo actual</span>
                        <strong><?= analyticsFormatMoney($currentCpl); ?></strong>
                        <span class="analytics-meta">Meta saludable sugerida: hasta $20 MXN</span>
                    </div>
                </div>

                <div class="analytics-zone-list">
                    <div class="analytics-zone-item">
                        <strong>Verde</strong>
                        <span class="analytics-meta">Hasta $20 MXN por lead. El canal esta respondiendo muy bien.</span>
                    </div>
                    <div class="analytics-zone-item">
                        <strong>Amarillo</strong>
                        <span class="analytics-meta">Entre $20 y $80 MXN. Conviene revisar anuncio, segmentacion y propuesta.</span>
                    </div>
                    <div class="analytics-zone-item">
                        <strong>Rojo</strong>
                        <span class="analytics-meta">Arriba de $80 MXN. Es momento de pausar, ajustar creativos o mover presupuesto.</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="analytics-table-shell">
            <div class="analytics-table-head">
                <div>
                    <h2>Historial de CPL</h2>
                    <p>Ultimos registros disponibles para detectar cambios bruscos en el costo.</p>
                </div>
            </div>

            <?php if ($cplRows === []): ?>
                <div class="analytics-empty">
                    <strong>Sin cortes de inversion aun</strong>
                    <p>Ejecuta <code class="analytics-code">supabase/analytics_schema.sql</code> y empieza a cargar gasto por fuente para ver el termometro en tiempo real.</p>
                </div>
            <?php else: ?>
                <table class="analytics-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Fuente</th>
                            <th>Gasto</th>
                            <th>Leads</th>
                            <th>CPL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($cplRows, 0, 8) as $row): ?>
                            <?php $rowCpl = cplTermometroCurrentCpl($row); ?>
                            <tr>
                                <td><?= htmlspecialchars(analyticsFormatDate((string) ($row['snapshot_date'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars((string) ($row['source_label'] ?? $row['source_key'] ?? 'Fuente'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= analyticsFormatMoney($row['spend_mxn'] ?? null); ?></td>
                                <td><?= analyticsFormatNumber((int) ($row['lead_count'] ?? 0)); ?></td>
                                <td><?= analyticsFormatMoney($rowCpl); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
<?php

function cplTermometroRecentRows(array $rows): array
{
    $limitTimestamp = strtotime('-30 days');

    return array_values(array_filter($rows, static function (array $row) use ($limitTimestamp): bool {
        $snapshotDate = trim((string) ($row['snapshot_date'] ?? ''));

        if ($snapshotDate === '') {
            return true;
        }

        $timestamp = strtotime($snapshotDate);

        return $timestamp === false || $timestamp >= $limitTimestamp;
    }));
}

function cplTermometroRecentLeadCount(array $leads, int $days): int
{
    $limitTimestamp = strtotime('-' . $days . ' days');
    $count = 0;

    foreach ($leads as $lead) {
        $createdAt = trim((string) ($lead['created_at'] ?? ''));
        $timestamp = strtotime($createdAt);

        if ($timestamp !== false && $timestamp >= $limitTimestamp) {
            $count++;
        }
    }

    return $count;
}

function cplTermometroCurrentCpl(?array $row): ?float
{
    if (!is_array($row)) {
        return null;
    }

    $spend = (float) ($row['spend_mxn'] ?? 0);
    $leads = (int) ($row['lead_count'] ?? 0);

    if ($spend <= 0 || $leads <= 0) {
        return null;
    }

    return $spend / $leads;
}

function cplTermometroAverageCpl(array $rows): ?float
{
    $values = [];

    foreach ($rows as $row) {
        $value = cplTermometroCurrentCpl($row);

        if ($value !== null) {
            $values[] = $value;
        }
    }

    if ($values === []) {
        return null;
    }

    return array_sum($values) / count($values);
}

function cplTermometroGaugeProgress(?float $value): float
{
    if ($value === null || $value <= 0) {
        return 0;
    }

    return min(($value / 150) * 100, 100);
}

function cplTermometroGaugeTone(?float $value): string
{
    if ($value === null) {
        return 'info';
    }

    if ($value <= 20) {
        return 'success';
    }

    if ($value <= 80) {
        return 'warning';
    }

    return 'danger';
}

function cplTermometroGaugeLabel(?float $value): string
{
    if ($value === null) {
        return 'Sin gasto conectado';
    }

    if ($value <= 20) {
        return 'Saludable';
    }

    if ($value <= 80) {
        return 'Revisar';
    }

    return 'Riesgo alto';
}
