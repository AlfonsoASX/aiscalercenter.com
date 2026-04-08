<?php
declare(strict_types=1);

use AiScaler\CustomerPipeline\CustomerPipelineRepository;

require_once __DIR__ . '/../../modules/customer-follow-up/bootstrap.php';

$toolContext = is_array($toolRuntimeContext ?? null) ? $toolRuntimeContext : [];
$accessToken = trim((string) ($toolContext['access_token'] ?? ''));
$userId = trim((string) ($toolContext['user_id'] ?? ''));
$projectContext = is_array($toolContext['project'] ?? null) ? $toolContext['project'] : [];
$activeProjectId = trim((string) ($projectContext['id'] ?? ''));
$repository = new CustomerPipelineRepository();
$project = null;
$board = [
    'settings' => null,
    'stages' => [],
    'leads' => [],
];
$error = null;
$launchToken = rawurlencode((string) ($toolContext['launch_token'] ?? ''));
$apiUrl = 'tool-action.php?launch=' . $launchToken;

try {
    if ($accessToken === '' || $userId === '') {
        throw new RuntimeException('No encontramos la sesion segura para abrir el tablero. Vuelve a abrir la herramienta desde el panel.');
    }

    if ($activeProjectId === '') {
        throw new RuntimeException('Selecciona un proyecto antes de abrir Seguimiento de Clientes.');
    }

    $project = $repository->findProject($accessToken, $activeProjectId);

    if (!is_array($project)) {
        throw new RuntimeException('No encontramos el proyecto activo para cargar el tablero.');
    }

    $board = customerPipelineNormalizeBoard($repository->getBoard($accessToken, $activeProjectId));
} catch (Throwable $exception) {
    $error = normalizeCustomerPipelineException($exception);
}

$webhookKey = trim((string) (($board['settings']['public_key'] ?? null) ?: ''));
$webhookUrl = $webhookKey !== '' ? customerPipelineWebhookUrl($webhookKey) : '';
$initialState = [
    'project' => [
        'id' => (string) ($project['id'] ?? $activeProjectId),
        'name' => (string) ($project['name'] ?? 'Proyecto'),
        'logo_url' => (string) ($project['logo_url'] ?? ''),
    ],
    'webhook' => [
        'url' => $webhookUrl,
        'public_key' => $webhookKey,
    ],
    'stages' => $board['stages'],
    'leads' => $board['leads'],
];
?>
<div
    class="customer-pipeline-page"
    data-customer-pipeline="true"
    data-api-url="<?= htmlspecialchars($apiUrl, ENT_QUOTES, 'UTF-8'); ?>"
