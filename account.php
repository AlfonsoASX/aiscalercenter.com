<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/pwa.php';
require_once __DIR__ . '/lib/account_session.php';
require_once __DIR__ . '/lib/billing.php';
require_once __DIR__ . '/lib/private_apps.php';

ensureAccountSessionStarted();

$supabaseConfig = require __DIR__ . '/config/supabase.php';
$panelConfig = require __DIR__ . '/config/panel.php';
$supabaseProjectUrl = trim((string) ($supabaseConfig['project_url'] ?? ''));
$publishableKey = trim((string) ($supabaseConfig['publishable_key'] ?? ''));
$anonKey = trim((string) ($supabaseConfig['anon_key'] ?? ''));
$supabasePublicKey = $publishableKey !== '' && $publishableKey !== 'tu_publishable_key' ? $publishableKey : $anonKey;
$hasSupabaseConfig = $supabaseProjectUrl !== ''
    && $supabasePublicKey !== ''
    && $supabasePublicKey !== 'tu_publishable_key'
    && $supabasePublicKey !== 'tu_anon_key';

$redirectPath = appPath('/cuenta');

try {
    [$accessToken, $user] = requireStoredAuthenticatedAccount();
} catch (Throwable) {
    accountRedirectToLogin($redirectPath);
}

$billingState = billingResolveAccountState($accessToken, (string) ($user['id'] ?? ''));
$subscription = is_array($billingState['subscription'] ?? null) ? $billingState['subscription'] : [];
$displayName = trim((string) ($user['user_metadata']['full_name'] ?? '')) ?: trim((string) ($user['email'] ?? 'Usuario'));
$accountSection = trim((string) ($_GET['section'] ?? ''));

