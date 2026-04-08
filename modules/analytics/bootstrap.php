<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/supabase_api.php';
require_once __DIR__ . '/AnalyticsRepository.php';

use AiScaler\Analytics\AnalyticsRepository;

function normalizeAnalyticsException(Throwable $exception): string
{
    $message = $exception->getMessage();
    $normalized = strtolower($message);

    if (
        str_contains($normalized, 'could not find the table')
        || str_contains($normalized, 'pgrst205')
        || str_contains($normalized, 'schema cache')
        || str_contains($normalized, 'does not exist')
    ) {
        return 'La estructura avanzada de analitica aun no existe. Ejecuta supabase/analytics_schema.sql en Supabase para desbloquear las metricas enriquecidas.';
    }

    if (str_contains($normalized, 'row-level security')) {
        return 'Supabase bloqueo la lectura de analitica por permisos. Revisa las politicas de supabase/analytics_schema.sql.';
    }

    return $message !== '' ? $message : 'Ocurrio un error inesperado al cargar Analiza.';
}

function analyticsResolveToolContext(array $toolContext, string $toolName, AnalyticsRepository $repository): array
{
    $accessToken = trim((string) ($toolContext['access_token'] ?? ''));
    $userId = trim((string) ($toolContext['user_id'] ?? ''));
    $project = is_array($toolContext['project'] ?? null) ? $toolContext['project'] : [];
    $projectId = trim((string) ($project['id'] ?? ''));

    if ($accessToken === '' || $userId === '') {
        throw new RuntimeException('No encontramos la sesion segura para abrir ' . $toolName . '.');
    }

    if ($projectId === '') {
        throw new RuntimeException('Selecciona un proyecto antes de abrir ' . $toolName . '.');
    }

    $resolvedProject = $repository->findProject($accessToken, $projectId);

    if (!is_array($resolvedProject)) {
        throw new RuntimeException('No encontramos el proyecto activo de ' . $toolName . '.');
    }

    return [
        'access_token' => $accessToken,
        'user_id' => $userId,
        'project_id' => $projectId,
        'project' => $resolvedProject,
    ];
}

function analyticsFormatNumber($value, string $fallback = '--'): string
{
    if (!is_numeric($value)) {
        return $fallback;
    }

    return number_format((float) $value, 0, '.', ',');
}

function analyticsFormatMoney($value, string $currency = 'MXN', string $fallback = '--'): string
{
    if (!is_numeric($value)) {
        return $fallback;
    }

    $symbol = strtoupper($currency) === 'USD' ? 'US$' : '$';

    return $symbol . number_format((float) $value, 2, '.', ',');
}

function analyticsFormatPercent($value, string $fallback = '--'): string
{
    if (!is_numeric($value)) {
        return $fallback;
    }

    return number_format((float) $value, 1, '.', ',') . '%';
}

function analyticsFormatDate(?string $value, string $fallback = 'Sin fecha'): string
{
    if ($value === null || trim($value) === '') {
        return $fallback;
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $fallback;
    }

    return date('d M Y', $timestamp);
}

function analyticsFormatDateTime(?string $value, string $fallback = 'Sin fecha'): string
{
    if ($value === null || trim($value) === '') {
        return $fallback;
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $fallback;
    }

    return date('d M Y H:i', $timestamp);
}

function analyticsSlugify(string $value, string $fallback = 'campania'): string
{
    $normalized = strtolower(trim($value));
    $normalized = preg_replace('/[^a-z0-9]+/i', '-', $normalized) ?? '';
    $normalized = trim($normalized, '-');

    return $normalized !== '' ? $normalized : $fallback;
}

function analyticsProviderLabel(string $providerKey): string
{
    $map = [
        'facebook_page' => 'Facebook',
        'facebook_profile' => 'Facebook Perfil',
        'instagram' => 'Instagram',
        'youtube_channel' => 'YouTube',
        'linkedin_profile' => 'LinkedIn Perfil',
        'linkedin_company' => 'LinkedIn Empresa',
        'google_business_profile' => 'Google Business Profile',
        'mercado_libre' => 'Mercado Libre',
        'amazon' => 'Amazon',
        'tiktok' => 'TikTok',
        'whatsapp' => 'WhatsApp',
    ];

    $normalized = trim($providerKey);

    if ($normalized === '') {
        return 'Canal';
    }

    return $map[$normalized] ?? ucwords(str_replace(['_', '-'], ' ', $normalized));
}

function analyticsAppendQueryParams(string $url, array $params): string
{
    $trimmed = trim($url);

    if ($trimmed === '') {
        return '';
    }

    $parts = parse_url($trimmed);

    if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
        return $trimmed;
    }

    $query = [];

    if (isset($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }

        $query[(string) $key] = (string) $value;
    }

    $rebuilt = $parts['scheme'] . '://' . $parts['host'];

    if (isset($parts['port'])) {
        $rebuilt .= ':' . $parts['port'];
    }

    $rebuilt .= $parts['path'] ?? '';
    $rebuilt .= $query !== [] ? '?' . http_build_query($query) : '';

    if (isset($parts['fragment']) && $parts['fragment'] !== '') {
        $rebuilt .= '#' . $parts['fragment'];
    }

    return $rebuilt;
}

function analyticsExtractTargetUrl(array $target): string
{
    $config = is_array($target['config'] ?? null) ? $target['config'] : [];
    $keys = ['link_url', 'button_url', 'redeem_url', 'url'];

    foreach ($keys as $key) {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function analyticsSeverityTone(string $severity): string
{
    $normalized = strtolower(trim($severity));

    if (in_array($normalized, ['critical', 'error'], true)) {
        return 'danger';
    }

    if ($normalized === 'warning') {
        return 'warning';
    }

    if (in_array($normalized, ['success', 'healthy'], true)) {
        return 'success';
    }

    return 'info';
}
