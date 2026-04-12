<?php
declare(strict_types=1);

use AiScaler\Forms\FormRepository;

require_once __DIR__ . '/lib/pwa.php';
require_once __DIR__ . '/modules/forms/bootstrap.php';

$repository = new FormRepository();
$action = trim((string) ($_GET['action'] ?? ''));

if ($action === 'track') {
    formPublicHandleTrackingRequest($repository);
}

$identifier = trim((string) ($_GET['f'] ?? appCurrentPublicIdentifier()));
$form = null;
$fields = [];
$notice = null;
$error = null;
$answers = [];
$sessionKey = '';
$initialStep = 0;

try {
    if ($identifier === '') {
        throw new InvalidArgumentException('No encontramos el formulario que quieres responder.');
    }

    $form = $repository->getPublicForm($identifier);

    if (!is_array($form)) {
        throw new RuntimeException('Este formulario no existe o ya no esta disponible.');
    }

    $fields = formPublicFields($form);
    $sessionKey = formPublicResolveSessionKey((string) ($form['public_id'] ?? $identifier));

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            throw new RuntimeException('No fue posible guardar la respuesta.');
        }

        $submittedSessionKey = trim((string) ($_POST['form_session_key'] ?? ''));

        if ($submittedSessionKey !== '') {
            $sessionKey = formPublicResolveSessionKey((string) ($form['public_id'] ?? $identifier), $submittedSessionKey);
        }

        $answers = formPublicAnswersFromPost($fields, $_POST);
        formPublicValidateAnswers($fields, $answers);

        $metadata = [
            'submitted_at' => gmdate('c'),
            'session_key' => $sessionKey,
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            'referer' => substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 500),
            'accept_language' => substr((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), 0, 120),
            'ip_hash' => hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . (string) ($form['public_id'] ?? '')),
        ];

        $repository->submitPublicResponse((string) ($form['public_id'] ?? $identifier), $answers, $metadata);

        if ($sessionKey !== '') {
            $repository->trackPublicSession(
                (string) ($form['public_id'] ?? $identifier),
                $sessionKey,
                'complete',
                formPublicCountAnswered($answers),
                count($fields),
                ['source' => 'submit']
            );
        }

        $notice = (string) (($form['settings']['thank_you_message'] ?? null) ?: 'Gracias, recibimos tu respuesta.');
        $answers = [];
    }
} catch (Throwable $exception) {
    $error = normalizeFormBuilderException($exception);
}

if (is_array($form) && $fields !== [] && $answers !== []) {
    $initialStep = formPublicResolveInitialStep($fields, $answers);
}

