<?php
declare(strict_types=1);

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

    if (str_contains($normalized, 'get_public_form_definition') || str_contains($normalized, 'submit_public_form_response')) {
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
    $scheme = 'http';

    if (
        (isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
    ) {
        $scheme = 'https';
    }

    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/tool.php'));
    $basePath = rtrim(dirname($scriptName), '/');

    if ($basePath === '/' || $basePath === '.') {
        $basePath = '';
    }

    return $scheme . '://' . $host . $basePath . '/form.php?f=' . rawurlencode($publicId);
}

function formBuilderJsonEncode(array $payload): string
{
    return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
}