>
    <header class="customer-pipeline-hero">
        <div class="customer-pipeline-hero-copy">
            <p class="customer-pipeline-eyebrow">Ejecutar</p>
            <h1>Seguimiento de Clientes</h1>
            <p>Gestiona prospectos con un tablero Kanban, contacta rapido por WhatsApp y mueve cada oportunidad segun avance tu proceso comercial.</p>
        </div>

        <?php if ($error === null): ?>
            <div class="customer-pipeline-hero-actions">
                <label class="customer-pipeline-search-shell">
                    <span class="material-symbols-rounded">search</span>
                    <input type="search" placeholder="Buscar por nombre, correo o etiqueta" data-pipeline-search>
                </label>

                <button type="button" class="customer-pipeline-primary" data-pipeline-create>
                    <span class="material-symbols-rounded">add_circle</span>
                    <span>Nuevo lead</span>
                </button>
            </div>
        <?php endif; ?>
    </header>

    <?php if ($error === null): ?>
        <div class="customer-pipeline-notice hidden" data-pipeline-notice></div>
    <?php endif; ?>

    <?php if ($error !== null): ?>
        <div class="customer-pipeline-notice customer-pipeline-notice--error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php else: ?>
        <section class="customer-pipeline-webhook-card">
            <div class="customer-pipeline-webhook-copy">
                <p class="customer-pipeline-eyebrow">Entrada automatica</p>
                <h2>Webhook de leads</h2>
                <p>Recibe formularios, landings o campañas en tiempo real y conviertelos en tarjetas nuevas dentro de la columna inicial.</p>
            </div>

            <div class="customer-pipeline-webhook-grid">
                <div class="customer-pipeline-webhook-field">
                    <span>URL</span>
                    <code><?= htmlspecialchars($webhookUrl, ENT_QUOTES, 'UTF-8'); ?></code>
                    <button type="button" class="customer-pipeline-link-button" data-copy-value="<?= htmlspecialchars($webhookUrl, ENT_QUOTES, 'UTF-8'); ?>">Copiar URL</button>
                </div>

                <div class="customer-pipeline-webhook-field">
                    <span>Llave</span>
                    <code><?= htmlspecialchars($webhookKey, ENT_QUOTES, 'UTF-8'); ?></code>
                    <button type="button" class="customer-pipeline-link-button" data-copy-value="<?= htmlspecialchars($webhookKey, ENT_QUOTES, 'UTF-8'); ?>">Copiar llave</button>
                </div>
            </div>
        </section>

        <section class="customer-pipeline-board-shell">
            <div class="customer-pipeline-board" data-pipeline-board>
                <?= customerPipelineRenderBoard($board['stages'], $board['leads']); ?>
            </div>
        </section>

        <div class="customer-pipeline-overlay hidden" data-pipeline-overlay></div>

        <aside class="customer-pipeline-panel hidden" data-pipeline-panel aria-hidden="true">
            <form class="customer-pipeline-panel-shell" data-pipeline-form>
                <div class="customer-pipeline-panel-head">
                    <div>
                        <p class="customer-pipeline-eyebrow">Detalle del lead</p>
                        <h2 data-pipeline-panel-title>Nuevo lead</h2>
                    </div>

                    <button type="button" class="customer-pipeline-icon-button" data-pipeline-close aria-label="Cerrar panel">
                        <span class="material-symbols-rounded">close</span>
                    </button>
                </div>

                <div class="customer-pipeline-panel-body">
                    <input type="hidden" name="id">
                    <input type="hidden" name="project_id" value="<?= htmlspecialchars((string) ($project['id'] ?? $activeProjectId), ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="customer-pipeline-form-grid">
                        <label class="customer-pipeline-field customer-pipeline-field--wide">
                            <span>Nombre completo</span>
                            <input type="text" name="full_name" placeholder="Nombre del prospecto" required>
                        </label>

                        <label class="customer-pipeline-field">
                            <span>Etapa</span>
                            <select name="stage_id" data-pipeline-stage-select>
                                <?php foreach ($board['stages'] as $stage): ?>
                                    <option value="<?= htmlspecialchars((string) ($stage['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                        <?= htmlspecialchars((string) ($stage['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="customer-pipeline-field">
                            <span>Origen</span>
                            <input type="text" name="source_label" placeholder="Landing Page, Facebook, etc.">
                        </label>

                        <label class="customer-pipeline-field">
                            <span>Empresa</span>
                            <input type="text" name="company_name" placeholder="Empresa o negocio">
                        </label>

                        <label class="customer-pipeline-field">
                            <span>Correo</span>
                            <input type="email" name="email" placeholder="correo@empresa.com">
                        </label>

                        <label class="customer-pipeline-field">
                            <span>Telefono / WhatsApp</span>
                            <input type="text" name="phone" placeholder="5215512345678">
                        </label>

                        <label class="customer-pipeline-field">
                            <span>Valor estimado</span>
                            <input type="number" name="estimated_value" min="0" step="0.01" placeholder="0">
                        </label>

                        <label class="customer-pipeline-field customer-pipeline-field--wide" data-pipeline-lost-reason-wrap hidden>
                            <span>Motivo de perdida</span>
                            <input type="text" name="lost_reason" placeholder="Caro, no responde, compro con la competencia...">
                        </label>

                        <label class="customer-pipeline-field customer-pipeline-field--wide">
                            <span>Notas de seguimiento</span>
                            <textarea name="notes" rows="8" placeholder="Llamar manana, esta interesado en el paquete B..."></textarea>
                        </label>
                    </div>

                    <div class="customer-pipeline-quick-actions" data-pipeline-quick-actions>
                        <a href="#" class="customer-pipeline-secondary" target="_blank" rel="noreferrer noopener" data-pipeline-whatsapp-link>
                            <span class="material-symbols-rounded">forum</span>
                            <span>WhatsApp</span>
                        </a>

                        <a href="#" class="customer-pipeline-secondary" data-pipeline-email-link>
                            <span class="material-symbols-rounded">mail</span>
                            <span>Correo</span>
                        </a>
                    </div>
                </div>

                <div class="customer-pipeline-panel-footer">
                    <button type="button" class="customer-pipeline-secondary" data-pipeline-close>Cancelar</button>
                    <button type="submit" class="customer-pipeline-primary" data-pipeline-save>
                        <span class="material-symbols-rounded">save</span>
                        <span>Guardar lead</span>
                    </button>
                </div>
            </form>
        </aside>

        <script id="customer-pipeline-state" type="application/json"><?= json_encode($initialState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
        <script src="tool-asset.php?launch=<?= htmlspecialchars($launchToken, ENT_QUOTES, 'UTF-8'); ?>&asset=app.js"></script>
    <?php endif; ?>
</div>

<?php
function customerPipelineNormalizeBoard(array $board): array
{
    return [
        'settings' => is_array($board['settings'] ?? null) ? $board['settings'] : null,
        'stages' => array_values(array_map('customerPipelineNormalizeStage', is_array($board['stages'] ?? null) ? $board['stages'] : [])),
        'leads' => array_values(array_map('customerPipelineNormalizeLead', is_array($board['leads'] ?? null) ? $board['leads'] : [])),
    ];
}

function customerPipelineNormalizeStage(mixed $stage): array
{
    if (!is_array($stage)) {
        return [];
    }

    return [
        'id' => (string) ($stage['id'] ?? ''),
        'key' => (string) ($stage['key'] ?? ''),
        'title' => (string) ($stage['title'] ?? ''),
        'accent_color' => (string) ($stage['accent_color'] ?? '#1a73e8'),
        'sort_order' => (int) ($stage['sort_order'] ?? 0),
    ];
}

function customerPipelineNormalizeLead(mixed $lead): array
{
    if (!is_array($lead)) {
        return [];
    }

    $tags = $lead['tags'] ?? [];
    $metadata = $lead['metadata'] ?? [];

    if (is_string($tags)) {
        $decoded = json_decode($tags, true);
        $tags = is_array($decoded) ? $decoded : [];
    }

    if (is_string($metadata)) {
        $decoded = json_decode($metadata, true);
        $metadata = is_array($decoded) ? $decoded : [];
    }

    return [
        'id' => (string) ($lead['id'] ?? ''),
        'project_id' => (string) ($lead['project_id'] ?? ''),
        'stage_id' => (string) ($lead['stage_id'] ?? ''),
        'full_name' => (string) ($lead['full_name'] ?? ''),
        'email' => (string) ($lead['email'] ?? ''),
        'phone' => (string) ($lead['phone'] ?? ''),
        'company_name' => (string) ($lead['company_name'] ?? ''),
        'source_label' => (string) ($lead['source_label'] ?? ''),
        'source_type' => (string) ($lead['source_type'] ?? ''),
        'source_reference' => (string) ($lead['source_reference'] ?? ''),
        'currency_code' => (string) ($lead['currency_code'] ?? 'MXN'),
        'estimated_value' => (float) ($lead['estimated_value'] ?? 0),
        'notes' => (string) ($lead['notes'] ?? ''),
        'lost_reason' => (string) ($lead['lost_reason'] ?? ''),
        'tags' => array_values(array_filter(is_array($tags) ? $tags : [])),
        'metadata' => is_array($metadata) ? $metadata : [],
        'assigned_user_id' => (string) ($lead['assigned_user_id'] ?? ''),
        'follow_up_at' => (string) ($lead['follow_up_at'] ?? ''),
        'sort_order' => (float) ($lead['sort_order'] ?? 0),
        'created_at' => (string) ($lead['created_at'] ?? ''),
    ];
}

function customerPipelineRenderBoard(array $stages, array $leads): string
{
    $groupedLeads = [];

    foreach ($stages as $stage) {
        $groupedLeads[(string) ($stage['id'] ?? '')] = [];
    }

    foreach ($leads as $lead) {
        $stageId = (string) ($lead['stage_id'] ?? '');
        $groupedLeads[$stageId] ??= [];
        $groupedLeads[$stageId][] = $lead;
    }

    ob_start();
    foreach ($stages as $stage):
        $stageId = (string) ($stage['id'] ?? '');
        $stageLeads = $groupedLeads[$stageId] ?? [];
        ?>
        <section class="customer-pipeline-column" data-stage-id="<?= htmlspecialchars($stageId, ENT_QUOTES, 'UTF-8'); ?>">
            <header class="customer-pipeline-column-head" style="--pipeline-stage-accent: <?= htmlspecialchars((string) ($stage['accent_color'] ?? '#1a73e8'), ENT_QUOTES, 'UTF-8'); ?>;">
                <div>
                    <h3><?= htmlspecialchars((string) ($stage['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p><?= count($stageLeads); ?> lead<?= count($stageLeads) === 1 ? '' : 's'; ?></p>
                </div>
                <span class="customer-pipeline-column-count"><?= count($stageLeads); ?></span>
            </header>

            <div class="customer-pipeline-column-body" data-stage-list="<?= htmlspecialchars($stageId, ENT_QUOTES, 'UTF-8'); ?>">
                <?php if ($stageLeads === []): ?>
                    <div class="customer-pipeline-empty-column">Arrastra un lead aqui</div>
                <?php else: ?>
                    <?php foreach ($stageLeads as $lead): ?>
                        <?= customerPipelineRenderCard($lead); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
        <?php
    endforeach;

    return (string) ob_get_clean();
}

function customerPipelineRenderCard(array $lead): string
{
    $name = (string) ($lead['full_name'] ?? 'Lead sin nombre');
    $source = trim((string) ($lead['source_label'] ?? '')) ?: 'Sin origen';
    $value = customerPipelineFormatMoney((float) ($lead['estimated_value'] ?? 0), (string) ($lead['currency_code'] ?? 'MXN'));
    $whatsAppUrl = customerPipelineWhatsAppUrl((string) ($lead['phone'] ?? ''));
    $searchBlob = strtolower(trim($name . ' ' . (string) ($lead['email'] ?? '') . ' ' . $source));

    ob_start();
    ?>
    <article
        class="customer-pipeline-card"
        draggable="true"
        data-lead-id="<?= htmlspecialchars((string) ($lead['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
        data-stage-id="<?= htmlspecialchars((string) ($lead['stage_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
        data-sort-order="<?= htmlspecialchars((string) ($lead['sort_order'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>"
        data-search="<?= htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8'); ?>"
    >
        <button type="button" class="customer-pipeline-card-body" data-lead-open="<?= htmlspecialchars((string) ($lead['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            <span class="customer-pipeline-card-name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="customer-pipeline-card-tag"><?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="customer-pipeline-card-value"><?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?></span>
        </button>

        <a
            class="customer-pipeline-card-wa"
            href="<?= htmlspecialchars($whatsAppUrl, ENT_QUOTES, 'UTF-8'); ?>"
            target="_blank"
            rel="noreferrer noopener"
            aria-label="Abrir WhatsApp"
            data-lead-whatsapp
        >
            <span class="material-symbols-rounded">forum</span>
        </a>
    </article>
    <?php

    return (string) ob_get_clean();
}

function customerPipelineFormatMoney(float $value, string $currency): string
{
    if (class_exists('NumberFormatter')) {
        $formatter = new NumberFormatter('es_MX', NumberFormatter::CURRENCY);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 0);
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 0);

        $formatted = $formatter->formatCurrency($value, $currency !== '' ? $currency : 'MXN');

        if ($formatted !== false) {
            return $formatted;
        }
    }

    return '$' . number_format($value, 0);
}

function customerPipelineWhatsAppUrl(string $phone): string
{
    $normalizedPhone = preg_replace('/\D+/', '', $phone) ?? '';

    return $normalizedPhone !== ''
        ? 'https://wa.me/' . rawurlencode($normalizedPhone)
        : '#';
}
