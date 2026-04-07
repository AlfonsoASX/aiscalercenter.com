<?php
declare(strict_types=1);

$toolContext = is_array($toolRuntimeContext ?? null) ? $toolRuntimeContext : [];
$launchToken = rawurlencode((string) ($toolContext['launch_token'] ?? ''));
$styleUrl = 'tool-asset.php?launch=' . $launchToken . '&asset=style.css';
$scriptUrl = 'tool-asset.php?launch=' . $launchToken . '&asset=app.js';
?>
<div
    class="research-tool-page"
    data-tool-fragment-root="true"
    data-tool-style-url="<?= htmlspecialchars($styleUrl, ENT_QUOTES, 'UTF-8'); ?>"
    data-tool-script-url="<?= htmlspecialchars($scriptUrl, ENT_QUOTES, 'UTF-8'); ?>"
>
    <section class="research-tool-card">
        <div class="research-tool-card-head">
            <h2>Idea a investigar</h2>
            <p>Consulta senales relacionadas desde YouTube con esta app aislada.</p>
        </div>

        <form id="research-tool-form" class="research-tool-form">
            <label for="research-tool-idea" class="research-tool-label">Idea</label>
            <div class="research-tool-input-shell">
                <textarea
                    id="research-tool-idea"
                    name="idea"
                    class="research-tool-textarea"
                    rows="3"
                    placeholder="Ejemplo: ideas de videos sobre inteligencia artificial para ventas"
                ></textarea>

                <button id="research-tool-submit" type="submit" class="research-tool-submit">
                    <span class="material-symbols-rounded">travel_explore</span>
                    <span>Investigar en YouTube</span>
                </button>
            </div>
        </form>

        <div id="research-tool-notice" class="research-tool-notice hidden"></div>
        <div id="research-tool-results">
            <div class="research-tool-empty">
                <span class="material-symbols-rounded">manage_search</span>
                <h3>Empieza con una idea</h3>
                <p>Escribe una idea y revisa terminos relacionados obtenidos desde YouTube.</p>
            </div>
        </div>
    </section>
</div>
