<?php
declare(strict_types=1);

use AiScaler\Forms\FormRepository;

require_once __DIR__ . '/../../modules/forms/bootstrap.php';

$toolContext = is_array($toolRuntimeContext ?? null) ? $toolRuntimeContext : [];
$accessToken = trim((string) ($toolContext['access_token'] ?? ''));
$userId = trim((string) ($toolContext['user_id'] ?? ''));
$userEmail = trim((string) ($toolContext['user_email'] ?? ''));
$projectContext = is_array($toolContext['project'] ?? null) ? $toolContext['project'] : [];
$activeProjectId = trim((string) ($projectContext['id'] ?? ''));
$activeProjectName = '';
$repository = new FormRepository();
$fieldTypes = formBuilderFieldTypes();
$notice = null;
$error = null;
$project = null;
$forms = [];
$mode = trim((string) ($_GET['builder'] ?? 'list'));
$currentForm = null;
$formInsights = formBuilderEmptyInsightsSummary();

try {
    if ($accessToken === '' || $userId === '') {
        throw new RuntimeException('No encontramos la sesion segura para guardar formularios. Vuelve a abrir la herramienta desde el panel.');
    }

    if ($activeProjectId === '') {
        throw new RuntimeException('Selecciona un proyecto antes de crear formularios.');
    }

    $project = $repository->findProject($accessToken, $activeProjectId);

    if (!is_array($project)) {
        throw new RuntimeException('No encontramos el proyecto activo para guardar formularios.');
    }

    $activeProjectName = (string) ($project['name'] ?? 'Proyecto');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $postAction = trim((string) ($_POST['form_action'] ?? ''));

        if ($postAction === 'delete_form') {
            $formId = trim((string) ($_POST['form_id'] ?? ''));

            if ($formId === '') {
                throw new InvalidArgumentException('No encontramos el formulario que intentas eliminar.');
            }

            $repository->softDeleteForm($accessToken, $formId, $activeProjectId);
            $notice = ['type' => 'success', 'message' => 'Formulario eliminado correctamente.'];
            $mode = 'list';
        } else {
            $currentForm = formBuilderStateFromPost($_POST);
            $builderAction = trim((string) ($_POST['builder_action'] ?? 'save'));

            if ($builderAction === 'add_field') {
                $newType = trim((string) ($_POST['new_field_type'] ?? 'short_text'));
                $currentForm['fields'][] = formBuilderCreateField($newType, $fieldTypes);
                $notice = ['type' => 'success', 'message' => 'Campo agregado. Recuerda guardar el formulario.'];
                $mode = 'edit';
            } elseif (str_starts_with($builderAction, 'delete:')) {
                $index = (int) substr($builderAction, strlen('delete:'));
                array_splice($currentForm['fields'], $index, 1);
                $notice = ['type' => 'success', 'message' => 'Campo eliminado. Recuerda guardar el formulario.'];
                $mode = 'edit';
            } elseif (str_starts_with($builderAction, 'up:')) {
                $index = (int) substr($builderAction, strlen('up:'));
                $currentForm['fields'] = formBuilderMoveField($currentForm['fields'], $index, -1);
                $mode = 'edit';
            } elseif (str_starts_with($builderAction, 'down:')) {
                $index = (int) substr($builderAction, strlen('down:'));
                $currentForm['fields'] = formBuilderMoveField($currentForm['fields'], $index, 1);
                $mode = 'edit';
            } else {
                $status = $builderAction === 'publish' ? 'published' : (string) ($currentForm['status'] ?? 'draft');
                $payload = formBuilderPayloadForSave($currentForm, $activeProjectId, $userId, $status);
                $currentForm = $repository->saveForm($accessToken, $payload);
                $notice = [
                    'type' => 'success',
                    'message' => $status === 'published' ? 'Formulario publicado y listo para compartir.' : 'Formulario guardado correctamente.',
                ];
                $mode = 'edit';
            }
        }
    }

    if ($mode === 'new' && $currentForm === null) {
        $currentForm = formBuilderEmptyForm();
    }

    if ($mode === 'edit' && $currentForm === null) {
        $formId = trim((string) ($_GET['id'] ?? ''));

        if ($formId === '') {
            $currentForm = formBuilderEmptyForm();
        } else {
            $loadedForm = $repository->findForm($accessToken, $formId, $activeProjectId);

            if (!is_array($loadedForm)) {
                throw new RuntimeException('No encontramos el formulario solicitado.');
            }

            $currentForm = formBuilderNormalizeForm($loadedForm);
        }
    }

    $forms = $repository->listForms($accessToken, $activeProjectId);

    if (is_array($currentForm) && trim((string) ($currentForm['id'] ?? '')) !== '') {
        $responses = $repository->listFormResponses($accessToken, (string) $currentForm['id'], $activeProjectId);
        $sessions = $repository->listFormSessions($accessToken, (string) $currentForm['id'], $activeProjectId);
        $formInsights = formBuilderBuildInsightsSummary($currentForm, $responses, $sessions);
    }
} catch (Throwable $exception) {
    $error = normalizeFormBuilderException($exception);
    $mode = $mode === 'edit' || $mode === 'new' ? $mode : 'list';
}

