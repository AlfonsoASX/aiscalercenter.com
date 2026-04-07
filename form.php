<?php
declare(strict_types=1);

use AiScaler\Forms\FormRepository;

require_once __DIR__ . '/modules/forms/bootstrap.php';

$repository = new FormRepository();
$identifier = trim((string) ($_GET['f'] ?? ''));
$form = null;
$notice = null;
$error = null;
$answers = [];

try {
    if ($identifier === '') {
        throw new InvalidArgumentException('No encontramos el formulario que quieres responder.');
    }

    $form = $repository->getPublicForm($identifier);

    if (!is_array($form)) {
        throw new RuntimeException('Este formulario no existe o ya no esta disponible.');
    }

    $fields = formPublicFields($form);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            throw new RuntimeException('No fue posible guardar la respuesta.');
        }

        $answers = formPublicAnswersFromPost($fields, $_POST);
        formPublicValidateAnswers($fields, $answers);

        $metadata = [
            'submitted_at' => gmdate('c'),
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            'referer' => substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 500),
            'accept_language' => substr((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), 0, 120),
            'ip_hash' => hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . (string) ($form['public_id'] ?? '')),
        ];

        $repository->submitPublicResponse((string) ($form['public_id'] ?? $identifier), $answers, $metadata);
        $notice = (string) (($form['settings']['thank_you_message'] ?? null) ?: 'Gracias, recibimos tu respuesta.');
        $answers = [];
    }
} catch (Throwable $exception) {
    $error = normalizeFormBuilderException($exception);
}

$pageTitle = is_array($form) ? (string) ($form['title'] ?? 'Formulario') : 'Formulario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> - AiScaler Center</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,500,0,0">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Roboto, sans-serif;
            color: #202124;
            background:
                radial-gradient(circle at top right, rgba(47, 124, 239, 0.18), transparent 30rem),
                linear-gradient(180deg, #eef4ff 0%, #f8fafc 45%, #ffffff 100%);
        }
        .public-form-shell {
            width: min(820px, calc(100% - 2rem));
            margin: 0 auto;
            padding: 2rem 0 3rem;
        }
        .public-form-logo {
            width: auto;
            height: 2.7rem;
            object-fit: contain;
            margin-bottom: 1.5rem;
        }
        .public-form-card,
        .public-form-notice {
            border: 1px solid rgba(19, 42, 74, 0.08);
            border-radius: 1.5rem;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 18px 44px rgba(16, 24, 40, 0.08);
        }
        .public-form-card {
            padding: clamp(1.25rem, 4vw, 2rem);
        }
        .public-form-card h1 {
            margin: 0;
            font-size: clamp(2rem, 6vw, 4rem);
            line-height: 0.95;
            letter-spacing: -0.055em;
            font-weight: 500;
        }
        .public-form-card p {
            color: #6b7280;
            line-height: 1.7;
        }
        .public-form-form {
            margin-top: 1.5rem;
            display: grid;
            gap: 1rem;
        }
        .public-form-field {
            display: grid;
            gap: 0.45rem;
        }
        .public-form-label {
            font-weight: 700;
            color: #374151;
        }
        .public-form-help {
            color: #6b7280;
            font-size: 0.92rem;
            line-height: 1.55;
        }
        .public-form-field input,
        .public-form-field textarea,
        .public-form-field select {
            width: 100%;
            min-height: 3.2rem;
            border: 1px solid rgba(19, 42, 74, 0.14);
            border-radius: 1rem;
            background: #ffffff;
            padding: 0.85rem 1rem;
            color: #111827;
            font: inherit;
        }
        .public-form-options {
            display: grid;
            gap: 0.65rem;
        }
        .public-form-option {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            padding: 0.85rem 1rem;
            border: 1px solid rgba(19, 42, 74, 0.1);
            border-radius: 1rem;
            background: #f8fafc;
        }
        .public-form-option input {
            width: auto;
            min-height: auto;
        }
        .public-form-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            min-height: 3.2rem;
            border: 0;
            border-radius: 999px;
            background: #1f5fd6;
            color: #ffffff;
            padding: 0 1.25rem;
            font-weight: 800;
            cursor: pointer;
        }
        .public-form-notice {
            margin-bottom: 1rem;
            padding: 1rem 1.15rem;
            font-weight: 700;
        }
        .public-form-notice--error {
            color: #b3261e;
            background: rgba(217, 48, 37, 0.09);
        }
        .public-form-notice--success {
            color: #0b8043;
            background: rgba(15, 157, 88, 0.1);
        }
        .public-form-honeypot {
            position: absolute;
            left: -10000px;
            top: auto;
            width: 1px;
            height: 1px;
            overflow: hidden;
        }
        @media (max-width: 640px) {
            .public-form-shell {
                width: min(100% - 1rem, 820px);
                padding-top: 1rem;
            }
        }
    </style>
