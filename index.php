<?php
declare(strict_types=1);

$supabaseConfig = require __DIR__ . '/config/supabase.php';

$supabaseProjectUrl = trim((string) ($supabaseConfig['project_url'] ?? ''));
$publishableKey = trim((string) ($supabaseConfig['publishable_key'] ?? ''));
$anonKey = trim((string) ($supabaseConfig['anon_key'] ?? ''));
$supabasePublicKey = $publishableKey !== '' && $publishableKey !== 'tu_publishable_key' ? $publishableKey : $anonKey;

$redirectUrl = strtok($_SERVER['REQUEST_URI'] ?? '/index.php', '?') ?: '/index.php';
$loginUrl = $redirectUrl . '?view=login';
$appUrl = $redirectUrl . '?view=app';
$view = (string) ($_GET['view'] ?? '');
$showLoginView = $view === 'login';
$showAppView = $view === 'app';
$hasSupabaseConfig = $supabaseProjectUrl !== ''
    && $supabaseProjectUrl !== 'https://tu-project-ref.supabase.co'
    && $supabasePublicKey !== ''
    && $supabasePublicKey !== 'tu_publishable_key'
    && $supabasePublicKey !== 'tu_anon_key';

$authClientConfig = [
    'supabaseUrl' => $supabaseProjectUrl,
    'supabaseKey' => $supabasePublicKey,
    'landingUrl' => $redirectUrl,
    'loginUrl' => $loginUrl,
    'appUrl' => $appUrl,
    'hasSupabaseConfig' => $hasSupabaseConfig,
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $showAppView ? 'AiScaler Center - Panel de control' : ($showLoginView ? 'AiScaler Center - Acceso' : 'AiScaler Center - Transforma tu Futuro Profesional'); ?></title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;800&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        body { font-family: 'Open Sans', sans-serif; }
        h1, h2, h3, h4, .btn-font { font-family: 'Montserrat', sans-serif; }

        .bg-brand-blue { background-color: #2F7CEF; }
        .text-brand-blue { color: #2F7CEF; }
        .bg-brand-dark { background-color: #0F172A; }
        .bg-brand-amber { background-color: #FBBF24; }

        .hero-bg {
            background-image: linear-gradient(rgba(15, 23, 42, 0.85), rgba(15, 23, 42, 0.8)), url('https://images.unsplash.com/photo-1519389950473-47ba0277781c?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80');
            background-size: cover;
            background-position: center;
        }

        .platform-shell {
            background:
                radial-gradient(circle at top left, rgba(47, 124, 239, 0.3), transparent 30%),
                radial-gradient(circle at bottom right, rgba(14, 165, 233, 0.18), transparent 26%),
                #020617;
        }

        .platform-panel {
            background: rgba(15, 23, 42, 0.72);
            backdrop-filter: blur(18px);
        }

        .platform-grid {
            background:
                linear-gradient(rgba(15, 23, 42, 0.82), rgba(15, 23, 42, 0.92)),
                linear-gradient(90deg, rgba(148, 163, 184, 0.08) 1px, transparent 1px),
                linear-gradient(rgba(148, 163, 184, 0.08) 1px, transparent 1px);
            background-size: auto, 40px 40px, 40px 40px;
        }

        html { scroll-behavior: smooth; }
    </style>

    <?php if ($showLoginView || $showAppView): ?>
        <script>
            window.AISCALER_AUTH_CONFIG = <?= json_encode($authClientConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        </script>
        <script type="module" src="js/supabase-auth.js"></script>
    <?php endif; ?>
</head>
<?php if ($showAppView): ?>
<body data-view="app" class="bg-slate-950 text-slate-100 antialiased">
    <div class="min-h-screen platform-shell">
        <header class="border-b border-white/10 bg-slate-950/80 backdrop-blur-xl">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex min-h-[5.5rem] items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/10">
                            <img class="h-9 w-auto" src="img/logoAiScalerCenter.png" alt="AiScaler Center Logo">
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-[0.35em] text-sky-300/80">AiScaler Center</p>
                            <h1 class="text-2xl font-extrabold text-white">Panel de control</h1>
                            <p id="app-user-email" class="mt-1 text-sm text-slate-400">Validando sesion...</p>
                        </div>
                    </div>

                    <button id="logout-button" type="button" class="btn-font shrink-0 rounded-full border border-white/15 bg-white/10 px-5 py-2.5 text-sm font-bold text-white transition duration-300 hover:bg-white/20">
                        Salir
                    </button>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 py-10 sm:px-6 lg:px-8">
            <div id="app-notice" class="hidden mb-6 rounded-2xl px-4 py-3 text-sm font-semibold"></div>

            <div id="app-loading" class="platform-panel flex min-h-[420px] items-center justify-center rounded-3xl border border-white/10 p-10 text-center">
                <div>
                    <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-sky-400/10 text-sky-300">
                        <i class="fas fa-circle-notch animate-spin text-2xl"></i>
                    </div>
                    <h2 class="text-3xl font-extrabold text-white">Preparando tu panel</h2>
                    <p class="mt-3 text-sm leading-7 text-slate-300">
                        Estamos validando tu sesion con Supabase para llevarte directo a tu espacio privado.
                    </p>
                </div>
            </div>

            <section id="app-shell" class="hidden grid gap-6 lg:grid-cols-[320px,minmax(0,1fr)]">
                <aside class="platform-panel rounded-3xl border border-white/10 p-6">
                    <p class="text-sm uppercase tracking-[0.3em] text-slate-400">Cuenta</p>
                    <h2 id="app-user-name" class="mt-4 text-3xl font-extrabold text-white">Tu espacio</h2>
                    <p id="app-verification-copy" class="mt-4 text-sm leading-7 text-slate-300">
                        Cargando informacion de tu cuenta...
                    </p>

                    <div class="mt-8 grid gap-3">
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Estado</p>
                            <p id="app-status-badge" class="mt-2 text-sm font-semibold text-sky-200">Pendiente</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Metodo</p>
                            <p id="app-provider" class="mt-2 text-sm font-semibold text-slate-200">email</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Ultimo acceso</p>
                            <p id="app-last-sign-in" class="mt-2 text-sm font-semibold text-slate-200">--</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">User ID</p>
                            <p id="app-user-id" class="mt-2 break-all text-sm font-semibold text-slate-200">--</p>
                        </div>
                    </div>

                    <form id="change-password-form" class="mt-8 space-y-4">
                        <div>
                            <label for="app-new-password" class="mb-2 block text-sm font-semibold text-slate-200">Cambiar contrasena</label>
                            <input
                                id="app-new-password"
                                name="password"
                                type="password"
                                minlength="8"
                                required
                                class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition duration-300 placeholder:text-slate-500 focus:border-sky-400 focus:bg-white/10"
                                placeholder="Minimo 8 caracteres"
                            >
                        </div>

                        <div>
                            <label for="app-confirm-password" class="mb-2 block text-sm font-semibold text-slate-200">Confirmar contrasena</label>
                            <input
                                id="app-confirm-password"
                                name="password_confirm"
                                type="password"
                                minlength="8"
                                required
                                class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition duration-300 placeholder:text-slate-500 focus:border-sky-400 focus:bg-white/10"
                                placeholder="Repite la contrasena"
                            >
                        </div>

                        <button type="submit" class="btn-font w-full rounded-2xl bg-brand-blue px-6 py-3.5 text-sm font-bold text-white transition duration-300 hover:bg-blue-700">
                            Actualizar contrasena
                        </button>
                    </form>
                </aside>

                <div class="platform-grid min-h-[520px] rounded-3xl border border-white/10 p-6 sm:p-8">
                    <div class="flex h-full flex-col rounded-[1.75rem] border border-dashed border-sky-400/25 bg-slate-950/50 p-6 sm:p-10">
                        <div class="max-w-3xl">
                            <span class="inline-flex rounded-full border border-sky-400/30 bg-sky-400/10 px-4 py-1 text-sm font-semibold text-sky-200">
                                Panel inicial
                            </span>
                            <h2 class="mt-6 text-4xl font-extrabold text-white sm:text-5xl">Ya entraste como debe ser.</h2>
                            <p class="mt-4 text-base leading-8 text-slate-300">
                                El acceso ahora corre con Supabase Auth real: registro, confirmacion por correo, login con contrasena, magic link, recuperacion de acceso y sesion persistente.
                            </p>
                        </div>

                        <div class="mt-10 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                                <p class="text-sm font-semibold text-sky-200">Acceso persistente</p>
                                <p class="mt-3 text-sm leading-7 text-slate-300">
                                    Si la sesion sigue activa, el sistema te devuelve al panel sin pedirte pasos extra.
                                </p>
                            </div>
                            <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                                <p class="text-sm font-semibold text-sky-200">Autogestion de cuenta</p>
                                <p class="mt-3 text-sm leading-7 text-slate-300">
                                    Desde aqui puedes cambiar tu contrasena y ver el estado real de tu correo y tu ultimo acceso.
                                </p>
                            </div>
                            <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                                <p class="text-sm font-semibold text-sky-200">Base lista para crecer</p>
                                <p class="mt-3 text-sm leading-7 text-slate-300">
                                    El siguiente paso natural ya es conectar tablas, permisos y herramientas de IA al usuario autenticado.
                                </p>
                            </div>
                        </div>

                        <div class="mt-auto pt-12">
                            <div class="rounded-3xl border border-white/10 bg-slate-950/40 p-6">
                                <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Proxima iteracion</p>
                                <h3 class="mt-3 text-2xl font-extrabold text-white">Tus modulos de IA iran aqui.</h3>
                                <p class="mt-3 text-sm leading-7 text-slate-300">
                                    Dejamos el panel despejado y con la base de autenticacion terminada para que ahora podamos conectar herramientas, historial, creditos, billing y roles sin rehacer el acceso.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</body>
<?php elseif ($showLoginView): ?>
<body data-view="login" class="bg-slate-950 text-slate-100 antialiased">
    <div class="min-h-screen hero-bg">
        <nav class="bg-slate-950/55 backdrop-blur-md">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-20 items-center">
                    <div class="flex-shrink-0 flex items-center">
                        <img class="h-12 w-auto" src="img/logoAiScalerCenter.png" alt="AiScaler Center Logo">
                    </div>

                    <a href="<?= htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn-font rounded-full border border-white/20 bg-white/10 px-5 py-2.5 text-sm font-bold text-white transition duration-300 hover:bg-white/20">
                        Volver
                    </a>
                </div>
            </div>
        </nav>

        <main class="px-4 py-10 sm:px-6 lg:px-8">
            <div class="mx-auto grid max-w-6xl gap-8 pt-10 lg:grid-cols-[1.1fr,0.9fr] lg:items-center lg:pt-16">
                <section class="max-w-2xl">
                    <p class="text-sm font-bold uppercase tracking-[0.35em] text-sky-300">Acceso privado</p>
                    <h1 class="mt-6 text-4xl font-extrabold text-white md:text-6xl leading-tight">
                        Entra de la forma mas simple y comoda posible.
                    </h1>
                    <p class="mt-6 max-w-xl text-lg leading-8 text-slate-200">
                        Esta pantalla ya usa Supabase Auth real para que el usuario pueda crear cuenta, entrar con contrasena, recibir un magic link, recuperar acceso y volver directo al panel.
                    </p>

                    <div class="mt-10 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-3xl border border-white/10 bg-white/10 p-5 backdrop-blur-sm">
                            <p class="text-sm font-semibold text-sky-200">Inicio de sesion real</p>
                            <p class="mt-2 text-sm leading-7 text-slate-300">El acceso ya no depende de una sesion temporal del sitio, sino de la identidad real del usuario en Supabase.</p>
                        </div>
                        <div class="rounded-3xl border border-white/10 bg-white/10 p-5 backdrop-blur-sm">
                            <p class="text-sm font-semibold text-sky-200">UX preparada</p>
                            <p class="mt-2 text-sm leading-7 text-slate-300">Confirmacion por correo, reenvio de email, recuperacion y sesion persistente ya forman parte del flujo.</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-[2rem] border border-white/10 bg-slate-950/75 p-6 shadow-2xl shadow-slate-950/50 backdrop-blur-xl sm:p-8">
                    <div id="auth-notice" class="hidden mb-6 rounded-2xl px-4 py-3 text-sm font-semibold"></div>

                    <div class="mb-8">
                        <p class="text-sm uppercase tracking-[0.3em] text-slate-400">Supabase Auth</p>
                        <h2 class="mt-3 text-3xl font-extrabold text-white">Bienvenido a AiScaler Center</h2>
                        <p id="auth-settings-hint" class="mt-3 text-sm leading-7 text-slate-300">
                            El sistema detectara si necesitas confirmar tu correo antes de entrar.
                        </p>
                    </div>

                    <div class="grid grid-cols-3 gap-2 rounded-2xl border border-white/10 bg-white/5 p-2">
                        <button type="button" data-auth-target="signin" data-auth-tab class="btn-font rounded-2xl px-3 py-3 text-sm font-bold text-white transition duration-300 hover:bg-white/10">
                            Entrar
                        </button>
                        <button type="button" data-auth-target="signup" data-auth-tab class="btn-font rounded-2xl px-3 py-3 text-sm font-bold text-white transition duration-300 hover:bg-white/10">
                            Crear cuenta
                        </button>
                        <button type="button" data-auth-target="magic" data-auth-tab class="btn-font rounded-2xl px-3 py-3 text-sm font-bold text-white transition duration-300 hover:bg-white/10">
                            Magic link
                        </button>
                    </div>

                    <div id="oauth-section" class="hidden mt-6">
                        <p class="mb-3 text-xs uppercase tracking-[0.25em] text-slate-500">Otros accesos habilitados</p>
                        <div id="oauth-provider-list" class="grid gap-3"></div>
                    </div>

                    <div class="mt-6 space-y-6">
                        <div data-auth-panel="signin">
                            <h3 class="text-2xl font-extrabold text-white">Entrar con correo y contrasena</h3>
                            <p class="mt-2 text-sm leading-7 text-slate-300">
                                Si ya tienes cuenta, entra directo al panel con tu correo confirmado.
                            </p>

                            <form id="signin-form" class="mt-6 space-y-5">
                                <div>
                                    <label for="signin-email" class="mb-2 block text-sm font-semibold text-slate-200">Correo electronico</label>
                                    <input
                                        id="signin-email"
                                        name="email"
                                        type="email"
                                        required
                                        class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition duration-300 placeholder:text-slate-500 focus:border-sky-400 focus:bg-white/10"
                                        placeholder="tu@empresa.com"
                                    >
                                </div>

                                <div>
                                    <label for="signin-password" class="mb-2 block text-sm font-semibold text-slate-200">Contrasena</label>
                                    <input
                                        id="signin-password"
                                        name="password"
                                        type="password"
                                        minlength="8"
                                        required
                                        class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition duration-300 placeholder:text-slate-500 focus:border-sky-400 focus:bg-white/10"
                                        placeholder="********"
                                    >
                                </div>

                                <button type="submit" class="btn-font w-full rounded-2xl bg-brand-blue px-6 py-3.5 text-base font-bold text-white transition duration-300 hover:bg-blue-700">
                                    Entrar al panel
                                </button>
                            </form>

                            <div class="mt-6 flex flex-wrap gap-3 text-sm">
                                <button type="button" data-auth-target="forgot" class="font-semibold text-sky-300 transition duration-300 hover:text-sky-200">
                                    Olvide mi contrasena
                                </button>
                                <button type="button" data-auth-target="resend" class="font-semibold text-slate-300 transition duration-300 hover:text-white">
                                    Reenviar confirmacion
                                </button>
                            </div>
                        </div>

                        <div data-auth-panel="signup" class="hidden">
                            <h3 class="text-2xl font-extrabold text-white">Crear cuenta nueva</h3>
                            <p class="mt-2 text-sm leading-7 text-slate-300">
                                Registra al usuario y deja listo el correo de confirmacion para su primer acceso.
                            </p>

                            <form id="signup-form" class="mt-6 space-y-5">
                                <div>
                                    <label for="signup-name" class="mb-2 block text-sm font-semibold text-slate-200">Nombre</label>
                                    <input
                                        id="signup-name"
                                        name="full_name"
                                        type="text"
                                        class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition duration-300 placeholder:text-slate-500 focus:border-sky-400 focus:bg-white/10"
                                        placeholder="Tu nombre"
                                    >
                                </div>

                                <div>
                                    <label for="signup-email" class="mb-2 block text-sm font-semibold text-slate-200">Correo electronico</label>
                                    <input
                                        id="signup-email"
                                        name="email"
                                        type="email"
                                        required
                                        class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition duration-300 placeholder:text-slate-500 focus:border-sky-400 focus:bg-white/10"
                                        placeholder="tu@empresa.com"
                                    >
                                </div>

                                <div>
                                    <label for="signup-password" class="mb-2 block text-sm font-semibold text-slate-200">Contrasena</label>
                                    <input
                                        id="signup-password"
                                        name="password"
                                        type="password"
                                        minlength="8"
                                        required
                                        class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition duration-300 placeholder:text-slate-500 focus:border-sky-400 focus:bg-white/10"
                                        placeholder="Minimo 8 caracteres"
                                    >
                                </div>

                                <button type="submit" class="btn-font w-full rounded-2xl bg-brand-blue px-6 py-3.5 text-base font-bold text-white transition duration-300 hover:bg-blue-700">
                                    Crear cuenta
                                </button>
                            </form>

                            <div class="mt-6 text-sm">
                                <button type="button" data-auth-target="signin" class="font-semibold text-sky-300 transition duration-300 hover:text-sky-200">
                                    Ya tengo cuenta
                                </button>
                            </div>
                        </div>

                        <div data-auth-panel="magic" class="hidden">
                            <h3 class="text-2xl font-extrabold text-white">Entrar sin contrasena</h3>
                            <p class="mt-2 text-sm leading-7 text-slate-300">
                                Supabase puede enviarte un enlace magico para entrar con un solo clic desde tu correo.
                            </p>

                            <form id="magic-form" class="mt-6 space-y-5">
                                <div>
                                    <label for="magic-email" class="mb-2 block text-sm font-semibold text-slate-200">Correo electronico</label>
                                    <input
                                        id="magic-email"
                                        name="email"
                                        type="email"
                                        required
                                        class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition duration-300 placeholder:text-slate-500 focus:border-sky-400 focus:bg-white/10"
                                        placeholder="tu@empresa.com"
                                    >
                                </div>

                                <button type="submit" class="btn-font w-full rounded-2xl bg-brand-blue px-6 py-3.5 text-base font-bold text-white transition duration-300 hover:bg-blue-700">
                                    Enviar magic link
                                </button>
                            </form>

                            <div class="mt-6 text-sm">
                                <button type="button" data-auth-target="signin" class="font-semibold text-sky-300 transition duration-300 hover:text-sky-200">
                                    Prefiero usar contrasena
                                </button>
                            </div>
                        </div>

                        <div data-auth-panel="forgot" class="hidden">
                            <h3 class="text-2xl font-extrabold text-white">Recuperar contrasena</h3>
                            <p class="mt-2 text-sm leading-7 text-slate-300">
                                Te enviaremos un enlace para crear una nueva contrasena sin salir del flujo de acceso.
                            </p>

                            <form id="forgot-form" class="mt-6 space-y-5">
                                <div>
                                    <label for="forgot-email" class="mb-2 block text-sm font-semibold text-slate-200">Correo electronico</label>
                                    <input
                                        id="forgot-email"
                                        name="email"
                                        type="email"
                                        required
                                        class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition duration-300 placeholder:text-slate-500 focus:border-sky-400 focus:bg-white/10"
                                        placeholder="tu@empresa.com"
                                    >
                                </div>

                                <button type="submit" class="btn-font w-full rounded-2xl bg-brand-blue px-6 py-3.5 text-base font-bold text-white transition duration-300 hover:bg-blue-700">
                                    Enviar enlace de recuperacion
                                </button>
                            </form>

                            <div class="mt-6 text-sm">
                                <button type="button" data-auth-target="signin" class="font-semibold text-sky-300 transition duration-300 hover:text-sky-200">
                                    Volver a entrar
                                </button>
                            </div>
                        </div>

                        <div data-auth-panel="resend" class="hidden">
                            <h3 class="text-2xl font-extrabold text-white">Reenviar confirmacion</h3>
                            <p class="mt-2 text-sm leading-7 text-slate-300">
                                Si el usuario no encontro el correo de confirmacion, puedes reenviarlo desde aqui.
                            </p>

                            <form id="resend-form" class="mt-6 space-y-5">
                                <div>
                                    <label for="resend-email" class="mb-2 block text-sm font-semibold text-slate-200">Correo electronico</label>
                                    <input
                                        id="resend-email"
                                        name="email"
                                        type="email"
                                        required
                                        class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition duration-300 placeholder:text-slate-500 focus:border-sky-400 focus:bg-white/10"
                                        placeholder="tu@empresa.com"
                                    >
                                </div>

                                <button type="submit" class="btn-font w-full rounded-2xl bg-brand-blue px-6 py-3.5 text-base font-bold text-white transition duration-300 hover:bg-blue-700">
                                    Reenviar correo
                                </button>
                            </form>

                            <div class="mt-6 text-sm">
                                <button type="button" data-auth-target="signin" class="font-semibold text-sky-300 transition duration-300 hover:text-sky-200">
                                    Volver a entrar
                                </button>
                            </div>
                        </div>

                        <div data-auth-panel="reset" class="hidden">
                            <h3 class="text-2xl font-extrabold text-white">Crear nueva contrasena</h3>
                            <p class="mt-2 text-sm leading-7 text-slate-300">
                                Estas dentro del flujo de recuperacion. Define una nueva contrasena para continuar al panel.
                            </p>

                            <form id="reset-form" class="mt-6 space-y-5">
                                <div>
                                    <label for="reset-password" class="mb-2 block text-sm font-semibold text-slate-200">Nueva contrasena</label>
                                    <input
                                        id="reset-password"
                                        name="password"
                                        type="password"
                                        minlength="8"
                                        required
                                        class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition duration-300 placeholder:text-slate-500 focus:border-sky-400 focus:bg-white/10"
                                        placeholder="Minimo 8 caracteres"
                                    >
                                </div>

                                <div>
                                    <label for="reset-password-confirm" class="mb-2 block text-sm font-semibold text-slate-200">Confirmar contrasena</label>
                                    <input
                                        id="reset-password-confirm"
                                        name="password_confirm"
                                        type="password"
                                        minlength="8"
                                        required
                                        class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition duration-300 placeholder:text-slate-500 focus:border-sky-400 focus:bg-white/10"
                                        placeholder="Repite la contrasena"
                                    >
                                </div>

                                <button type="submit" class="btn-font w-full rounded-2xl bg-brand-blue px-6 py-3.5 text-base font-bold text-white transition duration-300 hover:bg-blue-700">
                                    Guardar nueva contrasena
                                </button>
                            </form>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>
</body>
<?php else: ?>
<body class="bg-gray-50 text-gray-800 antialiased">
    <nav class="bg-white shadow-md fixed w-full z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20">
                <div class="flex-shrink-0 flex items-center">
                    <img class="h-12 w-auto" src="img/logoAiScalerCenter.png" alt="AiScaler Center Logo">
                </div>

                <div class="flex items-center gap-3">
                    <a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn-font rounded-full bg-brand-dark px-5 py-2.5 text-sm font-bold text-white transition duration-300 hover:bg-slate-800">
                        Iniciar sesion
                    </a>

                    <a href="#registro" class="hidden rounded-full bg-brand-blue px-6 py-2.5 text-sm font-bold text-white transition duration-300 hover:bg-blue-700 md:block btn-font">
                        Reservar mi Lugar
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <header class="hero-bg flex h-screen items-center justify-center px-4 pt-20 text-center">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-4xl md:text-6xl font-extrabold text-white mb-6 leading-tight">
                No dejes que la IA te reemplace.<br>
                <span class="text-brand-blue bg-white px-2 rounded">Conviértete en quien la domina.</span>
            </h1>
            <p class="text-xl text-gray-200 mb-10 max-w-2xl mx-auto">
                El mundo no necesita más "usuarios" de ChatGPT. Las empresas buscan desesperadamente <strong>AiScalers</strong>: estrategas capaces de escalar negocios usando Inteligencia Artificial.
            </p>

            <a href="#registro" class="inline-block bg-brand-amber text-gray-900 font-extrabold text-xl py-4 px-10 rounded-lg shadow-lg hover:bg-yellow-500 transform hover:scale-105 transition duration-300 btn-font">
                QUIERO SER UN AISCALER
            </a>

            <p class="mt-4 text-sm text-gray-400">Entrenamiento Exclusivo | Plazas Limitadas</p>
        </div>
    </header>

    <section class="py-20 bg-white">
        <div class="max-w-5xl mx-auto px-4 text-center">
            <h2 class="text-3xl font-bold text-gray-900 mb-8">La dura realidad del mercado actual</h2>
            <div class="grid md:grid-cols-3 gap-8">
                <div class="p-6 bg-gray-50 rounded-xl shadow-sm hover:shadow-md transition">
                    <i class="fas fa-robot text-5xl text-brand-blue mb-4"></i>
                    <h3 class="text-xl font-bold mb-2">Automatización Masiva</h3>
                    <p class="text-gray-600">Las tareas repetitivas están desapareciendo. Si tu trabajo es operativo, estás en riesgo.</p>
                </div>
                <div class="p-6 bg-gray-50 rounded-xl shadow-sm hover:shadow-md transition">
                    <i class="fas fa-chart-line text-5xl text-red-500 mb-4"></i>
                    <h3 class="text-xl font-bold mb-2">Brecha de Habilidades</h3>
                    <p class="text-gray-600">Las empresas tienen la tecnología pero no saben cómo implementarla para crecer.</p>
                </div>
                <div class="p-6 bg-gray-50 rounded-xl shadow-sm hover:shadow-md transition">
                    <i class="fas fa-user-slash text-5xl text-gray-400 mb-4"></i>
                    <h3 class="text-xl font-bold mb-2">Irrelevancia</h3>
                    <p class="text-gray-600">Quienes no se adapten hoy, serán invisibles para el mercado laboral mañana.</p>
                </div>
            </div>
            <div class="mt-12">
                <a href="#registro" class="text-brand-blue font-bold text-lg hover:underline underline-offset-4">
                    Prefiero tomar el control de mi futuro &rarr;
                </a>
            </div>
        </div>
    </section>

    <section class="py-20 bg-gray-900 text-white">
        <div class="max-w-6xl mx-auto px-4">
            <div class="flex flex-col md:flex-row items-center">
                <div class="md:w-1/2 mb-10 md:mb-0 pr-0 md:pr-10">
                    <h2 class="text-3xl md:text-4xl font-bold mb-6">¿Qué es un <span class="text-brand-blue">AiScaler</span>?</h2>
                    <p class="text-lg text-gray-300 mb-6">
                        Un AiScaler no es un programador. Es un arquitecto de crecimiento. Es el profesional que entiende cómo orquestar la tecnología para multiplicar resultados.
                    </p>
                    <p class="text-lg text-gray-300 mb-8">
                        A través de nuestra metodología <strong>I.D.E.A.</strong> (Idea, Diseño, Ejecución, Automatización), aprenderás a crear sistemas que trabajan solos, volviéndote el activo más valioso de cualquier compañía.
                    </p>
                    <ul class="space-y-4 mb-8">
                        <li class="flex items-center"><i class="fas fa-check-circle text-green-500 mr-3"></i> Domina la IA Estratégica</li>
                        <li class="flex items-center"><i class="fas fa-check-circle text-green-500 mr-3"></i> Escala operaciones empresariales</li>
                        <li class="flex items-center"><i class="fas fa-check-circle text-green-500 mr-3"></i> Asegura tu relevancia profesional</li>
                    </ul>
                    <a href="#registro" class="inline-block bg-white text-gray-900 font-bold py-3 px-8 rounded hover:bg-gray-100 transition">
                        Aplicar al Programa
                    </a>
                </div>
                <div class="md:w-1/2 flex justify-center">
                    <div class="grid grid-cols-2 gap-4 w-full max-w-sm">
                        <div class="bg-blue-600 h-32 rounded-lg flex items-center justify-center text-2xl font-bold shadow-lg shadow-blue-500/50">I</div>
                        <div class="bg-red-500 h-32 rounded-lg flex items-center justify-center text-2xl font-bold shadow-lg shadow-red-500/50">D</div>
                        <div class="bg-yellow-400 h-32 rounded-lg flex items-center justify-center text-2xl font-bold text-black shadow-lg shadow-yellow-400/50">E</div>
                        <div class="bg-green-600 h-32 rounded-lg flex items-center justify-center text-2xl font-bold shadow-lg shadow-green-500/50">A</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-20 bg-white relative overflow-hidden">
        <div class="absolute top-0 right-0 -mr-20 -mt-20 opacity-5">
            <i class="fas fa-arrow-up text-9xl"></i>
        </div>

        <div class="max-w-4xl mx-auto px-4 text-center">
            <span class="bg-blue-100 text-brand-blue px-4 py-1 rounded-full font-bold text-sm tracking-wide uppercase">Beneficio Exclusivo</span>
            <h2 class="text-4xl font-bold text-gray-900 mt-6 mb-6">El Directorio Oficial AiScaler</h2>
            <p class="text-xl text-gray-600 mb-10">
                Al graduarte, no te damos solo un diploma. Te damos visibilidad.
                <br><br>
                Las empresas ya no buscan currículums, buscan resultados. Al completar tu formación, ingresarás a nuestro <strong>Directorio Certificado</strong>, donde las compañías que necesitan escalar buscan talento calificado.
            </p>

            <div class="bg-gray-50 border-2 border-dashed border-gray-300 rounded-xl p-8 max-w-2xl mx-auto mb-10">
                <div class="flex items-center justify-center mb-4">
                    <i class="fas fa-search text-3xl text-gray-400 mr-4"></i>
                    <span class="text-2xl font-bold text-gray-700">Tu Perfil Profesional</span>
                </div>
                <p class="text-gray-500 italic">"Deja que las oportunidades te encuentren a ti, en lugar de tú perseguirlas."</p>
            </div>

            <a href="#registro" class="bg-brand-blue text-white font-extrabold text-xl py-4 px-12 rounded-lg shadow-xl hover:bg-blue-800 transition duration-300 btn-font">
                QUIERO ESTAR EN EL DIRECTORIO
            </a>
        </div>
    </section>

    <section id="registro" class="py-24 bg-gray-100">
        <div class="max-w-3xl mx-auto px-4">
            <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
                <div class="bg-gray-900 py-6 px-8 text-center">
                    <h2 class="text-2xl md:text-3xl font-bold text-white">Da el primer paso ahora</h2>
                    <p class="text-gray-400 mt-2">Regístrate para recibir el acceso a la formación en tu correo y WhatsApp.</p>
                </div>

                <div class="p-8 md:p-12">
                    <div class="hubspot-container">
                        <script src="https://js.hsforms.net/forms/embed/50539613.js" defer></script>
                        <div class="hs-form-frame" data-region="na1" data-form-id="f1c8fbc5-56cb-4a92-b1f0-152bd49cb06a" data-portal-id="50539613"></div>
                    </div>
                    <div class="mt-6 text-center">
                        <p class="text-xs text-gray-500">
                            <i class="fas fa-lock mr-1"></i> Tus datos están 100% seguros. No hacemos spam.
                            Al registrarte, recibirás el enlace para unirte a la revolución AiScaler.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-white border-t border-gray-200 pt-12 pb-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col items-center">
            <img class="h-10 w-auto mb-4 opacity-80" src="img/logoAiScalerCenter.png" alt="AiScaler Logo Footer">
            <p class="text-gray-500 text-sm text-center mb-4">
                © 2026 AiScaler Center. Todos los derechos reservados.<br>
                La metodología oficial para escalar negocios con Inteligencia Artificial.
            </p>
            <div class="flex space-x-6">
                <a href="#" class="text-gray-400 hover:text-gray-500"><i class="fab fa-linkedin text-xl"></i></a>
                <a href="#" class="text-gray-400 hover:text-gray-500"><i class="fab fa-instagram text-xl"></i></a>
                <a href="#" class="text-gray-400 hover:text-gray-500"><i class="fab fa-twitter text-xl"></i></a>
            </div>
        </div>
    </footer>
</body>
<?php endif; ?>
</html>
