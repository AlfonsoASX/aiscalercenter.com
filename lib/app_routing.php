<?php
declare(strict_types=1);

function appDomainConfig(): array
{
    static $config = null;

    if ($config === null) {
        $config = require __DIR__ . '/../config/domains.php';
    }

    return is_array($config) ? $config : [];
}

function appConfiguredHost(string $key): string
{
    $config = appDomainConfig();

    return strtolower(trim((string) ($config[$key] ?? '')));
}

function appRequestScheme(): string
{
    if (
        (isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
    ) {
        return 'https';
    }

    return 'http';
}

function appCurrentAuthority(): string
{
    return trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
}

function appCurrentHost(): string
{
    return strtolower(preg_replace('/:\d+$/', '', appCurrentAuthority()) ?? appCurrentAuthority());
}

function appAuthorityForHost(string $host): string
{
    $normalizedHost = strtolower(trim($host));

    if ($normalizedHost === '') {
        return appCurrentAuthority();
    }

    return $normalizedHost === appCurrentHost() ? appCurrentAuthority() : $normalizedHost;
}

function appUsesConfiguredSubdomains(): bool
{
    $currentHost = appCurrentHost();
    $configuredHosts = array_filter([
        appConfiguredHost('app_host'),
        appConfiguredHost('forms_host'),
        appConfiguredHost('landing_host'),
    ]);

    return in_array($currentHost, $configuredHosts, true);
}

function appIsPrimaryHost(): bool
{
    return appCurrentHost() === appConfiguredHost('app_host');
}

function appIsFormsHost(): bool
{
    return appCurrentHost() === appConfiguredHost('forms_host');
}

function appIsLandingHost(): bool
{
    return appCurrentHost() === appConfiguredHost('landing_host');
}

function appShouldEnablePwa(): bool
{
    return appIsPrimaryHost();
}

function appRootBasePath(): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $scriptDirectory = rtrim(dirname($scriptName), '/');
    $basePath = preg_replace('#/api$#', '', $scriptDirectory) ?: '';

    if ($basePath === '/' || $basePath === '.') {
        return '';
    }

    return $basePath;
}

function appNormalizePath(string $path): string
{
    $trimmed = trim($path);

    if ($trimmed === '' || $trimmed === '/') {
        return '/';
    }

    return '/' . ltrim($trimmed, '/');
}

function appPath(string $path = '/', array $query = []): string
{
    $normalizedPath = appNormalizePath($path);
    $basePath = appRootBasePath();
    $resolvedPath = ($basePath === '' ? '' : $basePath) . ($normalizedPath === '/' ? '/' : $normalizedPath);

    if ($resolvedPath === '') {
        $resolvedPath = '/';
    }

    if ($query !== []) {
        $resolvedPath .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    return $resolvedPath;
}

function appAbsoluteUrl(string $path = '/', array $query = [], ?string $host = null): string
{
    $targetHost = trim((string) ($host ?? appCurrentHost()));

    return appRequestScheme() . '://' . appAuthorityForHost($targetHost) . appPath($path, $query);
}

function appPreferredPrimaryHost(): string
{
    return appUsesConfiguredSubdomains() ? appConfiguredHost('app_host') : appCurrentHost();
}

function appHomeUrl(bool $absolute = false): string
{
    return $absolute ? appAbsoluteUrl('/', [], appPreferredPrimaryHost()) : appPath('/');
}

function appLoginUrl(bool $absolute = false): string
{
    return $absolute ? appAbsoluteUrl('/login', [], appPreferredPrimaryHost()) : appPath('/login');
}

function appPanelUrl(?string $sectionId = null, bool $absolute = false): string
{
    $url = $absolute ? appAbsoluteUrl('/app', [], appPreferredPrimaryHost()) : appPath('/app');
    $candidate = trim((string) ($sectionId ?? ''));

    if ($candidate !== '') {
        $url .= '#' . rawurlencode($candidate);
    }

    return $url;
}

function appBlogUrl(string $slug, bool $absolute = false): string
{
    $path = '/blog/' . rawurlencode(trim($slug));

    return $absolute ? appAbsoluteUrl($path, [], appPreferredPrimaryHost()) : appPath($path);
}

function appTermsUrl(bool $absolute = false): string
{
    return $absolute
        ? appAbsoluteUrl('/terminos-y-condiciones', [], appPreferredPrimaryHost())
        : appPath('/terminos-y-condiciones');
}

function appPrivacyUrl(bool $absolute = false): string
{
    return $absolute
        ? appAbsoluteUrl('/aviso-de-privacidad', [], appPreferredPrimaryHost())
        : appPath('/aviso-de-privacidad');
}

function appToolUrl(string $script, array $query = [], bool $absolute = false): string
{
    $path = '/' . ltrim($script, '/');

    return $absolute
        ? appAbsoluteUrl($path, $query, appPreferredPrimaryHost())
        : appPath($path, $query);
}

function appPublicFormUrl(string $publicId): string
{
    $publicId = trim($publicId);

    if ($publicId === '') {
        return appUsesConfiguredSubdomains()
            ? appAbsoluteUrl('/', [], appConfiguredHost('forms_host'))
            : appAbsoluteUrl('/form.php');
    }

    if (appUsesConfiguredSubdomains()) {
        return appAbsoluteUrl('/' . rawurlencode($publicId), [], appConfiguredHost('forms_host'));
    }

    return appAbsoluteUrl('/form.php', ['f' => $publicId]);
}

function appPublicLandingUrl(string $publicId): string
{
    $publicId = trim($publicId);

    if ($publicId === '') {
        return appUsesConfiguredSubdomains()
            ? appAbsoluteUrl('/', [], appConfiguredHost('landing_host'))
            : appAbsoluteUrl('/landing.php');
    }

    if (appUsesConfiguredSubdomains()) {
        return appAbsoluteUrl('/' . rawurlencode($publicId), [], appConfiguredHost('landing_host'));
    }

    return appAbsoluteUrl('/landing.php', ['p' => $publicId]);
}

function appCurrentRequestPath(): string
{
    $uriPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $resolvedPath = is_string($uriPath) && $uriPath !== '' ? $uriPath : '/';
    $basePath = appRootBasePath();

    if ($basePath !== '' && str_starts_with($resolvedPath, $basePath . '/')) {
        $resolvedPath = substr($resolvedPath, strlen($basePath));
    } elseif ($basePath !== '' && $resolvedPath === $basePath) {
        $resolvedPath = '/';
    }

    return $resolvedPath !== '' ? $resolvedPath : '/';
}

function appCurrentPublicIdentifier(): string
{
    $trimmedPath = trim(appCurrentRequestPath(), '/');

    if ($trimmedPath === '') {
        return '';
    }

    $segments = explode('/', $trimmedPath);

    return rawurldecode((string) ($segments[0] ?? ''));
}
