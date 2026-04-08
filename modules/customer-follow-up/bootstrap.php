<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/supabase_api.php';
require_once __DIR__ . '/CustomerPipelineRepository.php';

function normalizeCustomerPipelineException(Throwable $exception): string
{
    $message = $exception->getMessage();
    $normalized = strtolower($message);

    if (
        str_contains($normalized, 'could not find the table')
        || str_contains($normalized, 'pgrst205')
        || str_contains($normalized, 'schema cache')
        || str_contains($normalized, 'does not exist')
        || str_contains($normalized, 'customer_pipeline_')
    ) {
        return 'La estructura de Seguimiento de Clientes aun no existe. Ejecuta supabase/customer_pipeline_schema.sql en Supabase.';
    }

    if (str_contains($normalized, 'row-level security')) {
        return 'Supabase bloqueo la operacion por permisos. Revisa supabase/customer_pipeline_schema.sql.';
    }

    if (str_contains($normalized, 'submit_public_customer_lead')) {
        return 'Falta activar la funcion publica de ingreso de leads. Ejecuta supabase/customer_pipeline_schema.sql.';
    }

    return $message !== '' ? $message : 'Ocurrio un error inesperado en Seguimiento de Clientes.';
}

function customerPipelineJsonEncode(array $payload): string
{
    return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
}

function customerPipelineWebhookUrl(string $publicKey): string
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

    return $scheme . '://' . $host . $basePath . '/lead-intake.php?key=' . rawurlencode($publicKey);
}

