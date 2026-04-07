<?php
declare(strict_types=1);

use AiScaler\Research\Http\HttpClient;
use AiScaler\Research\Providers\YouTube\YouTubeProvider;
use AiScaler\Research\Support\TextAnalyzer;

require_once __DIR__ . '/../../modules/research/bootstrap.php';

$toolContext = is_array($toolRuntimeContext ?? null) ? $toolRuntimeContext : [];
$idea = trim((string) ($_POST['idea'] ?? ''));
$result = null;
$notice = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (mb_strlen($idea) < 3) {
            throw new InvalidArgumentException('Escribe una idea un poco mas especifica para investigar.');
        }

        $config = require __DIR__ . '/../../config/research.php';
        $providerConfig = is_array($config['providers']['youtube'] ?? null) ? $config['providers']['youtube'] : [];
        $provider = new YouTubeProvider($providerConfig, new HttpClient((int) ($config['http_timeout'] ?? 12)), new TextAnalyzer());
        $result = $provider->analyze($idea, 10);
        $notice = ['type' => 'success', 'message' => 'La investigacion se actualizo correctamente.'];
    } catch (Throwable $exception) {
        $notice = ['type' => 'error', 'message' => $exception->getMessage()];
    }
}
?>
<div class="research-tool-page">
    <section class="research-tool-card">
        <div class="research-tool-card-head">
            <p class="research-tool-eyebrow">Herramienta</p>
            <h2>YouTube</h2>
            <p>Consulta senales y terminos relacionados desde YouTube.</p>
        </div>

        <form method="post" action="<?= htmlspecialchars('tool.php?launch=' . rawurlencode((string) ($toolContext['launch_token'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>" class="research-tool-form">
            <label for="research-tool-idea" class="research-tool-label">Idea</label>
            <div class="research-tool-input-shell">
                <textarea
                    id="research-tool-idea"
                    name="idea"
                    class="research-tool-textarea"
                    rows="3"
                    placeholder="Ejemplo: ideas de videos sobre inteligencia artificial para ventas"
                ><?= htmlspecialchars($idea, ENT_QUOTES, 'UTF-8'); ?></textarea>

                <button type="submit" class="research-tool-submit">
                    <span class="material-symbols-rounded">travel_explore</span>
                    <span>Investigar en YouTube</span>
                </button>
            </div>
        </form>

        <?php if (is_array($notice)): ?>
            <div class="research-tool-notice research-tool-notice--<?= htmlspecialchars((string) ($notice['type'] ?? 'info'), ENT_QUOTES, 'UTF-8'); ?>">
                <?= htmlspecialchars((string) ($notice['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if (is_array($result)): ?>
            <?= renderResearchProviderResult('YouTube', $idea, $result); ?>
        <?php else: ?>
            <div class="research-tool-empty">
                <span class="material-symbols-rounded">manage_search</span>
                <h3>Empieza con una idea</h3>
                <p>Escribe una idea y revisa terminos relacionados obtenidos desde YouTube.</p>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php
function renderResearchProviderResult(string $providerTitle, string $idea, array $provider): string
{
    $entries = is_array($provider['entries'] ?? null) ? $provider['entries'] : [];
    $summary = is_array($provider['summary'] ?? null) ? $provider['summary'] : [];

    ob_start();
    ?>
    <div class="research-tool-results-head">
        <span class="research-tool-eyebrow">Idea investigada</span>
        <h3><?= htmlspecialchars($idea, ENT_QUOTES, 'UTF-8'); ?></h3>
    </div>

    <article class="research-tool-provider-card">
        <header class="research-tool-provider-head">
            <div>
                <h4><?= htmlspecialchars((string) ($provider['label'] ?? $providerTitle), ENT_QUOTES, 'UTF-8'); ?></h4>
                <p><?= htmlspecialchars((string) ($provider['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <span class="research-tool-badge"><?= htmlspecialchars((string) ($provider['status_label'] ?? 'Listo'), ENT_QUOTES, 'UTF-8'); ?></span>
        </header>

        <div class="research-tool-summary">
            <?= renderResearchSummaryChip('Resultados', $summary['total_results'] ?? 'N/D'); ?>
            <?= renderResearchSummaryChip('Analizados', $summary['analyzed_items'] ?? 'N/D'); ?>
            <?= renderResearchSummaryChip('Terminos', $summary['related_terms'] ?? 'N/D'); ?>
        </div>

        <?php if ($entries !== []): ?>
            <div class="research-tool-table-wrap">
                <table class="research-tool-table">
                    <thead>
                        <tr>
                            <th>Termino</th>
                            <th>Menciones</th>
                            <th>Ejemplo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $entry): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($entry['term'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars((string) ($entry['mentions'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars((string) ($entry['sample'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="research-tool-empty">
                <span class="material-symbols-rounded">data_info_alert</span>
                <h3>Sin resultados suficientes</h3>
                <p><?= htmlspecialchars((string) ($provider['message'] ?? 'No se encontraron terminos relacionados con informacion suficiente.'), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        <?php endif; ?>
    </article>
    <?php

    return (string) ob_get_clean();
}

function renderResearchSummaryChip(string $label, mixed $value): string
{
    ob_start();
    ?>
    <div class="research-tool-summary-chip">
        <small><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></small>
        <strong><?= htmlspecialchars($value == null ? 'N/D' : (string) $value, ENT_QUOTES, 'UTF-8'); ?></strong>
    </div>
    <?php

    return (string) ob_get_clean();
}
