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
$landingRows = [];
$formRows = [];
$trafficRows = [];
$storedAlerts = [];

try {
    $resolvedContext = analyticsResolveToolContext($toolContext, 'Auditor de Salud de Campañas', $repository);
    $project = $resolvedContext['project'];
    $postRows = $repository->listScheduledPosts($resolvedContext['access_token'], $resolvedContext['project_id']);
    $targetRows = $repository->listScheduledTargets($resolvedContext['access_token'], $postRows);
    $landingRows = $repository->listLandingPages($resolvedContext['access_token'], $resolvedContext['project_id']);
    $formRows = $repository->listForms($resolvedContext['access_token'], $resolvedContext['project_id']);
    $trafficRows = $repository->listTrafficSnapshots($resolvedContext['access_token'], $resolvedContext['project_id']);
    $storedAlerts = $repository->listCampaignAlerts($resolvedContext['access_token'], $resolvedContext['project_id']);
} catch (Throwable $exception) {
    $error = normalizeAnalyticsException($exception);
}

$alerts = auditorBuildAlerts($postRows, $targetRows, $landingRows, $formRows, $trafficRows, $storedAlerts);
$criticalCount = count(array_filter($alerts, static fn(array $row): bool => analyticsSeverityTone((string) ($row['severity'] ?? 'info')) === 'danger'));
$warningCount = count(array_filter($alerts, static fn(array $row): bool => analyticsSeverityTone((string) ($row['severity'] ?? 'info')) === 'warning'));
$infoCount = count($alerts) - $criticalCount - $warningCount;
?>
<div class="analytics-app analytics-app--auditor">
    <section class="analytics-hero">
        <div class="analytics-hero-copy">
            <p class="analytics-eyebrow">Analiza</p>
            <h1>Auditor de Salud de Campañas</h1>
            <p>Una vista temprana para detectar fallas antes de quemar presupuesto: landings sin publicar, publicaciones sin destino o campanas que ya dieron senales de riesgo.</p>
        </div>

        <?php if ($project !== null): ?>
            <span class="analytics-chip"><?= htmlspecialchars((string) ($project['name'] ?? 'Proyecto'), ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
    </section>

    <?php if ($error !== null): ?>
        <section class="analytics-empty">
            <strong><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></strong>
            <p>En cuanto el proyecto tenga datos suficientes, el auditor levantara alertas tempranas automaticamente.</p>
        </section>
    <?php else: ?>
        <section class="analytics-grid analytics-grid--four">
            <article class="analytics-card">
                <p class="analytics-eyebrow">Criticas</p>
                <strong class="analytics-kpi-value"><?= analyticsFormatNumber($criticalCount); ?></strong>
                <p class="analytics-kpi-subtitle">Situaciones que conviene corregir hoy.</p>
            </article>

            <article class="analytics-card">
                <p class="analytics-eyebrow">Advertencias</p>
                <strong class="analytics-kpi-value"><?= analyticsFormatNumber($warningCount); ?></strong>
                <p class="analytics-kpi-subtitle">Cambios recomendables antes de escalar inversion.</p>
            </article>

            <article class="analytics-card">
                <p class="analytics-eyebrow">Informativas</p>
                <strong class="analytics-kpi-value"><?= analyticsFormatNumber($infoCount); ?></strong>
                <p class="analytics-kpi-subtitle">Oportunidades para fortalecer el embudo del proyecto.</p>
            </article>

            <article class="analytics-card">
                <p class="analytics-eyebrow">Campanas revisadas</p>
                <strong class="analytics-kpi-value"><?= analyticsFormatNumber(count($postRows)); ?></strong>
                <p class="analytics-kpi-subtitle"><?= analyticsFormatNumber(count($targetRows)); ?> destinos y <?= analyticsFormatNumber(count($landingRows)); ?> landings observadas.</p>
            </article>
        </section>

        <?php if ($alerts === []): ?>
            <section class="analytics-empty">
                <strong>Todo se ve estable por ahora</strong>
                <p>Cuando aparezcan riesgos tecnicos o comerciales en este proyecto, los veras aqui antes de que se conviertan en perdida de dinero.</p>
            </section>
        <?php else: ?>
            <section class="analytics-alert-list">
                <?php foreach ($alerts as $alert): ?>
                    <?php $tone = analyticsSeverityTone((string) ($alert['severity'] ?? 'info')); ?>
                    <article class="analytics-alert analytics-alert--<?= htmlspecialchars($tone, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="analytics-card-head">
                            <div>
                                <h2><?= htmlspecialchars((string) ($alert['title'] ?? 'Alerta'), ENT_QUOTES, 'UTF-8'); ?></h2>
                                <p><?= htmlspecialchars((string) ($alert['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <span class="analytics-pill analytics-pill--<?= htmlspecialchars($tone, ENT_QUOTES, 'UTF-8'); ?>">
                                <?= htmlspecialchars((string) ucfirst((string) ($alert['severity'] ?? 'info')), ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </div>

                        <div class="analytics-inline-list">
                            <?php if (trim((string) ($alert['source'] ?? '')) !== ''): ?>
                                <span class="analytics-pill"><?= htmlspecialchars((string) ($alert['source'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                            <?php if (trim((string) ($alert['detected_at'] ?? '')) !== ''): ?>
                                <span class="analytics-pill"><?= htmlspecialchars(analyticsFormatDateTime((string) ($alert['detected_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php

function auditorBuildAlerts(
    array $posts,
    array $targets,
    array $landings,
    array $forms,
    array $trafficRows,
    array $storedAlerts
): array {
    $alerts = [];
    $targetsByPostId = [];

    foreach ($targets as $target) {
        $postId = trim((string) ($target['post_id'] ?? ''));

        if ($postId === '') {
            continue;
        }

        if (!isset($targetsByPostId[$postId])) {
            $targetsByPostId[$postId] = [];
        }

        $targetsByPostId[$postId][] = $target;
    }

    $publishedLandings = array_filter($landings, static fn(array $row): bool => (string) ($row['status'] ?? '') === 'published');
    $publishedForms = array_filter($forms, static fn(array $row): bool => (string) ($row['status'] ?? '') === 'published');

    if ($posts === []) {
        $alerts[] = auditorAlert('info', 'Todavia no hay campanas activas', 'Programa contenido o anuncios desde Ejecutar para que el auditor pueda revisar riesgos reales.', 'Proyecto');
    }

    if ($publishedLandings === []) {
        $alerts[] = auditorAlert('warning', 'No hay landings publicadas', 'Tu trafico puede estar llegando sin una pagina final lista para convertir.', 'Landing pages');
    }

    if ($publishedForms === []) {
        $alerts[] = auditorAlert('info', 'No hay formularios publicados', 'Asegura al menos un punto de captura para no perder leads cuando la campana funcione.', 'Formularios');
    }

    if ($trafficRows === []) {
        $alerts[] = auditorAlert('warning', 'No hay telemetria de trafico', 'Todavia no existen registros de trafico real para confirmar sesiones, rebote o fuentes ganadoras.', 'Analitica');
    }

    foreach ($posts as $post) {
        $postId = trim((string) ($post['id'] ?? ''));
        $title = trim((string) ($post['title'] ?? '')) ?: 'Publicacion sin titulo';
        $status = trim((string) ($post['status'] ?? ''));
        $postTargets = $targetsByPostId[$postId] ?? [];

        if ($status === 'failed') {
            $alerts[] = auditorAlert('critical', 'Publicacion fallida', 'La pieza "' . $title . '" se marco como fallida y conviene revisarla antes de seguir invirtiendo.', 'Ejecutar', (string) ($post['updated_at'] ?? ''));
        }

        if (in_array($status, ['draft', 'scheduled', 'publishing'], true) && $postTargets === []) {
            $alerts[] = auditorAlert('warning', 'Publicacion sin destinos', 'La pieza "' . $title . '" aun no tiene redes sociales asignadas.', 'Ejecutar', (string) ($post['updated_at'] ?? ''));
        }

        foreach ($postTargets as $target) {
            $destination = analyticsExtractTargetUrl($target);

            if ($destination === '') {
                $alerts[] = auditorAlert('info', 'Contenido sin enlace rastreable', 'La pieza "' . $title . '" en ' . analyticsProviderLabel((string) ($target['provider_key'] ?? '')) . ' no tiene URL para medir su rendimiento.', 'Rastreador');
            }
        }
    }

    foreach ($storedAlerts as $storedAlert) {
        $alerts[] = [
            'severity' => (string) ($storedAlert['severity'] ?? 'info'),
            'title' => (string) ($storedAlert['title'] ?? 'Alerta'),
            'message' => (string) ($storedAlert['message'] ?? ''),
            'source' => (string) ($storedAlert['source_key'] ?? 'Sistema'),
            'detected_at' => (string) ($storedAlert['detected_at'] ?? ''),
        ];
    }

    usort($alerts, static function (array $left, array $right): int {
        $weight = [
            'critical' => 3,
            'warning' => 2,
            'info' => 1,
        ];

        $leftWeight = $weight[(string) ($left['severity'] ?? 'info')] ?? 0;
        $rightWeight = $weight[(string) ($right['severity'] ?? 'info')] ?? 0;

        return ($rightWeight <=> $leftWeight) ?: strcmp((string) ($right['detected_at'] ?? ''), (string) ($left['detected_at'] ?? ''));
    });

    return $alerts;
}

function auditorAlert(string $severity, string $title, string $message, string $source, string $detectedAt = ''): array
{
    return [
        'severity' => $severity,
        'title' => $title,
        'message' => $message,
        'source' => $source,
        'detected_at' => $detectedAt,
    ];
}
