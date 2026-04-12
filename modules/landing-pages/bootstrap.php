<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/app_routing.php';
require_once __DIR__ . '/../../lib/supabase_api.php';
require_once __DIR__ . '/../../lib/app_storage.php';
require_once __DIR__ . '/LandingPageRepository.php';

function normalizeLandingBuilderException(Throwable $exception): string
{
    $message = $exception->getMessage();
    $normalized = strtolower($message);

    if (
        str_contains($normalized, 'could not find the table')
        || str_contains($normalized, 'pgrst205')
        || str_contains($normalized, 'schema cache')
        || str_contains($normalized, 'does not exist')
    ) {
        return 'La estructura de landing pages aun no existe. Ejecuta supabase/landing_pages_schema.sql en Supabase.';
    }

    if (str_contains($normalized, 'row-level security')) {
        return 'Supabase bloqueo la operacion por permisos. Revisa las politicas de supabase/landing_pages_schema.sql.';
    }

    if (str_contains($normalized, 'bucket') || str_contains($normalized, 'storage')) {
        return 'Falta configurar Supabase Storage centralizado. Ejecuta supabase/user_files_storage_setup.sql en Supabase.';
    }

    if (str_contains($normalized, 'duplicate key') || str_contains($normalized, 'landing_pages_project_slug_unique')) {
        return 'Ya existe una landing con un identificador interno similar. Guarda nuevamente o cambia ligeramente el titulo.';
    }

    if (str_contains($normalized, 'get_public_landing_page_definition')) {
        return 'Falta la funcion publica de landing pages. Ejecuta supabase/landing_pages_schema.sql en Supabase.';
    }

    return $message !== '' ? $message : 'Ocurrio un error inesperado en landing pages.';
}

function normalizeLandingSlug(string $value): string
{
    $normalized = strtolower(trim($value));
    $normalized = preg_replace('/[^a-z0-9]+/i', '-', $normalized) ?? '';
    $normalized = trim($normalized, '-');

    return $normalized !== '' ? $normalized : 'landing';
}

function generateLandingBlockId(): string
{
    return 'block_' . bin2hex(random_bytes(5));
}

function landingShareUrl(string $publicId): string
{
    return appPublicLandingUrl($publicId);
}

function landingBuilderJsonEncode(array $payload): string
{
    return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
}
