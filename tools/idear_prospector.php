<?php
// 1. CARGA OBLIGATORIA
require_once __DIR__ . '/../src/Bootstrap.php';
\App\Bootstrap::protect(); // Protege la página (Login + Pago)

$respuesta = "";

// 2. LÓGICA DEL POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_usuario = $_POST['input_data'];
    $motor = $_POST['ai_provider']; // 'openai' o 'gemini'

    // AQUÍ DEFINES LA PERSONALIDAD DE LA HERRAMIENTA
    $system_prompt = "Eres un Experto en Buyer Personas. Tu tarea es analizar la descripción del negocio del usuario y generar una tabla con: Miedos, Deseos y Objeciones del cliente ideal.";
    
    // LLAMADA A LA CLASE DE IA
    $respuesta = \App\AI::generate($input_usuario, $system_prompt, $motor);
}

// 3. VISTA (HTML)
include __DIR__ . '/../includes/header.php'; 
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 p-0">
            <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        </div>

        <div class="col-md-10 p-5">
            <h2 class="mb-4">🕵️ Prospector Sintético (Idear)</h2>
            
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Describe tu producto o negocio:</label>
                            <textarea name="input_data" class="form-control" rows="4" required placeholder="Ej: Vendo seguros de vida para millenials..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Motor de IA:</label>
                            <select name="ai_provider" class="form-select">
                                <option value="openai">OpenAI (GPT-4o)</option>
                                <option value="gemini">Google Gemini 1.5</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">Generar Análisis</button>
                    </form>
                </div>
            </div>

            <?php if ($respuesta): ?>
            <div class="card bg-light border-primary">
                <div class="card-header bg-primary text-white">Resultado de la IA</div>
                <div class="card-body">
                    <p><?= nl2br(htmlspecialchars($respuesta)) ?></p> 
                    
                    <button class="btn btn-sm btn-outline-secondary mt-2" onclick="navigator.clipboard.writeText(`<?= addslashes($respuesta) ?>`)">
                        Copiar Texto
                    </button>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>