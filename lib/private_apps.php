<?php
declare(strict_types=1);

require_once __DIR__ . '/app_routing.php';
require_once __DIR__ . '/supabase_api.php';

function privateAppsRegistry(): array
{
    return [
        'formularios' => [
            'key' => 'formularios',
            'title' => 'Formularios',
            'route' => 'formularios',
            'tool_slug' => 'generador-formularios',
            'app_folder' => 'apps/form-generator',
            'mode' => 'php_folder',
            'workspace_label' => 'Workspace Formularios',
        ],
        'landings' => [
            'key' => 'landings',
            'title' => 'Landings',
            'route' => 'landings',
            'tool_slug' => 'creador-landing-pages',
            'app_folder' => 'apps/landing-builder',
            'mode' => 'php_folder',
            'workspace_label' => 'Workspace Landings',
        ],
        'tableros' => [
            'key' => 'tableros',
            'title' => 'Tableros',
            'route' => 'tableros',
            'tool_slug' => 'tableros-tareas',
            'app_folder' => 'apps/task-boards',
            'mode' => 'php_folder',
            'workspace_label' => 'Workspace Tableros',
        ],
        'seguimiento-clientes' => [
            'key' => 'seguimiento-clientes',
            'title' => 'Seguimiento de Clientes',
            'route' => 'seguimiento-clientes',
            'tool_slug' => 'seguimiento-clientes',
            'app_folder' => 'apps/customer-follow-up',
            'mode' => 'php_folder',
            'workspace_label' => 'Workspace Seguimiento',
        ],
        'bots-whatsapp' => [
            'key' => 'bots-whatsapp',
            'title' => 'Bots de WhatsApp',
            'route' => 'bots-whatsapp',
            'tool_slug' => 'creacion-bots-whatsapp',
            'app_folder' => 'apps/whatsapp-bots',
            'mode' => 'php_folder',
            'workspace_label' => 'Workspace WhatsApp',
        ],
        'planificador-publicaciones' => [
            'key' => 'planificador-publicaciones',
            'title' => 'Planificador de Publicaciones',
            'route' => 'planificador-publicaciones',
            'tool_slug' => 'planificar-publicaciones',
            'app_folder' => 'apps/social-post-scheduler',
            'mode' => 'panel_module',
            'workspace_label' => 'Workspace Publicaciones',
            'panel_module_key' => 'social_post_scheduler',
        ],
        'google' => [
            'key' => 'google',
            'title' => 'Google',
            'route' => 'google',
            'tool_slug' => 'investigar-google',
            'app_folder' => 'apps/google',
            'mode' => 'php_folder',
            'workspace_label' => 'Workspace Google',
        ],
        'youtube' => [
            'key' => 'youtube',
            'title' => 'YouTube',
            'route' => 'youtube',
            'tool_slug' => 'investigar-youtube',
            'app_folder' => 'apps/youtube',
            'mode' => 'php_folder',
            'workspace_label' => 'Workspace YouTube',
        ],
        'mercado-libre' => [
            'key' => 'mercado-libre',
            'title' => 'Mercado Libre',
            'route' => 'mercado-libre',
            'tool_slug' => 'investigar-mercado-libre',
            'app_folder' => 'apps/mercado-libre',
            'mode' => 'php_folder',
            'workspace_label' => 'Workspace Mercado Libre',
        ],
        'amazon' => [
            'key' => 'amazon',
            'title' => 'Amazon',
            'route' => 'amazon',
            'tool_slug' => 'investigar-amazon',
            'app_folder' => 'apps/amazon',
            'mode' => 'php_folder',
            'workspace_label' => 'Workspace Amazon',
        ],
        'ai-image-studio' => [
            'key' => 'ai-image-studio',
            'title' => 'AI Image Studio',
            'route' => 'ai-image-studio',
            'tool_slug' => 'crear-imagenes-ia',
            'app_folder' => 'apps/ai-image-studio',
            'mode' => 'php_folder',
            'workspace_label' => 'Workspace Imagenes',
        ],
        'semaforo-trafico' => [
            'key' => 'semaforo-trafico',
            'title' => 'Semaforo de Trafico',
            'route' => 'semaforo-trafico',
            'tool_slug' => 'semaforo-trafico',
            'app_folder' => 'apps/traffic-semaforo',
            'mode' => 'php_folder',
            'workspace_label' => 'Workspace Trafico',
        ],
        'termometro-cpl' => [
            'key' => 'termometro-cpl',
            'title' => 'Termometro de CPL',
            'route' => 'termometro-cpl',
            'tool_slug' => 'termometro-cpl',
            'app_folder' => 'apps/cpl-termometro',
            'mode' => 'php_folder',
            'workspace_label' => 'Workspace CPL',
        ],
        'auditor-campanas' => [
            'key' => 'auditor-campanas',
            'title' => 'Auditor de Campanas',
            'route' => 'auditor-campanas',
            'tool_slug' => 'auditor-salud-campanas',
            'app_folder' => 'apps/auditor-campanas',
            'mode' => 'php_folder',
            'workspace_label' => 'Workspace Auditor',
        ],
        'rastreador-inteligente' => [
            'key' => 'rastreador-inteligente',
            'title' => 'Rastreador Inteligente',
            'route' => 'rastreador-inteligente',
            'tool_slug' => 'rastreador-inteligente',
            'app_folder' => 'apps/rastreador-inteligente',
            'mode' => 'php_folder',
            'workspace_label' => 'Workspace Rastreador',
        ],
        'vision-rayos-x' => [
            'key' => 'vision-rayos-x',
            'title' => 'Vision Rayos X',
            'route' => 'vision-rayos-x',
            'tool_slug' => 'vision-rayos-x',
            'app_folder' => 'apps/vision-rayos-x',
            'mode' => 'php_folder',
            'workspace_label' => 'Workspace Rayos X',
        ],
        'conecta' => [
            'key' => 'conecta',
            'title' => 'Conecta',
            'route' => 'conecta',
            'tool_slug' => '',
            'app_folder' => '',
            'mode' => 'redirect_legacy',
            'legacy_section' => 'Conecta',
            'workspace_label' => 'Workspace Conecta',
        ],
    ];
}

