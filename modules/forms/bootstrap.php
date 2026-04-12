<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/app_routing.php';
require_once __DIR__ . '/../../lib/supabase_api.php';
require_once __DIR__ . '/FormRepository.php';

function normalizeFormBuilderException(Throwable $exception): string
{
    $message = $exception->getMessage();
    $normalized = strtolower($message);

    if (
        str_contains($normalized, 'could not find the table')
        || str_contains($normalized, 'pgrst205')
        || str_contains($normalized, 'schema cache')
        || str_contains($normalized, 'does not exist')
    ) {
        return 'La estructura de formularios aun no existe. Ejecuta supabase/forms_schema.sql en Supabase.';
    }

    if (str_contains($normalized, 'row-level security')) {
        return 'Supabase bloqueo la operacion por permisos. Revisa las politicas de supabase/forms_schema.sql.';
    }

    if (str_contains($normalized, 'duplicate key') || str_contains($normalized, 'forms_project_slug_unique')) {
        return 'Ya existe un formulario con un identificador interno similar. Guarda nuevamente o cambia ligeramente el titulo.';
    }

    if (
        str_contains($normalized, 'get_public_form_definition')
        || str_contains($normalized, 'submit_public_form_response')
        || str_contains($normalized, 'track_public_form_session')
    ) {
        return 'Faltan las funciones publicas de formularios. Ejecuta supabase/forms_schema.sql en Supabase.';
    }

    return $message !== '' ? $message : 'Ocurrio un error inesperado en formularios.';
}

function normalizeFormSlug(string $value): string
{
    $normalized = strtolower(trim($value));
    $normalized = preg_replace('/[^a-z0-9]+/i', '-', $normalized) ?? '';
    $normalized = trim($normalized, '-');

    return $normalized !== '' ? $normalized : 'formulario';
}

function generateFormFieldId(): string
{
    return 'field_' . bin2hex(random_bytes(5));
}

function formShareUrl(string $publicId): string
{
    return appPublicFormUrl($publicId);
}

function formBuilderJsonEncode(array $payload): string
{
    return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
}

function formBuilderNormalizeStatsField(mixed $field): ?array
{
    if (!is_array($field)) {
        return null;
    }

    $options = $field['options'] ?? [];

    if (is_string($options)) {
        $decoded = json_decode($options, true);
        $options = is_array($decoded) ? $decoded : [];
    }

    return [
        'id' => trim((string) ($field['id'] ?? '')),
        'type' => trim((string) ($field['type'] ?? 'short_text')),
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
                'label' => $label,
                'value' => $label,
            ];
        }, is_array($options) ? $options : []))),
    ];
}

function formBuilderNormalizedStoredFields(array $form): array
{
    $fields = $form['fields'] ?? [];

    if (is_string($fields)) {
        $decoded = json_decode($fields, true);
        $fields = is_array($decoded) ? $decoded : [];
    }

    return array_values(array_filter(array_map('formBuilderNormalizeStatsField', is_array($fields) ? $fields : [])));
}

function formBuilderEmptyInsightsSummary(): array
{
    return [
        'completed_count' => 0,
        'visits_count' => 0,
        'started_count' => 0,
        'completed_sessions_count' => 0,
        'abandoned_count' => 0,
        'in_progress_count' => 0,
        'funnel' => [
            'arrived' => 0,
            'started' => 0,
            'completed' => 0,
        ],
        'choice_questions' => [],
        'open_questions' => [],
        'updated_at' => gmdate('c'),
    ];
}

