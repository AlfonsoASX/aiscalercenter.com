<?php
declare(strict_types=1);

require_once __DIR__ . '/app_routing.php';
require_once __DIR__ . '/supabase_api.php';

function ensureAccountSessionStarted(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('aiscaler_account');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => accountSessionUsesHttps(),
    ]);
    session_start();
}

function accountSessionUsesHttps(): bool
{
    if (isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        return strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
    }

    return false;
}

function rememberAccountServerAuth(array $payload): void
{
    ensureAccountSessionStarted();

    $_SESSION['aiscaler_account_auth'] = [
        'access_token' => (string) ($payload['access_token'] ?? ''),
        'user_id' => (string) ($payload['user_id'] ?? ''),
        'email' => (string) ($payload['email'] ?? ''),
        'created_at' => time(),
    ];
}

function getAccountServerAuth(): ?array
{
    ensureAccountSessionStarted();
    $payload = $_SESSION['aiscaler_account_auth'] ?? null;

    return is_array($payload) ? $payload : null;
}

function clearAccountServerAuth(): void
{
    ensureAccountSessionStarted();
    unset($_SESSION['aiscaler_account_auth']);
}

function resolveAccountBearerToken(): string
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authorizationHeader = '';

    foreach ($headers as $headerName => $headerValue) {
        if (strtolower((string) $headerName) === 'authorization') {
            $authorizationHeader = (string) $headerValue;
            break;
        }
    }

    if ($authorizationHeader === '' && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authorizationHeader = (string) $_SERVER['HTTP_AUTHORIZATION'];
    }

    if (preg_match('/Bearer\s+(.+)/i', $authorizationHeader, $matches) !== 1) {
        return '';
    }

    return trim((string) ($matches[1] ?? ''));
}

function authenticateAccountRequest(string $token): array
{
    if ($token === '') {
        throw new RuntimeException('Debes iniciar sesion para continuar.');
    }

    try {
        $response = supabaseAuthRequest('GET', 'user', [], $token);
    } catch (Throwable) {
        throw new RuntimeException('No se pudo validar la sesion actual.');
    }

    $data = $response['data'] ?? null;

    if (!is_array($data) || !isset($data['id'])) {
        throw new RuntimeException('La sesion actual ya no es valida.');
    }

    return $data;
}

function requireAuthenticatedAccountFromBearer(): array
{
    $token = resolveAccountBearerToken();
    return [$token, authenticateAccountRequest($token)];
}

function requireStoredAuthenticatedAccount(): array
{
    $auth = getAccountServerAuth();
    $token = trim((string) ($auth['access_token'] ?? ''));

    if ($token === '') {
        throw new RuntimeException('No encontramos la sesion protegida de tu cuenta.');
    }

    $user = authenticateAccountRequest($token);

    rememberAccountServerAuth([
        'access_token' => $token,
        'user_id' => (string) ($user['id'] ?? ''),
        'email' => (string) ($user['email'] ?? ''),
    ]);

    return [$token, $user];
}

function accountRedirectToLogin(string $targetPath = '/'): never
{
    $redirect = ltrim(trim($targetPath), '/');
    $url = appLoginRedirectUrl('/' . $redirect);
    header('Location: ' . $url, true, 302);
    exit;
}

function normalizeAccountException(Throwable $exception): string
{
    return $exception->getMessage() !== '' ? $exception->getMessage() : 'Ocurrio un error inesperado con la cuenta.';
}
