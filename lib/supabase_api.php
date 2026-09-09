<?php
declare(strict_types=1);

function closeCurlHandle($curl): void
{
    if (PHP_VERSION_ID < 80500) {
        curl_close($curl);
    }
}

function supabaseConfig(): array
{
    $config = require __DIR__ . '/../config/supabase.php';

    if (!is_array($config)) {
        throw new RuntimeException('La configuracion de Supabase no es valida.');
    }

    return $config;
}

function supabaseProjectUrl(): string
{
    $config = supabaseConfig();
    $projectUrl = rtrim((string) ($config['project_url'] ?? ''), '/');

    if ($projectUrl === '' || $projectUrl === 'https://tu-project-ref.supabase.co') {
        throw new RuntimeException('Completa project_url en config/supabase.php.');
    }

    return $projectUrl;
}

function supabaseApiKey(): string
{
    $config = supabaseConfig();
    $publishableKey = trim((string) ($config['publishable_key'] ?? ''));
    $anonKey = trim((string) ($config['anon_key'] ?? ''));

    if ($publishableKey !== '' && $publishableKey !== 'tu_publishable_key') {
        return $publishableKey;
    }

    if ($anonKey !== '' && $anonKey !== 'tu_anon_key') {
        return $anonKey;
    }

    throw new RuntimeException('Completa publishable_key o anon_key en config/supabase.php.');
}

function supabaseServiceRoleKey(): string
{
    $config = supabaseConfig();
    $serviceRoleKey = trim((string) ($config['service_role_key'] ?? ''));

    if ($serviceRoleKey === '') {
        throw new RuntimeException('Completa service_role_key en config/supabase.php para operaciones servidor-a-servidor.');
    }

    return $serviceRoleKey;
}

function supabaseRequest(
    string $method,
    string $path,
    array $payload = [],
    ?string $accessToken = null,
    array $extraHeaders = []
): array {
    $url = supabaseProjectUrl() . '/' . ltrim($path, '/');
    $apiKey = supabaseApiKey();
    $authorizationToken = $accessToken ?: $apiKey;

    $headers = array_merge(
        [
            'apikey: ' . $apiKey,
            'Authorization: Bearer ' . $authorizationToken,
            'Accept: application/json',
            'Content-Type: application/json',
        ],
        $extraHeaders
    );

    $curl = curl_init($url);

    if ($curl === false) {
        throw new RuntimeException('No se pudo inicializar cURL para Supabase.');
    }

    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

    if ($payload !== [] && !in_array(strtoupper($method), ['GET', 'HEAD'], true)) {
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($jsonPayload === false) {
            throw new RuntimeException('No se pudo convertir el payload a JSON.');
        }

        curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonPayload);
    }

    $responseBody = curl_exec($curl);

    if ($responseBody === false) {
        $errorMessage = curl_error($curl);
        closeCurlHandle($curl);
        throw new RuntimeException('Error al llamar la API de Supabase: ' . $errorMessage);
    }

    $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    closeCurlHandle($curl);

    $decoded = $responseBody !== '' ? json_decode($responseBody, true) : null;

    if ($statusCode >= 400) {
        $message = is_array($decoded)
            ? json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : $responseBody;

        throw new RuntimeException('Supabase respondio con error HTTP ' . $statusCode . ': ' . $message);
    }

    return [
        'status' => $statusCode,
        'data' => $decoded,
        'raw' => $responseBody,
    ];
}

function supabaseServiceRequest(
    string $method,
    string $path,
    array $payload = [],
    array $extraHeaders = []
): array {
    $url = supabaseProjectUrl() . '/' . ltrim($path, '/');
    $serviceRoleKey = supabaseServiceRoleKey();

    $headers = array_merge(
        [
            'apikey: ' . $serviceRoleKey,
            'Authorization: Bearer ' . $serviceRoleKey,
            'Accept: application/json',
            'Content-Type: application/json',
        ],
        $extraHeaders
    );

    $curl = curl_init($url);

    if ($curl === false) {
        throw new RuntimeException('No se pudo inicializar cURL para Supabase.');
    }

    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

    if ($payload !== [] && !in_array(strtoupper($method), ['GET', 'HEAD'], true)) {
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($jsonPayload === false) {
            throw new RuntimeException('No se pudo convertir el payload a JSON.');
        }

        curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonPayload);
    }

    $responseBody = curl_exec($curl);

    if ($responseBody === false) {
        $errorMessage = curl_error($curl);
        closeCurlHandle($curl);
        throw new RuntimeException('Error al llamar la API de Supabase: ' . $errorMessage);
    }

    $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    closeCurlHandle($curl);

    $decoded = $responseBody !== '' ? json_decode($responseBody, true) : null;

    if ($statusCode >= 400) {
        $message = is_array($decoded)
            ? json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : $responseBody;

        throw new RuntimeException('Supabase respondio con error HTTP ' . $statusCode . ': ' . $message);
    }

    return [
        'status' => $statusCode,
        'data' => $decoded,
        'raw' => $responseBody,
    ];
}

function supabaseAuthRequest(
    string $method,
    string $endpoint,
    array $payload = [],
    ?string $accessToken = null
): array {
    return supabaseRequest($method, 'auth/v1/' . ltrim($endpoint, '/'), $payload, $accessToken);
}

function supabaseRestRequest(
    string $method,
    string $endpoint,
    array $payload = [],
    ?string $accessToken = null,
    array $extraHeaders = []
): array {
    return supabaseRequest(
        $method,
        'rest/v1/' . ltrim($endpoint, '/'),
        $payload,
        $accessToken,
        $extraHeaders
    );
}

function supabaseServiceRestRequest(
    string $method,
    string $endpoint,
    array $payload = [],
    array $extraHeaders = []
): array {
    return supabaseServiceRequest(
        $method,
        'rest/v1/' . ltrim($endpoint, '/'),
        $payload,
        $extraHeaders
    );
}
