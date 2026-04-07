<?php
declare(strict_types=1);

use AiScaler\Forms\FormRepository;

require_once __DIR__ . '/../../modules/forms/bootstrap.php';

$toolContext = is_array($toolRuntimeContext ?? null) ? $toolRuntimeContext : [];
$accessToken = trim((string) ($toolContext['access_token'] ?? ''));
$userId = trim((string) ($toolContext['user_id'] ?? ''));
$userEmail = trim((string) ($toolContext['user_email'] ?? ''));
$repository = new FormRepository();
$fieldTypes = formBuilderFieldTypes();
$notice = null;
$error = null;
$business = null;
$forms = [];
$mode = trim((string) ($_GET['builder'] ?? 'list'));
$currentForm = null;

try {
    if ($accessToken === '' || $userId === '') {
        throw new RuntimeException('No encontramos la sesion segura para guardar formularios. Vuelve a abrir la herramienta desde el panel.');
    }

    $business = $repository->ensureDefaultBusiness($accessToken, $userId, $userEmail);
    $businessId = (string) ($business['id'] ?? '');

    if ($businessId === '') {
        throw new RuntimeException('No fue posible resolver la cuenta de empresa.');
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $postAction = trim((string) ($_POST['form_action'] ?? ''));

        if ($postAction === 'delete_form') {
            $formId = trim((string) ($_POST['form_id'] ?? ''));

            if ($formId === '') {
                throw new InvalidArgumentException('No encontramos el formulario que intentas eliminar.');
            }

            $repository->softDeleteForm($accessToken, $formId);
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
                $payload = formBuilderPayloadForSave($currentForm, $businessId, $userId, $status);
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
            $loadedForm = $repository->findForm($accessToken, $formId);

            if (!is_array($loadedForm)) {
                throw new RuntimeException('No encontramos el formulario solicitado.');
            }

            $currentForm = formBuilderNormalizeForm($loadedForm);
        }
    }

    $forms = $repository->listForms($accessToken, $businessId);
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
                <h1>Generador de formularios</h1>
                <p>Crea formularios publicos, compartelos sin login y guarda cada respuesta como un JSON conectado a la empresa y al usuario creador.</p>
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

    <?php if (is_array($business) && !$isEditorMode): ?>
        <div class="form-builder-business-chip">
            <span class="material-symbols-rounded">domain</span>
            <span>Cuenta de empresa: <strong><?= htmlspecialchars((string) ($business['name'] ?? 'Mi empresa'), ENT_QUOTES, 'UTF-8'); ?></strong></span>
        </div>
    <?php endif; ?>

    <?php if ($isEditorMode): ?>
        <?= formBuilderRenderEditor($currentForm, $fieldTypes, $toolContext); ?>
    <?php else: ?>
        <?= formBuilderRenderList($forms, $toolContext); ?>
    <?php endif; ?>
</div>

<?php
function formBuilderFieldTypes(): array
{
    return [
        'short_text' => 'Texto corto',
        'long_text' => 'Texto largo',
        'email' => 'Correo electronico',
        'phone' => 'Telefono',
        'number' => 'Numero',
        'date' => 'Fecha',
        'single_choice' => 'Opcion multiple: una respuesta',
        'multiple_choice' => 'Opcion multiple: varias respuestas',
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

    $type = trim((string) ($field['type'] ?? 'short_text'));
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
        $type = 'short_text';
    }

    return [
        'id' => generateFormFieldId(),
        'type' => $type,
        'label' => $fieldTypes[$type],
        'placeholder' => '',
        'help_text' => '',
        'required' => false,
        'options' => in_array($type, ['single_choice', 'multiple_choice'], true)
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
        'slug' => normalizeFormSlug((string) ($post['slug'] ?? $post['title'] ?? 'formulario')),
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
        $type = trim((string) ($types[$index] ?? 'short_text'));
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

function formBuilderPayloadForSave(array $form, string $businessId, string $userId, string $status): array
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

        if (in_array((string) ($field['type'] ?? ''), ['single_choice', 'multiple_choice'], true) && count((array) ($field['options'] ?? [])) < 2) {
            throw new InvalidArgumentException('Los campos de opcion multiple necesitan al menos dos opciones.');
        }
    }

    if ($status === 'published' && $fields === []) {
        throw new InvalidArgumentException('Agrega al menos un campo antes de publicar el formulario.');
    }

    $payload = [
        'business_id' => $businessId,
        'owner_user_id' => $userId,
        'title' => $title,
        'description' => trim((string) ($form['description'] ?? '')),
        'slug' => normalizeFormSlug((string) ($form['slug'] ?? $title)),
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

function formBuilderRenderEditor(array $form, array $fieldTypes, array $toolContext): string
{
    $launch = rawurlencode((string) ($toolContext['launch_token'] ?? ''));
    $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];
    $publicId = (string) ($form['public_id'] ?? '');
    $shareUrl = $publicId !== '' ? formShareUrl($publicId) : '';
    $formStatus = (string) ($form['status'] ?? 'draft');

    ob_start();
    ?>
    <section class="form-builder-card form-builder-card--canvas">
        <form method="post" action="tool.php?launch=<?= htmlspecialchars($launch, ENT_QUOTES, 'UTF-8'); ?>&builder=edit" class="form-builder-editor" data-form-builder>
            <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($form['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="status" value="<?= htmlspecialchars($formStatus, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="public_id" value="<?= htmlspecialchars($publicId, ENT_QUOTES, 'UTF-8'); ?>">

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
                        <section class="form-google-empty-panel">
                            <span class="material-symbols-rounded">query_stats</span>
                            <h2><?= htmlspecialchars((string) ($form['response_count'] ?? 0), ENT_QUOTES, 'UTF-8'); ?> respuestas</h2>
                            <p>Las respuestas ya se guardan en Supabase como un registro por envio. En una siguiente iteracion podemos agregar aqui tabla, filtros y exportacion.</p>
                        </section>
                    </div>

                    <div class="form-google-panel" data-form-panel="settings">
                        <section class="form-google-settings">
                            <label class="form-builder-field">
                                <span>Slug interno</span>
                                <input type="text" name="slug" value="<?= htmlspecialchars((string) ($form['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="diagnostico-inicial">
                            </label>

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

                <aside class="form-google-side-tools" aria-label="Herramientas del formulario">
                    <select name="new_field_type" aria-label="Tipo de campo nuevo" data-form-new-field-type>
                        <?php foreach ($fieldTypes as $type => $label): ?>
                            <option value="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
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
            <label class="form-builder-field form-builder-type-field">
                <span>Tipo</span>
                <select name="field_type[<?= $index; ?>]" data-form-field-input="type">
                    <?php foreach ($fieldTypes as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"<?= $value === $type ? ' selected' : ''; ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="form-builder-field form-builder-question-label">
                <span>Etiqueta</span>
                <input type="text" name="field_label[<?= $index; ?>]" value="<?= htmlspecialchars((string) ($field['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nombre completo" data-form-field-input="label">
            </label>

            <label class="form-builder-field form-builder-field--wide">
                <span>Ayuda del campo</span>
                <input type="text" name="field_help_text[<?= $index; ?>]" value="<?= htmlspecialchars((string) ($field['help_text'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Opcional" data-form-field-input="help_text">
            </label>

            <label class="form-builder-field form-builder-field--wide">
                <span>Placeholder</span>
                <input type="text" name="field_placeholder[<?= $index; ?>]" value="<?= htmlspecialchars((string) ($field['placeholder'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Texto de ayuda dentro del campo" data-form-field-input="placeholder">
            </label>

            <label class="form-builder-field form-builder-field--wide form-builder-options-field" data-form-options-wrap>
                <span>Opciones, una por linea</span>
                <textarea name="field_options[<?= $index; ?>]" rows="4" placeholder="Opcion 1&#10;Opcion 2" data-form-field-input="options"><?= htmlspecialchars(implode("\n", $options), ENT_QUOTES, 'UTF-8'); ?></textarea>
                <small>Solo se usan para campos de opcion multiple.</small>
            </label>
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