if ($accountSection === '') {
    $accountPath = trim((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/cuenta'), PHP_URL_PATH) ?? ''));
    $accountSection = str_ends_with($accountPath, '/suscripcion')
        ? 'suscripcion'
        : (str_ends_with($accountPath, '/facturacion') ? 'facturacion' : 'cuenta');
}

$authClientConfig = [
    'supabaseUrl' => $supabaseProjectUrl,
    'supabaseKey' => $supabasePublicKey,
    'landingUrl' => appHomeUrl(),
    'loginUrl' => appLoginUrl(),
    'appUrl' => appAccountUrl(),
    'accountUrl' => appAccountUrl(),
    'defaultAfterLoginUrl' => appAccountUrl(),
    'redirectTarget' => appPath('/cuenta'),
    'accountSessionUrl' => appToolUrl('api/account-session.php'),
    'toolsSessionUrl' => appToolUrl('api/tools-session.php'),
    'hasSupabaseConfig' => $hasSupabaseConfig,
    'panel' => $panelConfig,
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuenta - AiScaler Center</title>
    <?= renderPwaHead([
        'description' => 'Administra tu cuenta, suscripcion y acceso al ecosistema ASX.',
        'background_color' => '#f5f7fb',
    ]); ?>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,500,0,0">
    <style>
        :root {
            --surface: #f5f7fb;
            --card: #ffffff;
            --text: #13203a;
            --muted: #5f6b7c;
            --line: rgba(19, 32, 58, 0.12);
            --primary: #0f766e;
            --primary-strong: #115e59;
            --danger: #d93025;
            --warning: #df9c0a;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Manrope, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(15, 118, 110, 0.12), transparent 24rem),
                linear-gradient(180deg, #f8fbff 0%, var(--surface) 100%);
            color: var(--text);
        }
        .account-shell {
            width: min(1200px, calc(100% - 1.5rem));
            margin: 0 auto;
            padding: 1rem 0 2rem;
        }
        .account-topbar, .account-card {
            border: 1px solid var(--line);
            border-radius: 1.5rem;
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(18px);
            box-shadow: 0 18px 40px rgba(19, 32, 58, 0.08);
        }
        .account-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.15rem;
        }
        .account-brand, .account-nav, .account-user {
            display: flex;
            align-items: center;
            gap: .75rem;
        }
        .account-brand a, .account-nav a {
            color: inherit;
            text-decoration: none;
            font-weight: 800;
        }
        .account-nav a {
            padding: .75rem .95rem;
            border-radius: 999px;
            color: var(--muted);
        }
        .account-nav a.is-active {
            background: rgba(15,118,110,.12);
            color: var(--primary-strong);
        }
        .account-link-button, .account-primary-button, .account-secondary-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .55rem;
            min-height: 3rem;
            border-radius: 999px;
            padding: 0 1rem;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--text);
            font: inherit;
            text-decoration: none;
            cursor: pointer;
        }
        .account-primary-button {
            border-color: rgba(15,118,110,.2);
            background: linear-gradient(135deg, var(--primary), var(--primary-strong));
            color: #fff;
        }
        .account-grid {
            display: grid;
            gap: 1rem;
            margin-top: 1rem;
            grid-template-columns: minmax(0, 1.2fr) minmax(320px, .8fr);
        }
        .account-card { padding: 1.25rem; }
        .account-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            color: var(--primary-strong);
            font-size: .82rem;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .account-card h1, .account-card h2, .account-card h3 { margin: .6rem 0 0; }
        .account-card p { color: var(--muted); line-height: 1.7; }
        .account-status-pill {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            border-radius: 999px;
            padding: .5rem .75rem;
            font-weight: 800;
        }
        .account-status-pill.is-active { background: rgba(15,157,88,.12); color: #0b8043; }
        .account-status-pill.is-read-only { background: rgba(223,156,10,.14); color: #835b00; }
        .account-app-grid {
            display: grid;
            gap: .85rem;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            margin-top: 1rem;
        }
        .account-app-card {
            display: grid;
            gap: .6rem;
            padding: 1rem;
            border: 1px solid var(--line);
            border-radius: 1.25rem;
            background: #fff;
            text-decoration: none;
            color: inherit;
        }
        .account-meta-list {
            display: grid;
            gap: .85rem;
            margin-top: 1rem;
        }
        .account-meta-item {
            border: 1px solid var(--line);
            border-radius: 1rem;
            padding: .95rem 1rem;
            background: #fff;
        }
        .account-meta-item small {
            display: block;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .08em;
            font-weight: 800;
            font-size: .72rem;
        }
        .account-meta-item strong {
            display: block;
            margin-top: .35rem;
        }
        @media (max-width: 960px) {
            .account-grid { grid-template-columns: 1fr; }
            .account-topbar { flex-direction: column; align-items: stretch; }
            .account-nav { flex-wrap: wrap; }
        }
    </style>
    <script>
        window.AISCALER_AUTH_CONFIG = <?= json_encode($authClientConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    </script>
    <script type="module" src="<?= htmlspecialchars(pwaAssetUrl('js/account-shell.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</head>
<body data-view="account">
    <div class="account-shell">
        <header class="account-topbar">
            <div class="account-brand">
                <a href="<?= htmlspecialchars(appAccountUrl(), ENT_QUOTES, 'UTF-8'); ?>">AiScaler Account</a>
                <span class="account-status-pill <?= $billingState['read_only'] ? 'is-read-only' : 'is-active'; ?>">
                    <span class="material-symbols-rounded"><?= $billingState['read_only'] ? 'lock' : 'verified'; ?></span>
                    <span><?= htmlspecialchars($billingState['read_only'] ? 'Modo lectura' : 'Suscripcion activa', ENT_QUOTES, 'UTF-8'); ?></span>
                </span>
            </div>

            <nav class="account-nav" aria-label="Cuenta">
                <a href="<?= htmlspecialchars(appAccountUrl(), ENT_QUOTES, 'UTF-8'); ?>" class="<?= $accountSection === 'cuenta' ? 'is-active' : ''; ?>">Cuenta</a>
                <a href="<?= htmlspecialchars(appAccountUrl('suscripcion'), ENT_QUOTES, 'UTF-8'); ?>" class="<?= $accountSection === 'suscripcion' ? 'is-active' : ''; ?>">Suscripcion</a>
                <a href="<?= htmlspecialchars(appAccountUrl('facturacion'), ENT_QUOTES, 'UTF-8'); ?>" class="<?= $accountSection === 'facturacion' ? 'is-active' : ''; ?>">Facturacion</a>
            </nav>

            <div class="account-user">
                <strong><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></strong>
                <button type="button" class="account-link-button" data-account-logout>Salir</button>
            </div>
        </header>

        <section class="account-grid">
            <article class="account-card">
                <span class="account-eyebrow"><span class="material-symbols-rounded">manage_accounts</span>Cuenta ASX</span>
                <h1><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p><?= htmlspecialchars((string) ($user['email'] ?? 'Cuenta sin correo'), ENT_QUOTES, 'UTF-8'); ?></p>

                <?php if ($accountSection === 'suscripcion'): ?>
                    <h2>Suscripcion global</h2>
                    <p>Una sola suscripcion mensual da acceso a todo el ecosistema. Cuando no esta activa, las apps privadas siguen cargando en modo lectura.</p>
                    <div class="account-meta-list">
                        <div class="account-meta-item">
                            <small>Plan</small>
                            <strong><?= htmlspecialchars(billingPlanLabel(), ENT_QUOTES, 'UTF-8'); ?> · 330 MXN / mes</strong>
                        </div>
                        <div class="account-meta-item">
                            <small>Estado</small>
                            <strong><?= htmlspecialchars((string) ($billingState['status'] ?? 'inactive'), ENT_QUOTES, 'UTF-8'); ?></strong>
                        </div>
                        <div class="account-meta-item">
                            <small>Renovacion</small>
                            <strong><?= htmlspecialchars((string) ($subscription['current_period_end'] ?? 'Sin fecha'), ENT_QUOTES, 'UTF-8'); ?></strong>
                        </div>
                    </div>
                <?php elseif ($accountSection === 'facturacion'): ?>
                    <h2>Facturacion</h2>
                    <p>Desde aqui podras abrir el portal de cliente de Stripe para actualizar tarjeta, cancelar o reactivar tu plan.</p>
                    <div class="account-meta-list">
                        <div class="account-meta-item">
                            <small>Cliente Stripe</small>
                            <strong><?= htmlspecialchars((string) ($subscription['provider_customer_id'] ?? 'Pendiente de sincronizar'), ENT_QUOTES, 'UTF-8'); ?></strong>
                        </div>
                        <div class="account-meta-item">
                            <small>Suscripcion Stripe</small>
                            <strong><?= htmlspecialchars((string) ($subscription['provider_subscription_id'] ?? 'Pendiente de sincronizar'), ENT_QUOTES, 'UTF-8'); ?></strong>
                        </div>
                    </div>
                <?php else: ?>
                    <h2>Apps privadas</h2>
                    <p>Entra directo a cada app del ecosistema sin pasar por el panel antiguo. Cada app resolvera su propio workspace automaticamente.</p>
                    <div class="account-app-grid">
                        <?php foreach (privateAppDefinitions() as $app): ?>
                            <?php if ((string) ($app['mode'] ?? '') === 'redirect_legacy') { continue; } ?>
                            <a class="account-app-card" href="<?= htmlspecialchars(privateAppUrl((string) ($app['key'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">
                                <strong><?= htmlspecialchars((string) ($app['title'] ?? 'App'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span><?= htmlspecialchars((string) ($app['route'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>

            <aside class="account-card">
                <span class="account-eyebrow"><span class="material-symbols-rounded">credit_card</span>Billing</span>
                <h3><?= htmlspecialchars($billingState['read_only'] ? 'Reactiva tu acceso' : 'Tu cuenta esta al dia', ENT_QUOTES, 'UTF-8'); ?></h3>
                <p><?= htmlspecialchars((string) ($billingState['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="account-meta-list">
                    <div class="account-meta-item">
                        <small>Plan</small>
                        <strong><?= htmlspecialchars(billingPlanLabel(), ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                    <div class="account-meta-item">
                        <small>Precio</small>
                        <strong>330 MXN al mes</strong>
                    </div>
                    <div class="account-meta-item">
                        <small>Acceso</small>
                        <strong><?= htmlspecialchars($billingState['read_only'] ? 'Lectura en apps privadas' : 'Lectura y escritura completas', ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                </div>
                <div style="display:flex; gap:.75rem; flex-wrap:wrap; margin-top:1rem;">
                    <button type="button" class="account-primary-button" data-billing-action="checkout">Abrir checkout</button>
                    <button type="button" class="account-secondary-button" data-billing-action="portal">Abrir portal</button>
                </div>
            </aside>
        </section>
    </div>
</body>
</html>
