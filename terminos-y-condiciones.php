<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terminos y condiciones - AiScaler Center</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: 'Roboto', sans-serif;
            background: #f5f7fb;
            color: #202124;
        }

        .legal-shell {
            max-width: 920px;
            margin: 0 auto;
            padding: 32px 20px 56px;
        }

        .legal-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .legal-topbar img {
            height: 44px;
            width: auto;
            object-fit: contain;
        }

        .legal-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 18px;
            border-radius: 999px;
            background: #1f5fd6;
            color: #ffffff;
            text-decoration: none;
            font-weight: 600;
        }

        .legal-card {
            margin-top: 28px;
            padding: 32px;
            border-radius: 24px;
            background: #ffffff;
            border: 1px solid rgba(19, 42, 74, 0.08);
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.06);
        }

        .legal-card h1 {
            margin: 0;
            font-size: clamp(2rem, 6vw, 3rem);
            letter-spacing: -0.05em;
        }

        .legal-card p {
            margin-top: 16px;
            line-height: 1.8;
            color: #5f6368;
        }
    </style>
</head>
<body>
    <div class="legal-shell">
        <div class="legal-topbar">
            <img src="img/logoAiScalerCenter.png" alt="AiScaler Center Logo">
            <a class="legal-back" href="index.php?view=app">Volver al panel</a>
        </div>

        <section class="legal-card">
            <h1>Terminos y condiciones</h1>
            <p>Esta pagina queda lista como base para que agregues el texto legal definitivo de AiScaler Center. Aqui podras publicar los terminos de uso, condiciones del servicio, reglas de acceso y cualquier limitacion aplicable a la plataforma.</p>
        </section>
    </div>
</body>
</html>