</head>
<body>
    <main class="public-form-shell">
        <img class="public-form-logo" src="img/logoAiScalerCenter.png" alt="AiScaler Center Logo">

        <?php if ($error !== null): ?>
            <div class="public-form-notice public-form-notice--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($notice !== null): ?>
            <div class="public-form-notice public-form-notice--success"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if (is_array($form) && $notice === null): ?>
            <section class="public-form-card">
                <h1><?= htmlspecialchars((string) ($form['title'] ?? 'Formulario'), ENT_QUOTES, 'UTF-8'); ?></h1>
                <?php if (trim((string) ($form['description'] ?? '')) !== ''): ?>
                    <p><?= htmlspecialchars((string) ($form['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>

                <form method="post" class="public-form-form">
                    <label class="public-form-honeypot" aria-hidden="true">
                        Sitio web
                        <input type="text" name="website" tabindex="-1" autocomplete="off">
                    </label>

                    <?php foreach (formPublicFields($form) as $field): ?>
                        <?= formPublicRenderField($field, $answers); ?>
                    <?php endforeach; ?>

                    <button type="submit" class="public-form-button">
                        <span class="material-symbols-rounded">send</span>
                        <span><?= htmlspecialchars((string) (($form['settings']['submit_label'] ?? null) ?: 'Enviar respuesta'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </button>
                </form>
            </section>
        <?php elseif ($notice !== null): ?>
            <section class="public-form-card">
                <h1>Respuesta enviada</h1>
                <p>Puedes cerrar esta pagina.</p>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>

<?php
function formPublicFields(array $form): array
{
    $fields = $form['fields'] ?? [];

    if (is_string($fields)) {
        $decoded = json_decode($fields, true);
        $fields = is_array($decoded) ? $decoded : [];
    }

    return array_values(array_filter(array_map('formPublicNormalizeField', is_array($fields) ? $fields : [])));
}

function formPublicNormalizeField(mixed $field): ?array
{
    if (!is_array($field)) {
        return null;
    }

    return [
        'id' => trim((string) ($field['id'] ?? '')),
        'type' => trim((string) ($field['type'] ?? 'short_text')),
        'label' => trim((string) ($field['label'] ?? '')),
        'placeholder' => trim((string) ($field['placeholder'] ?? '')),
        'help_text' => trim((string) ($field['help_text'] ?? '')),
        'required' => filter_var($field['required'] ?? false, FILTER_VALIDATE_BOOL),
        'options' => is_array($field['options'] ?? null) ? $field['options'] : [],
    ];
}

function formPublicAnswersFromPost(array $fields, array $post): array
{
    $answers = [];

    foreach ($fields as $field) {
        $fieldId = (string) ($field['id'] ?? '');
        $inputName = 'field_' . $fieldId;
        $rawValue = $post[$inputName] ?? null;
        $type = (string) ($field['type'] ?? 'short_text');

        if ($type === 'multiple_choice') {
            $value = is_array($rawValue) ? array_values(array_map('strval', $rawValue)) : [];
        } else {
            $value = is_array($rawValue) ? implode(', ', array_map('strval', $rawValue)) : trim((string) $rawValue);
        }

        $answers[$fieldId] = [
            'field_id' => $fieldId,
            'type' => $type,
            'label' => (string) ($field['label'] ?? ''),
            'value' => $value,
        ];
    }

    return $answers;
}

function formPublicValidateAnswers(array $fields, array $answers): void
{
    foreach ($fields as $field) {
        $fieldId = (string) ($field['id'] ?? '');
        $answer = $answers[$fieldId]['value'] ?? null;

        if (empty($field['required'])) {
            $isRequired = false;
        } else {
            $isRequired = true;
        }

        $isEmpty = is_array($answer) ? $answer === [] : trim((string) $answer) === '';

        if ($isRequired && $isEmpty) {
            throw new InvalidArgumentException('Completa el campo obligatorio: ' . (string) ($field['label'] ?? 'Campo'));
        }

        if ($isEmpty) {
            continue;
        }

        $type = (string) ($field['type'] ?? '');

        if ($type === 'email' && filter_var((string) $answer, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Escribe un correo valido en: ' . (string) ($field['label'] ?? 'Correo'));
        }

        if (in_array($type, ['single_choice', 'multiple_choice'], true)) {
            $allowed = array_values(array_filter(array_map(static function ($option): string {
                return is_array($option) ? (string) ($option['label'] ?? $option['value'] ?? '') : (string) $option;
            }, (array) ($field['options'] ?? []))));
            $selected = is_array($answer) ? $answer : [(string) $answer];

            foreach ($selected as $selectedValue) {
                if (!in_array((string) $selectedValue, $allowed, true)) {
                    throw new InvalidArgumentException('Selecciona una opcion valida en: ' . (string) ($field['label'] ?? 'Campo'));
                }
            }
        }
    }
}

function formPublicRenderField(array $field, array $answers): string
{
    $fieldId = (string) ($field['id'] ?? '');
    $type = (string) ($field['type'] ?? 'short_text');
    $name = 'field_' . $fieldId;
    $answer = $answers[$fieldId]['value'] ?? '';
    $required = !empty($field['required']) ? ' required' : '';
    $label = (string) ($field['label'] ?? '');

    ob_start();
    ?>
    <label class="public-form-field">
        <span class="public-form-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?><?= !empty($field['required']) ? ' *' : ''; ?></span>
        <?php if (trim((string) ($field['help_text'] ?? '')) !== ''): ?>
            <span class="public-form-help"><?= htmlspecialchars((string) ($field['help_text'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>

        <?php if ($type === 'long_text'): ?>
            <textarea name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" rows="5" placeholder="<?= htmlspecialchars((string) ($field['placeholder'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"<?= $required; ?>><?= htmlspecialchars((string) $answer, ENT_QUOTES, 'UTF-8'); ?></textarea>
        <?php elseif (in_array($type, ['single_choice', 'multiple_choice'], true)): ?>
            <span class="public-form-options">
                <?php foreach ((array) ($field['options'] ?? []) as $option): ?>
                    <?php $optionLabel = is_array($option) ? (string) ($option['label'] ?? $option['value'] ?? '') : (string) $option; ?>
                    <?php $isSelected = is_array($answer) ? in_array($optionLabel, $answer, true) : (string) $answer === $optionLabel; ?>
                    <span class="public-form-option">
                        <input
                            type="<?= $type === 'single_choice' ? 'radio' : 'checkbox'; ?>"
                            name="<?= htmlspecialchars($name . ($type === 'multiple_choice' ? '[]' : ''), ENT_QUOTES, 'UTF-8'); ?>"
                            value="<?= htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8'); ?>"
                            <?= $isSelected ? ' checked' : ''; ?>
                        >
                        <span><?= htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                    </span>
                <?php endforeach; ?>
            </span>
        <?php else: ?>
            <input
                type="<?= htmlspecialchars(match ($type) {
                    'email' => 'email',
                    'phone' => 'tel',
                    'number' => 'number',
                    'date' => 'date',
                    default => 'text',
                }, ENT_QUOTES, 'UTF-8'); ?>"
                name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"
                value="<?= htmlspecialchars((string) $answer, ENT_QUOTES, 'UTF-8'); ?>"
                placeholder="<?= htmlspecialchars((string) ($field['placeholder'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                <?= $required; ?>
            >
        <?php endif; ?>
    </label>
    <?php

    return (string) ob_get_clean();
}
