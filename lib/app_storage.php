<?php
declare(strict_types=1);

require_once __DIR__ . '/supabase_api.php';

function appStorageConfig(): array
{
    $config = require __DIR__ . '/../config/storage.php';

    if (!is_array($config)) {
        throw new RuntimeException('La configuracion de almacenamiento no es valida.');
    }

    return $config;
}

function appStorageBucket(): string
{
    $bucket = trim((string) (appStorageConfig()['bucket'] ?? ''));

    if ($bucket === '') {
        throw new RuntimeException('Configura el bucket central en config/storage.php.');
    }

    return $bucket;
}

function appStorageScope(string $scope): string
{
    $scopes = appStorageConfig()['scopes'] ?? [];
    $resolved = is_array($scopes) ? trim((string) ($scopes[$scope] ?? $scope)) : $scope;
    $normalized = preg_replace('/[^a-zA-Z0-9\-_.]+/', '-', $resolved) ?? '';
    $normalized = trim($normalized, '-_.');

    if ($normalized === '') {
        throw new RuntimeException('El scope de almacenamiento no es valido.');
    }

    return $normalized;
}

function appStorageUserPath(string $userId, string $scope, string ...$segments): string
{
    $pathSegments = [
        appStoragePathSegment($userId),
        appStorageScope($scope),
    ];

    foreach ($segments as $segment) {
        $normalized = appStoragePathSegment($segment);

        if ($normalized !== '') {
            $pathSegments[] = $normalized;
        }
    }

    return implode('/', $pathSegments);
}

function appStoragePublicBaseUrl(): string
{
    return supabaseProjectUrl() . '/storage/v1/object/public/' . rawurlencode(appStorageBucket()) . '/';
}

function appStoragePublicUrl(string $path): string
{
    return appStoragePublicBaseUrl() . appStorageEncodePath($path);
}

function appStorageObjectUrl(string $path): string
{
    return supabaseProjectUrl() . '/storage/v1/object/' . rawurlencode(appStorageBucket()) . '/' . appStorageEncodePath($path);
}

function appStorageIsManagedPublicUrl(string $value): bool
{
    return str_starts_with(strtolower(trim($value)), strtolower(appStoragePublicBaseUrl()));
}

function appStorageEncodePath(string $path): string
{
    $segments = array_filter(explode('/', trim($path, '/')), static fn (string $segment): bool => $segment !== '');

    return implode('/', array_map('rawurlencode', $segments));
}

function appStoragePathSegment(string $value): string
{
    $normalized = trim($value);
    $normalized = preg_replace('/[^a-zA-Z0-9\-_.]+/', '-', $normalized) ?? '';
    $normalized = trim($normalized, '-_.');

    return $normalized;
}