function privateAppDefinitions(): array
{
    return array_values(privateAppsRegistry());
}

function findPrivateApp(string $key): ?array
{
    $registry = privateAppsRegistry();
    $candidate = trim($key);

    return is_array($registry[$candidate] ?? null) ? $registry[$candidate] : null;
}

function privateAppUrl(string $key, array $query = [], bool $absolute = false): string
{
    $app = findPrivateApp($key);

    if (!is_array($app)) {
        return appAccountUrl(null, $absolute);
    }

    if (appShouldUseScriptFallbackRoutes()) {
        $fallbackQuery = array_merge(['app' => $key], $query);

        return $absolute
            ? appAbsoluteUrl('/private-app.php', $fallbackQuery)
            : appPath('/private-app.php', $fallbackQuery);
    }

    return $absolute
        ? appAbsoluteUrl('/' . ltrim((string) ($app['route'] ?? $key), '/'), $query)
        : appPath('/' . ltrim((string) ($app['route'] ?? $key), '/'), $query);
}

function privateAppWorkspaceDisplayName(array $app): string
{
    return trim((string) ($app['workspace_label'] ?? 'Workspace')) ?: 'Workspace';
}

function privateAppListAccessibleProjects(string $accessToken): array
{
    $response = supabaseRestRequest(
        'GET',
        'projects?select=id,name,logo_url,owner_user_id&deleted_at=is.null&order=updated_at.desc',
        [],
        $accessToken
    );

    return is_array($response['data'] ?? null) ? $response['data'] : [];
}

function privateAppCreateDefaultProject(string $accessToken, array $app): array
{
    $response = supabaseRestRequest(
        'POST',
        'rpc/create_project',
        [
            'p_name' => privateAppWorkspaceDisplayName($app),
            'p_description' => 'Workspace personal para ' . (string) ($app['title'] ?? 'la app'),
        ],
        $accessToken
    );

    $rows = is_array($response['data'] ?? null) ? $response['data'] : [];
    $project = is_array($rows[0] ?? null) ? $rows[0] : null;

    if (!is_array($project)) {
        throw new RuntimeException('No fue posible crear el workspace personal de esta app.');
    }

    return $project;
}

function privateAppPersistWorkspaceSelection(string $accessToken, string $userId, string $appKey, string $projectId): void
{
    if ($userId === '' || $appKey === '' || $projectId === '') {
        return;
    }

    try {
        supabaseRestRequest(
            'POST',
            'account_app_workspaces',
            [
                'user_id' => $userId,
                'app_key' => $appKey,
                'project_id' => $projectId,
                'last_used_at' => gmdate('c'),
            ],
            $accessToken,
            ['Prefer: resolution=merge-duplicates,return=minimal']
        );
    } catch (Throwable) {
        // Transitional fallback: si aun no existe la tabla, seguimos con el primer workspace disponible.
    }
}

function privateAppResolveWorkspace(string $accessToken, array $user, array $app): array
{
    $userId = trim((string) ($user['id'] ?? ''));
    $appKey = trim((string) ($app['key'] ?? ''));

    try {
        $response = supabaseRestRequest(
            'GET',
            'account_app_workspaces?select=project_id,projects(id,name,logo_url,owner_user_id)&user_id=eq.'
            . rawurlencode($userId)
            . '&app_key=eq.'
            . rawurlencode($appKey)
            . '&order=last_used_at.desc&limit=1',
            [],
            $accessToken
        );

        $rows = is_array($response['data'] ?? null) ? $response['data'] : [];
        $selected = is_array($rows[0]['projects'] ?? null) ? $rows[0]['projects'] : null;

        if (is_array($selected) && trim((string) ($selected['id'] ?? '')) !== '') {
            return $selected;
        }
    } catch (Throwable) {
        // Fallback transicional si la tabla aun no existe.
    }

    $projects = privateAppListAccessibleProjects($accessToken);
    $resolved = is_array($projects[0] ?? null) ? $projects[0] : privateAppCreateDefaultProject($accessToken, $app);

    privateAppPersistWorkspaceSelection(
        $accessToken,
        $userId,
        $appKey,
        trim((string) ($resolved['id'] ?? ''))
    );

    return $resolved;
}