$isEditorMode = ($mode === 'edit' || $mode === 'new') && is_array($currentForm);
?>
<div class="form-builder-page">
    <?php if (!$isEditorMode): ?>
        <header class="form-builder-hero">
            <div>
                <p class="form-builder-eyebrow">Diseñar</p>
                <h1>Formularios</h1>
                <p>Crea formularios publicos, compartelos sin login y revisa respuestas completas, visitas y abandono en tiempo real.</p>
            </div>

            <a href="tool.php?launch=<?= htmlspecialchars(rawurlencode((string) ($toolContext['launch_token'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>&builder=new" class="form-builder-primary">
                <span class="material-symbols-rounded">add_circle</span>
                <span>Nuevo formulario</span>
            </a>
        </header>
    <?php endif; ?>

    <?php if ($error !== null): ?>
        <div class="form-builder-notice form-builder-notice--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (is_array($notice)): ?>
        <div class="form-builder-notice form-builder-notice--<?= htmlspecialchars((string) ($notice['type'] ?? 'success'), ENT_QUOTES, 'UTF-8'); ?>">
            <?= htmlspecialchars((string) ($notice['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if ($isEditorMode): ?>
        <?= formBuilderRenderEditor($currentForm, $fieldTypes, $toolContext, $formInsights); ?>
    <?php else: ?>
        <?= formBuilderRenderList($forms, $toolContext); ?>
    <?php endif; ?>
</div>

<?php
function formBuilderFieldTypes(): array
{
    return [
        'short_text' => 'Respuesta corta',
        'long_text' => 'Parrafo',
        'single_choice' => 'Varias opciones',
        'multiple_choice' => 'Casillas',
        'dropdown' => 'Desplegable',
        'date' => 'Fecha',
        'time' => 'Hora',
        'email' => 'Correo electronico',
        'phone' => 'Telefono',
        'number' => 'Numero',
    ];
}

function formBuilderFieldTypeIcons(): array
{
    return [
        'short_text' => 'short_text',
        'long_text' => 'subject',
        'single_choice' => 'radio_button_checked',
        'multiple_choice' => 'check_box',
        'dropdown' => 'arrow_drop_down_circle',
        'date' => 'calendar_today',
        'time' => 'schedule',
        'email' => 'alternate_email',
        'phone' => 'call',
        'number' => 'pin',
    ];
}

function formBuilderEmptyForm(): array
{
    return [
        'id' => '',
        'title' => '',
        'description' => '',
        'slug' => '',
        'status' => 'draft',
        'public_id' => '',
        'response_count' => 0,
        'fields' => [],
        'settings' => [
            'submit_label' => 'Enviar respuesta',
            'thank_you_message' => 'Gracias, recibimos tu respuesta.',
        ],
    ];
}

function formBuilderNormalizeForm(array $form): array
{
    $fields = $form['fields'] ?? [];
    $settings = $form['settings'] ?? [];

    if (is_string($fields)) {
        $decoded = json_decode($fields, true);
        $fields = is_array($decoded) ? $decoded : [];
    }

    if (is_string($settings)) {
        $decoded = json_decode($settings, true);
        $settings = is_array($decoded) ? $decoded : [];
    }

    $normalized = formBuilderEmptyForm();

    return [
        ...$normalized,
        'id' => (string) ($form['id'] ?? ''),
        'title' => (string) ($form['title'] ?? ''),
        'description' => (string) ($form['description'] ?? ''),
        'slug' => (string) ($form['slug'] ?? ''),
        'status' => (string) ($form['status'] ?? 'draft'),
        'public_id' => (string) ($form['public_id'] ?? ''),
        'response_count' => (int) ($form['response_count'] ?? 0),
        'fields' => array_values(array_filter(array_map('formBuilderNormalizeField', is_array($fields) ? $fields : []))),
        'settings' => array_merge($normalized['settings'], is_array($settings) ? $settings : []),
    ];
}

function formBuilderNormalizeField(mixed $field): ?array
{
    if (!is_array($field)) {
        return null;
    }

    $type = trim((string) ($field['type'] ?? 'single_choice'));
    $options = $field['options'] ?? [];

    if (is_string($options)) {
        $decoded = json_decode($options, true);
        $options = is_array($decoded) ? $decoded : [];
    }

    return [
        'id' => trim((string) ($field['id'] ?? '')) ?: generateFormFieldId(),
        'type' => $type,
        'label' => trim((string) ($field['label'] ?? '')),
        'placeholder' => trim((string) ($field['placeholder'] ?? '')),
        'help_text' => trim((string) ($field['help_text'] ?? '')),
        'required' => filter_var($field['required'] ?? false, FILTER_VALIDATE_BOOL),
        'options' => array_values(array_filter(array_map(static function ($option): ?array {
            if (is_array($option)) {
                $label = trim((string) ($option['label'] ?? $option['value'] ?? ''));
            } else {
                $label = trim((string) $option);
            }

            if ($label === '') {
                return null;
            }

            return [
                'id' => 'option_' . substr(hash('sha1', $label), 0, 10),
                'label' => $label,
                'value' => $label,
            ];
        }, is_array($options) ? $options : []))),
    ];
}

function formBuilderCreateField(string $type, array $fieldTypes): array
{
    if (!array_key_exists($type, $fieldTypes)) {
        $type = 'single_choice';
    }

    return [
        'id' => generateFormFieldId(),
        'type' => $type,
        'label' => '',
        'placeholder' => '',
        'help_text' => '',
        'required' => false,
        'options' => in_array($type, ['single_choice', 'multiple_choice', 'dropdown'], true)
            ? [
                ['id' => 'option_' . bin2hex(random_bytes(4)), 'label' => 'Opcion 1', 'value' => 'Opcion 1'],
                ['id' => 'option_' . bin2hex(random_bytes(4)), 'label' => 'Opcion 2', 'value' => 'Opcion 2'],
            ]
            : [],
    ];
}

function formBuilderStateFromPost(array $post): array
{
    $settings = [
        'submit_label' => trim((string) ($post['submit_label'] ?? 'Enviar respuesta')) ?: 'Enviar respuesta',
        'thank_you_message' => trim((string) ($post['thank_you_message'] ?? 'Gracias, recibimos tu respuesta.')) ?: 'Gracias, recibimos tu respuesta.',
    ];

    return [
        'id' => trim((string) ($post['id'] ?? '')),
        'title' => trim((string) ($post['title'] ?? '')),
        'description' => trim((string) ($post['description'] ?? '')),
        'slug' => trim((string) ($post['slug'] ?? '')),
        'status' => trim((string) ($post['status'] ?? 'draft')) ?: 'draft',
        'public_id' => trim((string) ($post['public_id'] ?? '')),
        'fields' => formBuilderFieldsFromPost($post),
        'settings' => $settings,
    ];
}

function formBuilderFieldsFromPost(array $post): array
{
    $ids = is_array($post['field_id'] ?? null) ? $post['field_id'] : [];
    $types = is_array($post['field_type'] ?? null) ? $post['field_type'] : [];
    $labels = is_array($post['field_label'] ?? null) ? $post['field_label'] : [];
    $placeholders = is_array($post['field_placeholder'] ?? null) ? $post['field_placeholder'] : [];
    $helpTexts = is_array($post['field_help_text'] ?? null) ? $post['field_help_text'] : [];
    $options = is_array($post['field_options'] ?? null) ? $post['field_options'] : [];
    $required = is_array($post['field_required'] ?? null) ? $post['field_required'] : [];
    $fields = [];

    foreach ($ids as $index => $id) {
        $type = trim((string) ($types[$index] ?? 'single_choice'));
        $optionLines = preg_split('/\R+/', (string) ($options[$index] ?? '')) ?: [];

        $fields[] = [
            'id' => trim((string) $id) ?: generateFormFieldId(),
            'type' => $type,
            'label' => trim((string) ($labels[$index] ?? '')),
            'placeholder' => trim((string) ($placeholders[$index] ?? '')),
            'help_text' => trim((string) ($helpTexts[$index] ?? '')),
            'required' => isset($required[$index]) && (string) $required[$index] === '1',
            'options' => array_values(array_filter(array_map(static function (string $line): ?array {
                $label = trim($line);

                if ($label === '') {
                    return null;
                }

                return [
                    'id' => 'option_' . substr(hash('sha1', $label), 0, 10),
                    'label' => $label,
                    'value' => $label,
                ];
            }, $optionLines))),
        ];
    }

    return array_values(array_filter(array_map('formBuilderNormalizeField', $fields)));
}

function formBuilderMoveField(array $fields, int $index, int $direction): array
{
    $target = $index + $direction;

    if (!isset($fields[$index], $fields[$target])) {
        return $fields;
    }

    $current = $fields[$index];
    $fields[$index] = $fields[$target];
    $fields[$target] = $current;

    return array_values($fields);
}

function formBuilderPayloadForSave(array $form, string $projectId, string $userId, string $status): array
{
    $title = trim((string) ($form['title'] ?? ''));

    if ($title === '') {
        throw new InvalidArgumentException('El formulario necesita un titulo.');
    }

    $fields = array_values(array_filter(array_map('formBuilderNormalizeField', is_array($form['fields'] ?? null) ? $form['fields'] : [])));

    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }

        if (trim((string) ($field['label'] ?? '')) === '') {
            throw new InvalidArgumentException('Todos los campos necesitan una etiqueta visible.');
        }

        if (in_array((string) ($field['type'] ?? ''), ['single_choice', 'multiple_choice', 'dropdown'], true) && count((array) ($field['options'] ?? [])) < 2) {
            throw new InvalidArgumentException('Los campos de opcion multiple necesitan al menos dos opciones.');
        }
    }

    if ($status === 'published' && $fields === []) {
        throw new InvalidArgumentException('Agrega al menos un campo antes de publicar el formulario.');
    }

    $payload = [
        'project_id' => trim($projectId),
        'owner_user_id' => $userId,
        'title' => $title,
        'description' => trim((string) ($form['description'] ?? '')),
        'slug' => formBuilderResolveInternalSlug($form, $title),
        'status' => in_array($status, ['draft', 'published', 'archived'], true) ? $status : 'draft',
        'fields' => $fields,
        'settings' => is_array($form['settings'] ?? null) ? $form['settings'] : [],
        'published_at' => $status === 'published' ? gmdate('c') : null,
    ];

    $id = trim((string) ($form['id'] ?? ''));

    if ($id !== '') {
        $payload['id'] = $id;
    }

    return $payload;
}

function formBuilderResolveInternalSlug(array $form, string $title): string
{
    $existingSlug = trim((string) ($form['slug'] ?? ''));

    if ($existingSlug !== '') {
        return normalizeFormSlug($existingSlug);
    }

    $seed = trim((string) ($form['id'] ?? '')) ?: trim((string) ($form['public_id'] ?? '')) ?: bin2hex(random_bytes(4));

    return normalizeFormSlug($title) . '-' . substr(hash('sha1', $seed), 0, 8);
}

function formBuilderRenderList(array $forms, array $toolContext): string
{
    ob_start();
    ?>
    <section class="form-builder-card">
        <div class="form-builder-section-head">
            <div>
                <h2>Formularios creados</h2>
                <p>Administra los formularios que luego podras compartir con cualquier persona.</p>
            </div>
        </div>

        <?php if ($forms === []): ?>
            <div class="form-builder-empty">
                <span class="material-symbols-rounded">dynamic_form</span>
                <h3>Aun no tienes formularios</h3>
                <p>Crea el primero para empezar a recolectar respuestas sin pedir login.</p>
            </div>
        <?php else: ?>
            <div class="form-builder-table-wrap">
                <table class="form-builder-table">
                    <thead>
                        <tr>
                            <th>Titulo</th>
                            <th>Estado</th>
                            <th>Respuestas</th>
                            <th>Compartir</th>
                            <th>Herramientas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($forms as $form): ?>
                            <?php
                            $publicId = (string) ($form['public_id'] ?? '');
                            $shareUrl = $publicId !== '' ? formShareUrl($publicId) : '';
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars((string) ($form['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <small><?= htmlspecialchars((string) ($form['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
                                </td>
                                <td><span class="form-builder-status"><?= htmlspecialchars((string) ($form['status'] ?? 'draft'), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td><?= htmlspecialchars((string) ($form['response_count'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?php if ((string) ($form['status'] ?? '') === 'published' && $shareUrl !== ''): ?>
                                        <a href="<?= htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noreferrer noopener"><?= htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8'); ?></a>
                                    <?php else: ?>
                                        <span class="form-builder-muted">Publica para compartir</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="form-builder-actions">
                                        <a class="form-builder-secondary" href="tool.php?launch=<?= htmlspecialchars(rawurlencode((string) ($toolContext['launch_token'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>&builder=edit&id=<?= htmlspecialchars(rawurlencode((string) ($form['id'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">Editar</a>
                                        <form method="post" action="tool.php?launch=<?= htmlspecialchars(rawurlencode((string) ($toolContext['launch_token'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="form_action" value="delete_form">
                                            <input type="hidden" name="form_id" value="<?= htmlspecialchars((string) ($form['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="submit" class="form-builder-danger">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    <?php

    return (string) ob_get_clean();
}

function formBuilderRenderEditor(array $form, array $fieldTypes, array $toolContext, array $formInsights): string
{
    $launch = rawurlencode((string) ($toolContext['launch_token'] ?? ''));
    $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];
    $publicId = (string) ($form['public_id'] ?? '');
    $shareUrl = $publicId !== '' ? formShareUrl($publicId) : '';
    $formStatus = (string) ($form['status'] ?? 'draft');
    $apiUrl = 'tool-action.php?launch=' . $launch;
    $canLoadStats = trim((string) ($form['id'] ?? '')) !== '';
    $fieldTypeIcons = formBuilderFieldTypeIcons();

    ob_start();
    ?>
    <section class="form-builder-card form-builder-card--canvas">
        <form
            method="post"
            action="tool.php?launch=<?= htmlspecialchars($launch, ENT_QUOTES, 'UTF-8'); ?>&builder=edit"
            class="form-builder-editor"
            data-form-builder
            data-api-url="<?= htmlspecialchars($apiUrl, ENT_QUOTES, 'UTF-8'); ?>"
            data-form-id="<?= htmlspecialchars((string) ($form['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
            data-can-load-stats="<?= $canLoadStats ? 'true' : 'false'; ?>"
        >
            <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($form['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="status" value="<?= htmlspecialchars($formStatus, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="public_id" value="<?= htmlspecialchars($publicId, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="new_field_type" value="single_choice">

            <div class="form-google-toolbar">
                <div class="form-google-doc">
                    <span class="material-symbols-rounded">dynamic_form</span>
                    <div>
                        <strong data-form-doc-title><?= htmlspecialchars(trim((string) ($form['title'] ?? '')) ?: 'Formulario sin titulo', ENT_QUOTES, 'UTF-8'); ?></strong>
                        <small><?= $formStatus === 'published' ? 'Publicado' : 'Borrador'; ?></small>
                    </div>
                </div>

                <div class="form-google-actions">
                    <?php if ($formStatus === 'published' && $shareUrl !== ''): ?>
                        <a href="<?= htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noreferrer noopener" class="form-google-icon-action" aria-label="Vista publica">
                            <span class="material-symbols-rounded">visibility</span>
                        </a>
                    <?php endif; ?>
                    <button type="submit" name="builder_action" value="save" class="form-builder-secondary">
                        <span class="material-symbols-rounded">save</span>
                        <span>Guardar</span>
                    </button>
                    <button type="submit" name="builder_action" value="publish" class="form-builder-primary">Publicar</button>
                </div>
            </div>

            <nav class="form-google-tabs" aria-label="Secciones del constructor">
                <button type="button" class="is-active" data-form-tab="questions">Preguntas</button>
                <button type="button" data-form-tab="responses">Respuestas</button>
                <button type="button" data-form-tab="settings">Configuracion</button>
            </nav>

            <div class="form-google-stage">
                <div class="form-google-canvas">
                    <div class="form-google-panel is-active" data-form-panel="questions">
                        <section class="form-google-cover">
                            <div class="form-google-cover-bar"></div>
                            <label>
                                <span class="sr-only">Titulo del formulario</span>
                                <input type="text" name="title" value="<?= htmlspecialchars((string) ($form['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Formulario sin titulo" data-form-title-input>
                            </label>
                            <label>
                                <span class="sr-only">Descripcion del formulario</span>
                                <textarea name="description" rows="2" placeholder="Descripcion del formulario"><?= htmlspecialchars((string) ($form['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </label>
                        </section>

                        <?php if ($formStatus === 'published' && $shareUrl !== ''): ?>
                            <div class="form-builder-share">
                                <span class="material-symbols-rounded">ios_share</span>
                                <div>
                                    <strong>Formulario publico</strong>
                                    <a href="<?= htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noreferrer noopener"><?= htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8'); ?></a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="form-builder-fields" data-form-fields>
                            <?php if (($form['fields'] ?? []) === []): ?>
                                <div class="form-builder-empty form-builder-empty--compact" data-form-empty>
                                    <span class="material-symbols-rounded">add_notes</span>
                                    <h3>Agrega tu primera pregunta</h3>
                                    <p>Usa el boton + de la barra lateral para construir el formulario sin recargar.</p>
                                </div>
                            <?php endif; ?>

                            <?php foreach ((array) ($form['fields'] ?? []) as $index => $field): ?>
                                <?= formBuilderRenderFieldEditor(is_array($field) ? $field : [], $index, $fieldTypes); ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-google-panel" data-form-panel="responses">
                        <div class="form-builder-responses-shell" data-form-responses-shell>
                            <?= formBuilderRenderResponsesPanel($formInsights, $canLoadStats); ?>
                        </div>
                    </div>

                    <div class="form-google-panel" data-form-panel="settings">
                        <section class="form-google-settings">
                            <label class="form-builder-field">
                                <span>Texto del boton</span>
                                <input type="text" name="submit_label" value="<?= htmlspecialchars((string) ($settings['submit_label'] ?? 'Enviar respuesta'), ENT_QUOTES, 'UTF-8'); ?>">
                            </label>

                            <label class="form-builder-field">
                                <span>Mensaje al enviar</span>
                                <input type="text" name="thank_you_message" value="<?= htmlspecialchars((string) ($settings['thank_you_message'] ?? 'Gracias, recibimos tu respuesta.'), ENT_QUOTES, 'UTF-8'); ?>">
                            </label>
                        </section>
                    </div>
                </div>

                <aside class="form-google-side-tools" data-form-side-tools aria-label="Herramientas del formulario">
                    <button type="submit" name="builder_action" value="add_field" class="form-google-tool-button" data-form-add-field title="Agregar pregunta" aria-label="Agregar pregunta">
                        <span class="material-symbols-rounded">add_circle</span>
                    </button>
                    <button type="button" class="form-google-tool-button" data-form-duplicate-active title="Duplicar pregunta activa" aria-label="Duplicar pregunta activa">
                        <span class="material-symbols-rounded">content_copy</span>
                    </button>
                    <button type="button" class="form-google-tool-button" data-form-focus-settings title="Configuracion" aria-label="Configuracion">
                        <span class="material-symbols-rounded">settings</span>
                    </button>
                </aside>
            </div>

            <div class="form-builder-footer-actions">
                <a href="tool.php?launch=<?= htmlspecialchars($launch, ENT_QUOTES, 'UTF-8'); ?>" class="form-builder-secondary">Volver a formularios</a>
                <button type="submit" name="builder_action" value="save" class="form-builder-secondary">Guardar borrador</button>
                <button type="submit" name="builder_action" value="publish" class="form-builder-primary">Publicar</button>
            </div>
        </form>
        <script type="application/json" data-form-responses-state><?= formBuilderJsonEncode($formInsights); ?></script>
        <script type="application/json" data-form-field-types><?= formBuilderJsonEncode(formBuilderFieldTypeDefinitions($fieldTypes)); ?></script>
    </section>
    <script src="tool-asset.php?launch=<?= htmlspecialchars($launch, ENT_QUOTES, 'UTF-8'); ?>&asset=app.js"></script>
    <?php

    return (string) ob_get_clean();
}

function formBuilderRenderFieldEditor(array $field, int $index, array $fieldTypes): string
{
    $type = (string) ($field['type'] ?? 'short_text');
    $options = array_map(static fn (array $option): string => (string) ($option['label'] ?? $option['value'] ?? ''), (array) ($field['options'] ?? []));

    ob_start();
    ?>
    <article class="form-builder-field-card" data-form-field-card>
        <div class="form-builder-field-card-head">
            <span class="form-builder-drag-handle material-symbols-rounded" aria-hidden="true">drag_indicator</span>
            <strong data-form-field-title>Pregunta <?= $index + 1; ?></strong>
            <div class="form-builder-field-tools">
                <button type="submit" name="builder_action" value="up:<?= $index; ?>" class="form-builder-icon-button" aria-label="Subir campo" data-form-field-move="up">
                    <span class="material-symbols-rounded">arrow_upward</span>
                </button>
                <button type="submit" name="builder_action" value="down:<?= $index; ?>" class="form-builder-icon-button" aria-label="Bajar campo" data-form-field-move="down">
                    <span class="material-symbols-rounded">arrow_downward</span>
                </button>
                <button type="button" class="form-builder-icon-button" aria-label="Duplicar campo" data-form-field-duplicate>
                    <span class="material-symbols-rounded">content_copy</span>
                </button>
                <button type="submit" name="builder_action" value="delete:<?= $index; ?>" class="form-builder-icon-button form-builder-icon-button--danger" aria-label="Eliminar campo" data-form-field-delete>
                    <span class="material-symbols-rounded">delete</span>
                </button>
            </div>
        </div>

        <input type="hidden" name="field_id[<?= $index; ?>]" value="<?= htmlspecialchars((string) ($field['id'] ?? generateFormFieldId()), ENT_QUOTES, 'UTF-8'); ?>" data-form-field-input="id">

        <div class="form-builder-editor-grid">
            <?= formBuilderRenderTypePicker($type, $index, $fieldTypes); ?>

            <label class="form-builder-field form-builder-question-label">
                <span class="sr-only">Pregunta</span>
                <input type="text" name="field_label[<?= $index; ?>]" value="<?= htmlspecialchars((string) ($field['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Pregunta" data-form-field-input="label">
            </label>

            <?= formBuilderRenderOptionsEditor($type, $options, $index); ?>
        </div>

        <div class="form-builder-question-preview" data-form-question-preview>
            <?= formBuilderRenderQuestionPreview($type, (string) ($field['label'] ?? ''), $options); ?>
        </div>

        <div class="form-builder-card-footer">
            <label class="form-builder-check form-builder-check--switch">
                <span>Obligatorio</span>
                <input type="checkbox" name="field_required[<?= $index; ?>]" value="1"<?= !empty($field['required']) ? ' checked' : ''; ?> data-form-field-input="required">
            </label>
        </div>
    </article>
    <?php

    return (string) ob_get_clean();
}

function formBuilderRenderTypePicker(string $type, int $index, array $fieldTypes): string
{
    $icons = formBuilderFieldTypeIcons();
    $safeType = array_key_exists($type, $fieldTypes) ? $type : 'single_choice';

    ob_start();
    ?>
    <div class="form-builder-field form-builder-type-field">
        <span class="sr-only">Tipo</span>
        <details class="form-builder-type-picker" data-form-type-picker>
            <summary class="form-builder-type-summary">
                <span class="material-symbols-rounded" data-form-type-icon><?= htmlspecialchars((string) ($icons[$safeType] ?? 'short_text'), ENT_QUOTES, 'UTF-8'); ?></span>
                <span data-form-type-label><?= htmlspecialchars((string) ($fieldTypes[$safeType] ?? 'Varias opciones'), ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="material-symbols-rounded form-builder-type-chevron">expand_more</span>
            </summary>

            <div class="form-builder-type-menu">
                <?php foreach ($fieldTypes as $value => $label): ?>
                    <button
                        type="button"
                        class="form-builder-type-option<?= $value === $safeType ? ' is-active' : ''; ?>"
                        data-form-type-option
                        data-value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"
                        data-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>"
                        data-icon="<?= htmlspecialchars((string) ($icons[$value] ?? 'short_text'), ENT_QUOTES, 'UTF-8'); ?>"
                    >
                        <span class="material-symbols-rounded"><?= htmlspecialchars((string) ($icons[$value] ?? 'short_text'), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </details>
        <input type="hidden" name="field_type[<?= $index; ?>]" value="<?= htmlspecialchars($safeType, ENT_QUOTES, 'UTF-8'); ?>" data-form-field-input="type">
    </div>
    <?php

    return (string) ob_get_clean();
}

function formBuilderRenderOptionsEditor(string $type, array $options, int $index): string
{
    $normalizedOptions = $options !== [] ? $options : ['Opcion 1', 'Opcion 2'];

    ob_start();
    ?>
    <label class="form-builder-field form-builder-field--wide form-builder-options-field" data-form-options-wrap>
        <span class="sr-only">Opciones</span>
        <div class="form-builder-option-list" data-form-option-items>
            <?php foreach ($normalizedOptions as $optionIndex => $optionLabel): ?>
                <?= formBuilderRenderOptionRow($type, $optionIndex, (string) $optionLabel); ?>
            <?php endforeach; ?>
        </div>
        <button type="button" class="form-builder-option-add" data-form-option-add>
            <span class="material-symbols-rounded">add</span>
            <span>Anadir opcion</span>
        </button>
        <textarea name="field_options[<?= $index; ?>]" rows="4" class="form-builder-options-storage" data-form-field-input="options"><?= htmlspecialchars(implode("\n", $normalizedOptions), ENT_QUOTES, 'UTF-8'); ?></textarea>
    </label>
    <?php

    return (string) ob_get_clean();
}

function formBuilderRenderOptionRow(string $type, int $index, string $value): string
{
    $decoratorIcon = match ($type) {
        'multiple_choice' => 'check_box_outline_blank',
        'dropdown' => 'arrow_drop_down',
        default => 'radio_button_unchecked',
    };

    ob_start();
    ?>
    <div class="form-builder-option-row" data-form-option-row>
        <span class="material-symbols-rounded form-builder-option-decorator" data-form-option-icon><?= htmlspecialchars($decoratorIcon, ENT_QUOTES, 'UTF-8'); ?></span>
        <input type="text" value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Opcion <?= $index + 1; ?>" data-form-option-input>
        <button type="button" class="form-builder-option-remove" data-form-option-remove aria-label="Eliminar opcion">
            <span class="material-symbols-rounded">close</span>
        </button>
    </div>
    <?php

    return (string) ob_get_clean();
}

function formBuilderRenderQuestionPreview(string $type, string $label, array $options): string
{
    $title = trim($label) !== '' ? $label : 'Pregunta';

    ob_start();
    ?>
    <div class="form-builder-preview-shell" data-form-preview-shell>
        <div class="form-builder-preview-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="form-builder-preview-body" data-form-preview-body>
            <?= formBuilderRenderQuestionPreviewBody($type, $options); ?>
        </div>
    </div>
    <?php

    return (string) ob_get_clean();
}

function formBuilderRenderQuestionPreviewBody(string $type, array $options): string
{
    $normalizedOptions = $options !== [] ? $options : ['Opcion 1', 'Opcion 2'];
    $decoratorIcon = match ($type) {
        'multiple_choice' => 'check_box_outline_blank',
        'dropdown' => 'arrow_drop_down',
        default => 'radio_button_unchecked',
    };

    ob_start();

    if (in_array($type, ['single_choice', 'multiple_choice'], true)) {
        ?>
        <div class="form-builder-preview-options">
            <?php foreach ($normalizedOptions as $optionLabel): ?>
                <div class="form-builder-preview-option">
                    <span class="material-symbols-rounded"><?= htmlspecialchars($decoratorIcon, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span><?= htmlspecialchars((string) $optionLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    if ($type === 'dropdown') {
        ?>
        <div class="form-builder-preview-input form-builder-preview-input--dropdown">
            <span>Selecciona una opcion</span>
            <span class="material-symbols-rounded">expand_more</span>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    if ($type === 'long_text') {
        ?>
        <div class="form-builder-preview-input form-builder-preview-input--textarea"></div>
        <?php

        return (string) ob_get_clean();
    }

    ?>
    <div class="form-builder-preview-input"></div>
    <?php

    return (string) ob_get_clean();
}

function formBuilderFieldTypeDefinitions(array $fieldTypes): array
{
    $icons = formBuilderFieldTypeIcons();

    return array_values(array_map(static function (string $value) use ($fieldTypes, $icons): array {
        return [
            'value' => $value,
            'label' => (string) ($fieldTypes[$value] ?? $value),
            'icon' => (string) ($icons[$value] ?? 'short_text'),
        ];
    }, array_keys($fieldTypes)));
}

function formBuilderRenderResponsesPanel(array $summary, bool $canLoadStats): string
{
    $completedCount = (int) ($summary['completed_count'] ?? 0);
    $visitsCount = (int) ($summary['visits_count'] ?? 0);
    $startedCount = (int) ($summary['started_count'] ?? 0);
    $finishedCount = (int) ($summary['completed_sessions_count'] ?? max($completedCount, 0));
    $funnel = is_array($summary['funnel'] ?? null) ? $summary['funnel'] : [];

    ob_start();
    ?>
    <div class="form-builder-responses">
        <?php if (!$canLoadStats): ?>
            <section class="form-google-empty-panel">
                <span class="material-symbols-rounded">query_stats</span>
                <h2>Guarda el formulario para ver resultados</h2>
                <p>En cuanto exista el formulario, aqui veras respuestas completas, visitas y abandono en tiempo real.</p>
            </section>
        <?php else: ?>
            <section class="form-builder-responses-kpis">
                <article class="form-builder-response-card form-builder-response-card--primary">
                    <span class="form-builder-response-label">Respuestas completas</span>
                    <strong><?= htmlspecialchars((string) $completedCount, ENT_QUOTES, 'UTF-8'); ?></strong>
                    <small>Solo contamos formularios terminados.</small>
                </article>

                <article class="form-builder-response-card">
                    <span class="form-builder-response-label">Visitas</span>
                    <strong><?= htmlspecialchars((string) $visitsCount, ENT_QUOTES, 'UTF-8'); ?></strong>
                    <small>Se actualiza automaticamente con cada nueva entrada.</small>
                </article>
            </section>

            <?php if (($summary['choice_questions'] ?? []) !== []): ?>
                <section class="form-builder-analytics-section">
                    <div class="form-builder-analytics-head">
                        <h2>Preguntas cerradas</h2>
                        <p>Cada grafica usa solo respuestas completas.</p>
                    </div>

                    <div class="form-builder-charts-grid">
                        <?php foreach ((array) ($summary['choice_questions'] ?? []) as $question): ?>
                            <?= formBuilderRenderChoiceQuestionCard(is_array($question) ? $question : []); ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (($summary['open_questions'] ?? []) !== []): ?>
                <section class="form-builder-analytics-section">
                    <div class="form-builder-analytics-head">
                        <h2>Preguntas abiertas</h2>
                        <p>Tabla viva con respuestas completas recibidas.</p>
                    </div>

                    <div class="form-builder-open-question-list">
                        <?php foreach ((array) ($summary['open_questions'] ?? []) as $question): ?>
                            <?= formBuilderRenderOpenQuestionTable(is_array($question) ? $question : []); ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="form-builder-analytics-section">
                <div class="form-builder-analytics-head">
                    <h2>Embudo del formulario</h2>
                </div>

                <div class="form-builder-funnel-layout">
                    <div class="form-builder-funnel-visual">
                        <?= formBuilderRenderLiteralFunnel($funnel); ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </div>
    <?php

    return (string) ob_get_clean();
}

function formBuilderChartPalette(): array
{
    return ['#3367d6', '#d93025', '#f29900', '#1e8e3e', '#9334e6', '#0099c6'];
}

function formBuilderDescribePieSlice(float $centerX, float $centerY, float $radius, float $startAngle, float $endAngle): string
{
    $startX = $centerX + ($radius * cos($startAngle));
    $startY = $centerY + ($radius * sin($startAngle));
    $endX = $centerX + ($radius * cos($endAngle));
    $endY = $centerY + ($radius * sin($endAngle));
    $largeArcFlag = ($endAngle - $startAngle) > M_PI ? 1 : 0;

    return sprintf(
        'M %.3F %.3F L %.3F %.3F A %.3F %.3F 0 %d 1 %.3F %.3F Z',
        $centerX,
        $centerY,
        $startX,
        $startY,
        $radius,
        $radius,
        $largeArcFlag,
        $endX,
        $endY
    );
}

function formBuilderRenderChoicePieSvg(array $options): string
{
    $palette = formBuilderChartPalette();
    $centerX = 140.0;
    $centerY = 140.0;
    $radius = 112.0;
    $angle = -M_PI / 2;
    $hasVisibleData = false;

    ob_start();
    ?>
    <svg class="form-builder-pie-svg" viewBox="0 0 280 280" role="img" aria-hidden="true">
        <circle cx="140" cy="140" r="112" fill="#eef1f5"></circle>
        <?php foreach ($options as $index => $option): ?>
            <?php
            $percent = max(0.0, min(100.0, (float) ($option['percent'] ?? 0)));
            $sweep = (2 * M_PI * $percent) / 100;
            $sliceStart = $angle;
            $sliceEnd = $angle + $sweep;
            $angle = $sliceEnd;

            if ($percent <= 0.01) {
                continue;
            }

            $hasVisibleData = true;
            $midAngle = $sliceStart + ($sweep / 2);
            $labelRadius = $radius * 0.7;
            $labelX = $centerX + ($labelRadius * cos($midAngle));
            $labelY = $centerY + ($labelRadius * sin($midAngle));
            ?>
            <path d="<?= htmlspecialchars(formBuilderDescribePieSlice($centerX, $centerY, $radius, $sliceStart, $sliceEnd), ENT_QUOTES, 'UTF-8'); ?>" fill="<?= htmlspecialchars($palette[$index % count($palette)], ENT_QUOTES, 'UTF-8'); ?>" stroke="#ffffff" stroke-width="2"></path>
            <?php if ($percent >= 6): ?>
                <text x="<?= htmlspecialchars(number_format($labelX, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>" y="<?= htmlspecialchars(number_format($labelY, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>" text-anchor="middle" dominant-baseline="middle" class="form-builder-pie-svg-label"><?= htmlspecialchars(number_format($percent, 0), ENT_QUOTES, 'UTF-8'); ?>%</text>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if (!$hasVisibleData): ?>
            <text x="140" y="146" text-anchor="middle" class="form-builder-pie-svg-empty">Sin datos</text>
        <?php endif; ?>
    </svg>
    <?php

    return (string) ob_get_clean();
}

function formBuilderRenderChoiceQuestionCard(array $question): string
{
    $options = is_array($question['options'] ?? null) ? $question['options'] : [];
    $payload = [
        'label' => (string) ($question['label'] ?? 'Pregunta'),
        'answer_count' => (int) ($question['answer_count'] ?? 0),
        'options' => array_map(static function ($option): array {
            return [
                'label' => (string) ($option['label'] ?? ''),
                'count' => (int) ($option['count'] ?? 0),
                'percent' => (float) ($option['percent'] ?? 0),
            ];
        }, $options),
    ];

    ob_start();
    ?>
    <article class="form-builder-chart-card form-builder-chart-card--choice">
        <div class="form-builder-chart-head">
            <div class="form-builder-chart-head-main">
                <h3><?= htmlspecialchars((string) ($question['label'] ?? 'Pregunta'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <p><?= htmlspecialchars((string) ((int) ($question['answer_count'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?> respuestas</p>
            </div>

            <button type="button" class="form-builder-chart-copy" data-form-copy-chart data-chart-payload="<?= htmlspecialchars((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-symbols-rounded">content_copy</span>
                <span>Copiar grafico</span>
            </button>
        </div>

        <div class="form-builder-chart-body form-builder-chart-body--choice">
            <div class="form-builder-chart-visual">
                <?= formBuilderRenderChoicePieSvg($options); ?>
            </div>

            <div class="form-builder-chart-legend">
                <?php foreach ($options as $index => $option): ?>
                    <div class="form-builder-chart-legend-item">
                        <span class="form-builder-chart-swatch" style="background: <?= htmlspecialchars(formBuilderChartPalette()[$index % count(formBuilderChartPalette())], ENT_QUOTES, 'UTF-8'); ?>;"></span>
                        <div>
                            <strong><?= htmlspecialchars((string) ($option['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                            <small><?= htmlspecialchars((string) ((int) ($option['count'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?> respuestas • <?= htmlspecialchars(number_format((float) ($option['percent'] ?? 0), 0), ENT_QUOTES, 'UTF-8'); ?>%</small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </article>
    <?php

    return (string) ob_get_clean();
}

function formBuilderRenderOpenQuestionTable(array $question): string
{
    $responses = is_array($question['responses'] ?? null) ? $question['responses'] : [];

    ob_start();
    ?>
    <article class="form-builder-open-card">
        <div class="form-builder-chart-head">
            <h3><?= htmlspecialchars((string) ($question['label'] ?? 'Pregunta abierta'), ENT_QUOTES, 'UTF-8'); ?></h3>
            <p><?= htmlspecialchars((string) count($responses), ENT_QUOTES, 'UTF-8'); ?> respuestas completas</p>
        </div>

        <?php if ($responses === []): ?>
            <div class="form-builder-open-empty">
                <span class="material-symbols-rounded">notes</span>
                <p>Aun no hay respuestas completas para esta pregunta.</p>
            </div>
        <?php else: ?>
            <div class="form-builder-open-table-wrap">
                <table class="form-builder-open-table">
                    <thead>
                        <tr>
                            <th>Respuesta</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($responses as $response): ?>
                            <tr>
                                <td><?= nl2br(htmlspecialchars((string) ($response['value'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></td>
                                <td><?= htmlspecialchars(formBuilderFormatDateTime((string) ($response['submitted_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>
    <?php

    return (string) ob_get_clean();
}

function formBuilderFormatDateTime(string $value): string
{
    if (trim($value) === '') {
        return 'Sin fecha';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date('d/m/Y H:i', $timestamp);
}

function formBuilderRenderLiteralFunnel(array $funnel): string
{
    $arrived = max(0, (int) ($funnel['arrived'] ?? 0));
    $started = min($arrived, max(0, (int) ($funnel['started'] ?? 0)));
    $completed = min($started, max(0, (int) ($funnel['completed'] ?? 0)));
    $base = max($arrived, 1);
    $stages = [
        [
            'label' => 'Llegaron',
            'count' => $arrived,
            'width' => 100,
            'percent' => 100,
        ],
        [
            'label' => 'Empezaron',
            'count' => $started,
            'width' => max(52, (int) round(78 * max(0, $started / $base))),
            'percent' => (int) round(($started / $base) * 100),
        ],
        [
            'label' => 'Terminaron',
            'count' => $completed,
            'width' => max(34, (int) round(60 * max(0, $completed / $base))),
            'percent' => (int) round(($completed / $base) * 100),
        ],
    ];

    ob_start();
    ?>
    <div class="form-builder-funnel" aria-label="Embudo del formulario">
        <?php foreach ($stages as $index => $stage): ?>
            <article class="form-builder-funnel-stage form-builder-funnel-stage--<?= $index + 1; ?>" style="--funnel-stage-width: <?= htmlspecialchars((string) $stage['width'], ENT_QUOTES, 'UTF-8'); ?>%;">
                <span class="form-builder-funnel-stage-label"><?= htmlspecialchars((string) $stage['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                <strong><?= htmlspecialchars((string) $stage['count'], ENT_QUOTES, 'UTF-8'); ?></strong>
                <small><?= htmlspecialchars((string) $stage['percent'], ENT_QUOTES, 'UTF-8'); ?>% del total</small>
            </article>
        <?php endforeach; ?>
    </div>
    <?php

    return (string) ob_get_clean();
}
