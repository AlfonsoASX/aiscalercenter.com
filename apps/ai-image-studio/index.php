<?php
declare(strict_types=1);

require_once __DIR__ . '/../../modules/ai-images/bootstrap.php';

$toolContext = is_array($toolRuntimeContext ?? null) ? $toolRuntimeContext : [];
$project = is_array($toolContext['project'] ?? null) ? $toolContext['project'] : [];
$activeProjectId = trim((string) ($project['id'] ?? ''));
$launchToken = rawurlencode((string) ($toolContext['launch_token'] ?? ''));
$apiUrl = 'tool-action.php?launch=' . $launchToken . '&action=generate';
$initialState = aiImagesDefaultState();
$providerReady = aiImagesProviderReady();
$setupMessage = $providerReady
    ? ''
    : 'La interfaz ya esta lista. Para generar imagenes reales, completa config/ai_images.php con tu proveedor, endpoint y API key del servidor.';
?>
<div
    class="ai-images-page"
    data-ai-images-app
    data-api-url="<?= htmlspecialchars($apiUrl, ENT_QUOTES, 'UTF-8'); ?>"
>
    <header class="ai-images-hero">
        <div class="ai-images-hero-copy">
            <p class="ai-images-eyebrow">Diseñar</p>
            <h1>Crear imagenes con IA</h1>
            <p>Construye prompts visuales de forma simple, elige formato, estilo y contexto de marca, y deja lista la base del creativo desde un solo lugar.</p>
        </div>

        <?php if ($activeProjectId !== ''): ?>
            <span class="ai-images-project-chip"><?= htmlspecialchars((string) ($project['name'] ?? 'Proyecto'), ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
    </header>

    <?php if ($activeProjectId === ''): ?>
        <section class="ai-images-empty">
            <strong>Selecciona un proyecto antes de crear imagenes.</strong>
            <p>Esta herramienta trabaja con el contexto activo del proyecto para mantener organizados los creativos.</p>
        </section>
    <?php else: ?>
        <?php if ($setupMessage !== ''): ?>
            <section class="ai-images-notice">
                <strong>Proveedor pendiente</strong>
                <p><?= htmlspecialchars($setupMessage, ENT_QUOTES, 'UTF-8'); ?></p>
            </section>
        <?php endif; ?>

        <section class="ai-images-layout">
            <form class="ai-images-builder" data-ai-images-form>
                <div class="ai-images-card">
                    <div class="ai-images-card-head">
                        <div>
                            <h2>Prompt principal</h2>
                            <p>Describe con claridad la escena, producto, fondo, iluminacion y composicion que esperas.</p>
                        </div>
                    </div>

                    <label class="ai-images-field">
                        <span>Idea visual</span>
                        <textarea name="prompt" rows="7" placeholder="Ejemplo: fotografa editorial de una botella de salsa macha premium sobre mesa de piedra, iluminacion cinematografica, fondo oscuro, gotas de aceite visibles, estilo comercial de lujo"></textarea>
                    </label>

                    <div class="ai-images-quick-prompts">
                        <button type="button" class="ai-images-quick-button" data-ai-prompt-insert="Fotografia de producto hiperrealista, iluminacion de estudio, fondo limpio, detalle premium.">Producto premium</button>
                        <button type="button" class="ai-images-quick-button" data-ai-prompt-insert="Escena aspiracional con persona usando el producto, composicion editorial, sensacion de marca moderna.">Lifestyle</button>
                        <button type="button" class="ai-images-quick-button" data-ai-prompt-insert="Creativo para anuncio digital, enfoque de venta, espacio para copy, contraste alto.">Anuncio</button>
                    </div>
                </div>

                <div class="ai-images-card">
                    <div class="ai-images-card-head">
                        <div>
                            <h2>Direccion creativa</h2>
                            <p>Controla el estilo general y el formato de salida sin entrar a configuraciones complicadas.</p>
                        </div>
                    </div>

                    <div class="ai-images-style-grid">
                        <?php foreach (aiImagesStylePresets() as $preset): ?>
                            <button
                                type="button"
                                class="ai-images-style-card<?= $preset['key'] === $initialState['style'] ? ' is-active' : ''; ?>"
                                data-ai-style-option="<?= htmlspecialchars((string) $preset['key'], ENT_QUOTES, 'UTF-8'); ?>"
                            >
                                <strong><?= htmlspecialchars((string) $preset['label'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span><?= htmlspecialchars((string) $preset['hint'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <input type="hidden" name="style" value="<?= htmlspecialchars((string) $initialState['style'], ENT_QUOTES, 'UTF-8'); ?>" data-ai-style-input>

                    <div class="ai-images-options-grid">
                        <label class="ai-images-field">
                            <span>Relacion de aspecto</span>
                            <select name="aspect_ratio">
                                <?php foreach (aiImagesAspectRatios() as $ratio): ?>
                                    <option value="<?= htmlspecialchars((string) $ratio['key'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) $ratio['label'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="ai-images-field">
                            <span>Cantidad</span>
                            <select name="quantity">
                                <option value="1">1 imagen</option>
                                <option value="2">2 imagenes</option>
                                <option value="3">3 imagenes</option>
                                <option value="4">4 imagenes</option>
                            </select>
                        </label>
                    </div>
                </div>

                <div class="ai-images-card">
                    <div class="ai-images-card-head">
                        <div>
                            <h2>Contexto de marca</h2>
                            <p>Agrega detalles para que el resultado mantenga una linea visual mas consistente con el proyecto.</p>
                        </div>
                    </div>

                    <label class="ai-images-field">
                        <span>Notas de marca</span>
                        <textarea name="brand_note" rows="3" placeholder="Ejemplo: usar paleta roja y crema, transmitir sofisticacion artesanal, evitar look generico de stock."></textarea>
                    </label>

                    <label class="ai-images-field">
                        <span>Que evitar</span>
                        <textarea name="negative_prompt" rows="3" placeholder="Ejemplo: manos deformes, texto ilegible, packaging incorrecto, fondo saturado."></textarea>
                    </label>
                </div>

                <div class="ai-images-actions">
                    <button type="submit" class="ai-images-primary"<?= $providerReady ? '' : ' data-disabled-provider="true"'; ?>>
                        <span class="material-symbols-rounded">auto_awesome</span>
                        <span><?= $providerReady ? 'Generar imagenes' : 'Preparar generador'; ?></span>
                    </button>
                </div>
            </form>

            <aside class="ai-images-preview">
                <div class="ai-images-card">
                    <div class="ai-images-card-head">
                        <div>
                            <h2>Vista previa creativa</h2>
                            <p>Una referencia visual del brief antes de lanzar la generacion.</p>
                        </div>
                    </div>

                    <div class="ai-images-preview-stage" data-ai-preview-stage>
                        <div class="ai-images-preview-badge" data-ai-preview-style>Foto de producto</div>
                        <div class="ai-images-preview-ratio" data-ai-preview-ratio>1:1</div>
                        <strong data-ai-preview-headline>Tu concepto aparecera aqui</strong>
                        <p data-ai-preview-copy>Describe la imagen que quieres crear y la herramienta armara un brief visual mas claro antes de enviarlo al motor de IA.</p>
                    </div>
                </div>

                <div class="ai-images-card">
                    <div class="ai-images-card-head">
                        <div>
                            <h2>Resultados</h2>
                            <p>Las imagenes generadas apareceran aqui en cuanto el proveedor este conectado.</p>
                        </div>
                    </div>

                    <div class="ai-images-results" data-ai-results>
                        <div class="ai-images-empty">
                            <strong>Aun no hay imagenes generadas</strong>
                            <p>Prepara tu prompt y ejecuta la generacion para ver aqui las salidas del modelo.</p>
                        </div>
                    </div>
                </div>
            </aside>
        </section>

        <script id="ai-images-state" type="application/json"><?= json_encode([
            'provider_ready' => $providerReady,
            'setup_message' => $setupMessage,
            'styles' => aiImagesStylePresets(),
            'initial_state' => $initialState,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
        <script src="tool-asset.php?launch=<?= htmlspecialchars($launchToken, ENT_QUOTES, 'UTF-8'); ?>&asset=app.js"></script>
    <?php endif; ?>
</div>