$pageTitle = is_array($form) ? (string) ($form['title'] ?? 'Formulario') : 'Formulario';
$trackUrl = '/form.php?action=track';
$initialAnsweredCount = formPublicCountAnswered($answers);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> - AiScaler Center</title>
    <?= renderPwaHead([
        'description' => 'Completa este formulario de AiScaler Center desde cualquier dispositivo.',
        'background_color' => '#f3f4f6',
    ]); ?>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,500,0,0">
    <style>
        * { box-sizing: border-box; }
        :root {
            --public-form-accent: #5f6368;
            --public-form-accent-strong: #202124;
            --public-form-border: rgba(60, 64, 67, 0.14);
            --public-form-surface: rgba(255, 255, 255, 0.96);
            --public-form-soft: #f8f9fa;
            --public-form-soft-strong: #eef1f3;
        }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Manrope, sans-serif;
            color: #202124;
            background:
                radial-gradient(circle at top right, rgba(95, 99, 104, 0.12), transparent 28rem),
                linear-gradient(180deg, #f3f4f6 0%, #f8fafc 48%, #ffffff 100%);
        }
        .public-form-shell {
            width: min(760px, calc(100% - 1.5rem));
            margin: 0 auto;
            padding: 1.1rem 0 2rem;
        }
        .public-form-notice,
        .public-form-card {
            border: 1px solid var(--public-form-border);
            border-radius: 1.5rem;
            background: var(--public-form-surface);
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
        }
        .public-form-notice {
            margin-bottom: 1rem;
            padding: 1rem 1.15rem;
            font-weight: 700;
        }
        .public-form-notice--error {
            color: #b3261e;
            background: rgba(217, 48, 37, 0.08);
        }
        .public-form-notice--success {
            color: #0b8043;
            background: rgba(15, 157, 88, 0.1);
        }
        .public-form-card {
            padding: clamp(1rem, 4vw, 1.7rem);
        }
        .public-form-header {
            display: grid;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .public-form-kicker {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            width: fit-content;
            border-radius: 999px;
            background: var(--public-form-soft);
            color: var(--public-form-accent);
            padding: 0.4rem 0.7rem;
            font-size: 0.8rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .public-form-header h1,
        .public-form-step-title,
        .public-form-success-title {
            margin: 0;
            line-height: 0.95;
            letter-spacing: -0.05em;
        }
        .public-form-header h1 {
            font-size: clamp(2rem, 7vw, 3.9rem);
        }
        .public-form-description,
        .public-form-step-help,
        .public-form-step-hint,
        .public-form-progress-caption,
        .public-form-footer-note {
            color: #5f6368;
            line-height: 1.65;
        }
        .public-form-progress {
            display: grid;
            gap: 0.6rem;
        }
        .public-form-progress-track {
            width: 100%;
            height: 0.7rem;
            border-radius: 999px;
            background: var(--public-form-soft-strong);
            overflow: hidden;
        }
        .public-form-progress-bar {
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #3c4043, #5f6368);
            transition: width 180ms ease;
        }
        .public-form-form {
            display: grid;
            gap: 1rem;
        }
        .public-form-step {
            display: grid;
            gap: 1rem;
        }
        .public-form-step[hidden] {
            display: none;
        }
        .public-form-step-head {
            display: grid;
            gap: 0.45rem;
        }
        .public-form-step-index {
            color: var(--public-form-accent);
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .public-form-step-title {
            font-size: clamp(1.8rem, 5vw, 3rem);
        }
        .public-form-required {
            color: #b3261e;
        }
        .public-form-field input,
        .public-form-field textarea,
        .public-form-field select {
            width: 100%;
            min-height: 3.35rem;
            border: 1px solid var(--public-form-border);
            border-radius: 1.1rem;
            background: #ffffff;
            padding: 0.95rem 1rem;
            color: #111827;
            font: inherit;
            transition: border-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
        }
        .public-form-field textarea {
            min-height: 8.5rem;
            resize: vertical;
        }
        .public-form-field input:focus,
        .public-form-field textarea:focus,
        .public-form-field select:focus {
            outline: none;
            border-color: #5f6368;
            box-shadow: 0 0 0 4px rgba(95, 99, 104, 0.12);
        }
        .public-form-options {
            display: grid;
            gap: 0.75rem;
        }
        .public-form-option {
            position: relative;
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: 0.85rem;
            align-items: center;
            border: 1px solid var(--public-form-border);
            border-radius: 1.2rem;
            background: var(--public-form-soft);
            padding: 1rem 1.05rem;
            cursor: pointer;
            transition: border-color 160ms ease, transform 160ms ease, background 160ms ease, box-shadow 160ms ease;
        }
        .public-form-option:hover,
        .public-form-option:focus-within {
            border-color: rgba(60, 64, 67, 0.24);
            transform: translateY(-1px);
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
        }
        .public-form-option input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .public-form-option.is-selected {
            border-color: rgba(60, 64, 67, 0.34);
            background: #eceff1;
        }
        .public-form-option-letter {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            border-radius: 999px;
            background: #ffffff;
            border: 1px solid rgba(60, 64, 67, 0.14);
            color: var(--public-form-accent-strong);
            font-size: 0.88rem;
            font-weight: 800;
        }
        .public-form-step-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            flex-wrap: wrap;
            padding-top: 0.4rem;
        }
        .public-form-button,
        .public-form-button-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            min-height: 3.15rem;
            border-radius: 999px;
            padding: 0 1.15rem;
            font: inherit;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
        }
        .public-form-button {
            border: 0;
            background: #202124;
            color: #ffffff;
        }
        .public-form-button-secondary {
            border: 1px solid var(--public-form-border);
            background: #ffffff;
            color: #202124;
        }
        .public-form-step-error {
            display: none;
            border-radius: 1rem;
            background: rgba(217, 48, 37, 0.08);
            color: #b3261e;
            padding: 0.85rem 1rem;
            font-weight: 700;
        }
        .public-form-step-error.is-visible {
            display: block;
        }
        .public-form-honeypot {
            position: absolute;
            left: -10000px;
            top: auto;
            width: 1px;
            height: 1px;
            overflow: hidden;
        }
        .public-form-logo-wrap {
            display: grid;
            justify-items: center;
            gap: 0.75rem;
            margin-top: 1rem;
        }
        .public-form-logo {
            width: auto;
            height: 2.3rem;
            object-fit: contain;
            opacity: 0.86;
        }
        .public-form-success {
            display: grid;
            gap: 0.7rem;
            text-align: center;
        }
        .public-form-success .material-symbols-rounded {
            justify-self: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 4rem;
            height: 4rem;
            border-radius: 50%;
            background: rgba(15, 157, 88, 0.1);
            color: #0b8043;
            font-size: 2rem;
        }
        @media (max-width: 640px) {
            .public-form-shell {
                width: min(100% - 1rem, 760px);
                padding-top: 0.75rem;
            }
            .public-form-step-actions {
                align-items: stretch;
                flex-direction: column-reverse;
            }
            .public-form-button,
            .public-form-button-secondary {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <main class="public-form-shell">
        <?php if ($error !== null): ?>
            <div class="public-form-notice public-form-notice--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($notice !== null): ?>
            <div class="public-form-notice public-form-notice--success"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if (is_array($form) && $notice === null): ?>
            <section class="public-form-card">
                <div class="public-form-header">
                    <div>
                        <h1><?= htmlspecialchars((string) ($form['title'] ?? 'Formulario'), ENT_QUOTES, 'UTF-8'); ?></h1>
                        <?php if (trim((string) ($form['description'] ?? '')) !== ''): ?>
                            <p class="public-form-description"><?= htmlspecialchars((string) ($form['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="public-form-progress">
                        <div class="public-form-progress-track">
                            <div class="public-form-progress-bar" data-public-form-progress-bar style="width: <?= $fields === [] ? '100' : htmlspecialchars(number_format((($initialStep + 1) / max(count($fields), 1)) * 100, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>%;"></div>
                        </div>
                        <div class="public-form-progress-caption" data-public-form-progress-caption>
                            <?= $fields === [] ? 'Sin preguntas activas.' : 'Pregunta ' . ($initialStep + 1) . ' de ' . count($fields); ?>
                        </div>
                    </div>
                </div>

                <form
                    method="post"
                    class="public-form-form"
                    data-public-form="true"
                    data-track-url="<?= htmlspecialchars($trackUrl, ENT_QUOTES, 'UTF-8'); ?>"
                    data-public-id="<?= htmlspecialchars((string) ($form['public_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    data-session-key="<?= htmlspecialchars($sessionKey, ENT_QUOTES, 'UTF-8'); ?>"
                    data-start-step="<?= htmlspecialchars((string) $initialStep, ENT_QUOTES, 'UTF-8'); ?>"
                    data-initial-answered="<?= htmlspecialchars((string) $initialAnsweredCount, ENT_QUOTES, 'UTF-8'); ?>"
                >
                    <label class="public-form-honeypot" aria-hidden="true">
                        Sitio web
                        <input type="text" name="website" tabindex="-1" autocomplete="off">
                    </label>
                    <input type="hidden" name="form_session_key" value="<?= htmlspecialchars($sessionKey, ENT_QUOTES, 'UTF-8'); ?>" data-public-form-session-key>

                    <?php foreach ($fields as $index => $field): ?>
                        <?= formPublicRenderStep($field, $answers, $index, count($fields)); ?>
                    <?php endforeach; ?>
                </form>
            </section>
        <?php elseif ($notice !== null): ?>
            <section class="public-form-card">
                <div class="public-form-success">
                    <span class="material-symbols-rounded">check_circle</span>
                    <h1 class="public-form-success-title">Respuesta enviada</h1>
                    <p class="public-form-description">Todo listo. Puedes cerrar esta pagina.</p>
                </div>
            </section>
        <?php endif; ?>

        <div class="public-form-logo-wrap">
            <img class="public-form-logo" src="img/logoAiScalerCenter.png" alt="AiScaler Center Logo">
            <p class="public-form-footer-note">Impulsado por AiScaler parte de ASX.mx</p>
        </div>
    </main>

    <?php if (is_array($form) && $notice === null && $fields !== []): ?>
        <script>
            (() => {
                const form = document.querySelector('[data-public-form="true"]');

                if (!(form instanceof HTMLFormElement)) {
                    return;
                }

                const steps = Array.from(form.querySelectorAll('[data-public-form-step]'));
                const progressBar = document.querySelector('[data-public-form-progress-bar]');
                const progressCaption = document.querySelector('[data-public-form-progress-caption]');
                const sessionKeyInput = form.querySelector('[data-public-form-session-key]');
                const publicId = String(form.dataset.publicId || '').trim();
                const sessionKey = String(form.dataset.sessionKey || '').trim();
                const trackUrl = String(form.dataset.trackUrl || '').trim();
                const totalSteps = steps.length;
                const startStep = Math.min(Math.max(Number(form.dataset.startStep || 0), 0), Math.max(totalSteps - 1, 0));
                let currentStep = startStep;
                let started = Number(form.dataset.initialAnswered || 0) > 0;
                let completed = false;
                let abandonmentSent = false;

                if (sessionKeyInput instanceof HTMLInputElement && sessionKey !== '') {
                    sessionKeyInput.value = sessionKey;
                }

                const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

                const currentVisibleStep = () => steps[currentStep] || null;

                const answerCount = () => steps.reduce((count, step) => {
                    const type = String(step.dataset.stepType || '');

                    if (type === 'single_choice') {
                        return count + (step.querySelector('input[type="radio"]:checked') ? 1 : 0);
                    }

                    if (type === 'multiple_choice') {
                        return count + (step.querySelectorAll('input[type="checkbox"]:checked').length > 0 ? 1 : 0);
                    }

                    const input = step.querySelector('input, textarea, select');
                    return count + (input && String(input.value || '').trim() !== '' ? 1 : 0);
                }, 0);

                const updateSelectedStates = () => {
                    form.querySelectorAll('[data-public-form-option]').forEach((option) => {
                        const input = option.querySelector('input');
                        option.classList.toggle('is-selected', Boolean(input?.checked));
                    });
                };

                const updateProgress = () => {
                    if (progressBar instanceof HTMLElement) {
                        const percentage = totalSteps === 0 ? 100 : ((currentStep + 1) / totalSteps) * 100;
                        progressBar.style.width = `${percentage}%`;
                    }

                    if (progressCaption instanceof HTMLElement) {
                        progressCaption.textContent = totalSteps === 0
                            ? 'Sin preguntas activas.'
                            : `Pregunta ${currentStep + 1} de ${totalSteps}`;
                    }
                };

                const focusCurrentField = () => {
                    const step = currentVisibleStep();

                    if (!(step instanceof HTMLElement)) {
                        return;
                    }

                    const target = step.querySelector('[data-public-form-autofocus]');

                    if (target instanceof HTMLElement) {
                        window.requestAnimationFrame(() => {
                            target.focus({ preventScroll: true });
                            if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) {
                                target.select?.();
                            }
                        });
                    }
                };

                const showStep = (index) => {
                    currentStep = Math.min(Math.max(index, 0), Math.max(totalSteps - 1, 0));

                    steps.forEach((step, stepIndex) => {
                        step.hidden = stepIndex !== currentStep;
                    });

                    updateProgress();
                    updateSelectedStates();
                    focusCurrentField();
                };

                const setStepError = (step, message = '') => {
                    const errorNode = step.querySelector('[data-public-form-step-error]');

                    if (!(errorNode instanceof HTMLElement)) {
                        return;
                    }

                    errorNode.textContent = message;
                    errorNode.classList.toggle('is-visible', message.trim() !== '');
                };

                const validateStep = (step) => {
                    const required = String(step.dataset.stepRequired || '') === 'true';
                    const type = String(step.dataset.stepType || '');

                    if (!required) {
                        setStepError(step, '');
                        return true;
                    }

                    if (type === 'single_choice' && step.querySelector('input[type="radio"]:checked')) {
                        setStepError(step, '');
                        return true;
                    }

                    if (type === 'multiple_choice' && step.querySelectorAll('input[type="checkbox"]:checked').length > 0) {
                        setStepError(step, '');
                        return true;
                    }

                    const input = step.querySelector('input, textarea, select');
                    const value = input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement || input instanceof HTMLSelectElement
                        ? String(input.value || '').trim()
                        : '';

                    if (value !== '') {
                        setStepError(step, '');
                        return true;
                    }

                    setStepError(step, 'Completa esta pregunta antes de continuar.');
                    return false;
                };

                const track = (eventName, extraMetadata = {}, useBeacon = false) => {
                    if (trackUrl === '' || publicId === '' || sessionKey === '') {
                        return;
                    }

                    const payload = {
                        public_id: publicId,
                        session_key: sessionKey,
                        event: eventName,
                        answered_count: answerCount(),
                        question_count: totalSteps,
                        metadata: {
                            current_step: currentStep + 1,
                            ...extraMetadata,
                        },
                    };

                    if (useBeacon && navigator.sendBeacon) {
                        const body = new Blob([JSON.stringify(payload)], { type: 'application/json' });
                        navigator.sendBeacon(trackUrl, body);
                        return;
                    }

                    void fetch(trackUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        credentials: 'same-origin',
                        keepalive: useBeacon,
                        body: JSON.stringify(payload),
                    }).catch(() => {});
                };

                const sendAbandonIfNeeded = (reason) => {
                    if (abandonmentSent || completed || !started || answerCount() <= 0) {
                        return;
                    }

                    abandonmentSent = true;
                    track('abandon', { action: reason }, true);
                };

                const registerProgress = (metadata = {}) => {
                    if (!started && answerCount() > 0) {
                        started = true;
                        track('start', metadata);
                        return;
                    }

                    if (started) {
                        track('progress', metadata);
                    }
                };

                const moveNext = () => {
                    const step = currentVisibleStep();

                    if (!(step instanceof HTMLElement) || !validateStep(step)) {
                        return;
                    }

                    registerProgress({ action: 'next' });

                    if (currentStep < totalSteps - 1) {
                        showStep(currentStep + 1);
                    }
                };

                const chooseOptionByLetter = (letter) => {
                    const step = currentVisibleStep();

                    if (!(step instanceof HTMLElement)) {
                        return;
                    }

                    const options = Array.from(step.querySelectorAll('[data-public-form-option]'));
                    const index = alphabet.indexOf(letter.toUpperCase());
                    const option = options[index];
                    const input = option?.querySelector('input');

                    if (!(input instanceof HTMLInputElement)) {
                        return;
                    }

                    if (input.type === 'radio') {
                        input.checked = true;
                        updateSelectedStates();
                        registerProgress({ action: 'shortcut' });
                        window.setTimeout(() => {
                            if (currentStep < totalSteps - 1) {
                                showStep(currentStep + 1);
                            } else {
                                form.requestSubmit();
                            }
                        }, 90);
                        return;
                    }

                    if (input.type === 'checkbox') {
                        input.checked = !input.checked;
                        updateSelectedStates();
                        registerProgress({ action: 'shortcut' });
                    }
                };

                form.addEventListener('click', (event) => {
                    const nextButton = event.target.closest('[data-public-form-next]');
                    const backButton = event.target.closest('[data-public-form-back]');
                    const option = event.target.closest('[data-public-form-option]');

                    if (nextButton) {
                        event.preventDefault();
                        moveNext();
                        return;
                    }

                    if (backButton) {
                        event.preventDefault();
                        showStep(currentStep - 1);
                        return;
                    }

                    if (option) {
                        const input = option.querySelector('input[type="radio"]');

                        if (input instanceof HTMLInputElement) {
                            window.setTimeout(() => {
                                updateSelectedStates();
                                registerProgress({ action: 'click' });

                                if (currentStep < totalSteps - 1) {
                                    showStep(currentStep + 1);
                                } else {
                                    form.requestSubmit();
                                }
                            }, 80);
                        }
                    }
                });

                form.addEventListener('change', (event) => {
                    const target = event.target;

                    if (!(target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement || target instanceof HTMLSelectElement)) {
                        return;
                    }

                    updateSelectedStates();
                    registerProgress({ action: 'change' });
                });

                form.addEventListener('keydown', (event) => {
                    const step = currentVisibleStep();

                    if (!(step instanceof HTMLElement)) {
                        return;
                    }

                    const target = event.target;
                    const type = String(step.dataset.stepType || '');
                    const isTextarea = target instanceof HTMLTextAreaElement;
                    const isPlainLetter = event.key.length === 1 && /^[a-z]$/i.test(event.key) && !event.metaKey && !event.ctrlKey && !event.altKey;

                    if (isPlainLetter && (type === 'single_choice' || type === 'multiple_choice')) {
                        event.preventDefault();
                        chooseOptionByLetter(event.key);
                        return;
                    }

                    if (event.key === 'Enter' && !event.shiftKey) {
                        if (target instanceof HTMLInputElement || target instanceof HTMLSelectElement || isTextarea) {
                            event.preventDefault();

                            if (currentStep === totalSteps - 1 && validateStep(step)) {
                                form.requestSubmit();
                                return;
                            }

                            moveNext();
                        }
                    }
                });

                form.addEventListener('submit', (event) => {
                    const step = currentVisibleStep();

                    if (!(step instanceof HTMLElement) || validateStep(step)) {
                        completed = true;
                        abandonmentSent = true;
                        return;
                    }

                    event.preventDefault();
                });

                window.addEventListener('beforeunload', () => {
                    sendAbandonIfNeeded('beforeunload');
                });

                window.addEventListener('pagehide', () => {
                    sendAbandonIfNeeded('pagehide');
                });

                track('view', { action: 'view' });
                showStep(startStep);
            })();
        </script>
    <?php endif; ?>
</body>
</html>

<?php
function formPublicHandleTrackingRequest(FormRepository $repository): never
{
    header('Content-Type: application/json; charset=UTF-8');

    try {
        $raw = file_get_contents('php://input');
        $payload = $raw !== false && trim($raw) !== '' ? json_decode($raw, true) : null;

        if (!is_array($payload)) {
            $payload = $_POST;
        }

        $publicId = trim((string) ($payload['public_id'] ?? ''));
        $sessionKey = trim((string) ($payload['session_key'] ?? ''));
        $event = trim((string) ($payload['event'] ?? 'view'));
        $answeredCount = isset($payload['answered_count']) ? (int) $payload['answered_count'] : null;
        $questionCount = isset($payload['question_count']) ? (int) $payload['question_count'] : null;
        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];

        if ($publicId === '' || $sessionKey === '') {
            throw new InvalidArgumentException('No encontramos la sesion publica del formulario.');
        }

        $repository->trackPublicSession($publicId, $sessionKey, $event, $answeredCount, $questionCount, $metadata);

        echo json_encode([
            'success' => true,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $exception) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => normalizeFormBuilderException($exception),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    exit;
}

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
        $isRequired = !empty($field['required']);
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

        if (in_array($type, ['single_choice', 'multiple_choice', 'dropdown'], true)) {
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

function formPublicResolveInitialStep(array $fields, array $answers): int
{
    foreach ($fields as $index => $field) {
        $fieldId = (string) ($field['id'] ?? '');
        $value = $answers[$fieldId]['value'] ?? null;
        $isEmpty = is_array($value) ? $value === [] : trim((string) $value) === '';

        if ($isEmpty) {
            return $index;
        }
    }

    return 0;
}

function formPublicCountAnswered(array $answers): int
{
    $count = 0;

    foreach ($answers as $answer) {
        $value = $answer['value'] ?? null;
        $isEmpty = is_array($value) ? $value === [] : trim((string) $value) === '';

        if (!$isEmpty) {
            $count++;
        }
    }

    return $count;
}

function formPublicRenderStep(array $field, array $answers, int $index, int $total): string
{
    $fieldId = (string) ($field['id'] ?? '');
    $type = (string) ($field['type'] ?? 'short_text');
    $name = 'field_' . $fieldId;
    $answer = $answers[$fieldId]['value'] ?? '';
    $required = !empty($field['required']);
    $label = (string) ($field['label'] ?? '');
    $stepHint = match ($type) {
        'single_choice' => 'Presiona la letra correcta o haz clic en la opcion para avanzar.',
        'multiple_choice' => 'Puedes marcar varias opciones. Presiona Enter o haz clic en siguiente para continuar.',
        'dropdown' => 'Selecciona una opcion y presiona Enter o haz clic en siguiente.',
        'long_text' => 'Escribe tu respuesta y presiona Enter para seguir. Usa Shift + Enter si quieres un salto de linea.',
        default => 'El campo ya tiene foco. Responde y presiona Enter o haz clic en siguiente.',
    };

    ob_start();
    ?>
    <section
        class="public-form-step"
        data-public-form-step
        data-step-index="<?= htmlspecialchars((string) $index, ENT_QUOTES, 'UTF-8'); ?>"
        data-step-type="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>"
        data-step-required="<?= $required ? 'true' : 'false'; ?>"
        <?= $index > 0 ? ' hidden' : ''; ?>
    >
        <div class="public-form-step-head">
            <span class="public-form-step-index">Pregunta <?= $index + 1; ?> de <?= $total; ?></span>
            <h2 class="public-form-step-title">
                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                <?php if ($required): ?>
                    <span class="public-form-required">*</span>
                <?php endif; ?>
            </h2>
            <?php if (trim((string) ($field['help_text'] ?? '')) !== ''): ?>
                <p class="public-form-step-help"><?= htmlspecialchars((string) ($field['help_text'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <p class="public-form-step-hint"><?= htmlspecialchars($stepHint, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>

        <div class="public-form-step-error" data-public-form-step-error></div>

        <div class="public-form-field">
            <?php if ($type === 'long_text'): ?>
                <textarea
                    name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"
                    rows="5"
                    placeholder="<?= htmlspecialchars((string) ($field['placeholder'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    data-public-form-autofocus
                ><?= htmlspecialchars((string) $answer, ENT_QUOTES, 'UTF-8'); ?></textarea>
            <?php elseif (in_array($type, ['single_choice', 'multiple_choice'], true)): ?>
                <div class="public-form-options">
                    <?php foreach ((array) ($field['options'] ?? []) as $optionIndex => $option): ?>
                        <?php $optionLabel = is_array($option) ? (string) ($option['label'] ?? $option['value'] ?? '') : (string) $option; ?>
                        <?php $isSelected = is_array($answer) ? in_array($optionLabel, $answer, true) : (string) $answer === $optionLabel; ?>
                        <label class="public-form-option<?= $isSelected ? ' is-selected' : ''; ?>" data-public-form-option>
                            <span class="public-form-option-letter"><?= htmlspecialchars(substr('ABCDEFGHIJKLMNOPQRSTUVWXYZ', $optionIndex, 1), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span><?= htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                            <input
                                type="<?= $type === 'single_choice' ? 'radio' : 'checkbox'; ?>"
                                name="<?= htmlspecialchars($name . ($type === 'multiple_choice' ? '[]' : ''), ENT_QUOTES, 'UTF-8'); ?>"
                                value="<?= htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8'); ?>"
                                <?= $isSelected ? ' checked' : ''; ?>
                                <?= $optionIndex === 0 ? ' data-public-form-autofocus' : ''; ?>
                            >
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($type === 'dropdown'): ?>
                <select name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" data-public-form-autofocus>
                    <option value="">Selecciona una opcion</option>
                    <?php foreach ((array) ($field['options'] ?? []) as $option): ?>
                        <?php $optionLabel = is_array($option) ? (string) ($option['label'] ?? $option['value'] ?? '') : (string) $option; ?>
                        <option value="<?= htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8'); ?>"<?= (string) $answer === $optionLabel ? ' selected' : ''; ?>><?= htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input
                    type="<?= htmlspecialchars(match ($type) {
                        'email' => 'email',
                        'phone' => 'tel',
                        'number' => 'number',
                        'date' => 'date',
                        'time' => 'time',
                        default => 'text',
                    }, ENT_QUOTES, 'UTF-8'); ?>"
                    name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"
                    value="<?= htmlspecialchars((string) $answer, ENT_QUOTES, 'UTF-8'); ?>"
                    placeholder="<?= htmlspecialchars((string) ($field['placeholder'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    data-public-form-autofocus
                >
            <?php endif; ?>
        </div>

        <div class="public-form-step-actions">
            <?php if ($index > 0): ?>
                <button type="button" class="public-form-button-secondary" data-public-form-back>
                    <span class="material-symbols-rounded">arrow_back</span>
                    <span>Anterior</span>
                </button>
            <?php else: ?>
                <span></span>
            <?php endif; ?>

            <?php if ($index === $total - 1): ?>
                <button type="submit" class="public-form-button">
                    <span class="material-symbols-rounded">send</span>
                    <span>Enviar respuesta</span>
                </button>
            <?php else: ?>
                <button type="button" class="public-form-button" data-public-form-next>
                    <span>Siguiente</span>
                    <span class="material-symbols-rounded">arrow_forward</span>
                </button>
            <?php endif; ?>
        </div>
    </section>
    <?php

    return (string) ob_get_clean();
}

function formPublicResolveSessionKey(string $publicId, ?string $preferred = null): string
{
    $candidate = trim((string) $preferred);

    if ($candidate === '') {
        $candidate = bin2hex(random_bytes(16));
    }

    return $candidate;
}