function formBuilderBuildInsightsSummary(array $form, array $responses, array $sessions): array
{
    $summary = formBuilderEmptyInsightsSummary();
    $fields = formBuilderNormalizedStoredFields($form);
    $fieldMap = [];
    $choiceTypes = ['single_choice', 'multiple_choice', 'dropdown'];
    $staleBoundary = strtotime('-30 minutes') ?: time();

    foreach ($fields as $field) {
        $fieldId = (string) ($field['id'] ?? '');
        $type = (string) ($field['type'] ?? 'short_text');

        if ($fieldId === '' || trim((string) ($field['label'] ?? '')) === '') {
            continue;
        }

        $fieldMap[$fieldId] = $field;

        if (in_array($type, $choiceTypes, true)) {
            $summary['choice_questions'][$fieldId] = [
                'id' => $fieldId,
                'type' => $type,
                'label' => (string) ($field['label'] ?? ''),
                'answer_count' => 0,
                'selection_count' => 0,
                'options' => array_map(static function (array $option): array {
                    return [
                        'label' => (string) ($option['label'] ?? ''),
                        'count' => 0,
                        'percent' => 0,
                    ];
                }, (array) ($field['options'] ?? [])),
            ];
        } else {
            $summary['open_questions'][$fieldId] = [
                'id' => $fieldId,
                'type' => $type,
                'label' => (string) ($field['label'] ?? ''),
                'responses' => [],
            ];
        }
    }

    foreach ($responses as $response) {
        $answers = $response['answers'] ?? [];

        if (is_string($answers)) {
            $decoded = json_decode($answers, true);
            $answers = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($answers)) {
            continue;
        }

        foreach ($fieldMap as $fieldId => $field) {
            $answerValue = $answers[$fieldId]['value'] ?? null;
            $type = (string) ($field['type'] ?? 'short_text');

            if (in_array($type, $choiceTypes, true)) {
                $selectedValues = is_array($answerValue)
                    ? array_values(array_filter(array_map('strval', $answerValue), static fn(string $value): bool => trim($value) !== ''))
                    : (trim((string) $answerValue) !== '' ? [trim((string) $answerValue)] : []);

                if ($selectedValues === []) {
                    continue;
                }

                $summary['choice_questions'][$fieldId]['answer_count']++;

                foreach ($selectedValues as $selectedValue) {
                    foreach ($summary['choice_questions'][$fieldId]['options'] as &$option) {
                        if ((string) ($option['label'] ?? '') !== $selectedValue) {
                            continue;
                        }

                        $option['count']++;
                        $summary['choice_questions'][$fieldId]['selection_count']++;
                        break;
                    }
                    unset($option);
                }

                continue;
            }

            $normalizedValue = is_array($answerValue)
                ? implode(', ', array_map('strval', $answerValue))
                : trim((string) $answerValue);

            if ($normalizedValue === '') {
                continue;
            }

            $summary['open_questions'][$fieldId]['responses'][] = [
                'value' => $normalizedValue,
                'submitted_at' => (string) ($response['submitted_at'] ?? ''),
            ];
        }
    }

    foreach ($summary['choice_questions'] as &$question) {
        $selectionCount = max(0, (int) ($question['selection_count'] ?? 0));

        foreach ($question['options'] as &$option) {
            $count = (int) ($option['count'] ?? 0);
            $option['percent'] = $selectionCount > 0 ? round(($count / $selectionCount) * 100, 1) : 0;
        }
        unset($option);
    }
    unset($question);

    $summary['completed_count'] = count($responses);
    $summary['completed_sessions_count'] = count(array_filter($sessions, static function (array $session): bool {
        return trim((string) ($session['completed_at'] ?? '')) !== ''
            || trim((string) ($session['status'] ?? '')) === 'completed';
    }));
    $summary['visits_count'] = count($sessions);
    $summary['started_count'] = count(array_filter($sessions, static function (array $session): bool {
        return trim((string) ($session['started_at'] ?? '')) !== ''
            || in_array(trim((string) ($session['status'] ?? '')), ['started', 'completed', 'abandoned'], true);
    }));
    $summary['abandoned_count'] = count(array_filter($sessions, static function (array $session) use ($staleBoundary): bool {
        $completedAt = trim((string) ($session['completed_at'] ?? ''));
        $startedAt = trim((string) ($session['started_at'] ?? ''));
        $status = trim((string) ($session['status'] ?? ''));
        $lastSeenAt = trim((string) ($session['last_seen_at'] ?? ''));
        $lastSeenTs = $lastSeenAt !== '' ? strtotime($lastSeenAt) : false;

        if ($completedAt !== '' || $startedAt === '') {
            return false;
        }

        return $status === 'abandoned' || ($lastSeenTs !== false && $lastSeenTs <= $staleBoundary);
    }));

    $funnelCompleted = max($summary['completed_count'], $summary['completed_sessions_count']);
    $funnelStarted = max($summary['started_count'], $funnelCompleted);
    $funnelArrived = max($summary['visits_count'], $funnelStarted);
    $funnelAbandoned = min(max(0, $summary['abandoned_count']), max(0, $funnelStarted - $funnelCompleted));
    $inProgressCount = max(0, $funnelStarted - $funnelCompleted - $funnelAbandoned);

    $summary['visits_count'] = $funnelArrived;
    $summary['started_count'] = $funnelStarted;
    $summary['completed_sessions_count'] = $funnelCompleted;
    $summary['abandoned_count'] = $funnelAbandoned;
    $summary['in_progress_count'] = $inProgressCount;
    $summary['funnel'] = [
        'arrived' => $funnelArrived,
        'started' => $funnelStarted,
        'completed' => $funnelCompleted,
    ];
    $summary['updated_at'] = gmdate('c');
    $summary['choice_questions'] = array_values($summary['choice_questions']);
    $summary['open_questions'] = array_values($summary['open_questions']);

    return $summary;
}
