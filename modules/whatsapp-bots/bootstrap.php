<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/supabase_api.php';
require_once __DIR__ . '/../../lib/app_storage.php';
require_once __DIR__ . '/WhatsAppBotRepository.php';

function normalizeWhatsAppBotException(Throwable $exception): string
{
    $message = $exception->getMessage();
    $normalized = strtolower($message);

    if (
        str_contains($normalized, 'whatsapp_bot_')
        || str_contains($normalized, 'get_public_whatsapp_bot_context')
        || str_contains($normalized, 'create_project_customer_pipeline_lead')
        || str_contains($normalized, 'pgrst205')
        || str_contains($normalized, 'schema cache')
        || str_contains($normalized, 'does not exist')
    ) {
        return 'La estructura de Bots de WhatsApp aun no existe. Ejecuta supabase/whatsapp_bots_schema.sql en Supabase.';
    }

    if (str_contains($normalized, 'row-level security')) {
        return 'Supabase bloqueo la operacion por permisos. Revisa supabase/whatsapp_bots_schema.sql.';
    }

    return $message !== '' ? $message : 'Ocurrio un error inesperado en Bots de WhatsApp.';
}

function whatsappBotDefaultScheduleDefinition(): array
{
    return [
        'days' => [
            'monday' => ['enabled' => true, 'from' => '09:00', 'to' => '18:00'],
            'tuesday' => ['enabled' => true, 'from' => '09:00', 'to' => '18:00'],
            'wednesday' => ['enabled' => true, 'from' => '09:00', 'to' => '18:00'],
            'thursday' => ['enabled' => true, 'from' => '09:00', 'to' => '18:00'],
            'friday' => ['enabled' => true, 'from' => '09:00', 'to' => '18:00'],
            'saturday' => ['enabled' => false, 'from' => '10:00', 'to' => '14:00'],
            'sunday' => ['enabled' => false, 'from' => '10:00', 'to' => '14:00'],
        ],
    ];
}

function whatsappBotDefaultFlowDefinition(): array
{
    return [
        'main_buttons' => [
            [
                'id' => 'catalogo',
                'label' => 'Ver catalogo',
                'response_text' => 'Claro. Te comparto la informacion principal para que veas lo que ofrecemos.',
                'capture_sequence' => [],
                'human_on_complete' => false,
                'create_lead_on_complete' => false,
                'success_message' => '',
                'attachment' => null,
            ],
            [
                'id' => 'cotizar',
                'label' => 'Cotizar',
                'response_text' => 'Con gusto te ayudo con la cotizacion.',
                'capture_sequence' => ['name', 'requirement'],
                'human_on_complete' => true,
                'create_lead_on_complete' => true,
                'success_message' => 'Gracias. Ya tengo lo necesario para que un asesor continue contigo.',
                'attachment' => null,
            ],
            [
                'id' => 'soporte',
                'label' => 'Soporte',
                'response_text' => 'Te transfiero con el equipo de soporte.',
                'capture_sequence' => [],
                'human_on_complete' => true,
                'create_lead_on_complete' => false,
                'success_message' => '',
                'attachment' => null,
            ],
        ],
        'list_options' => [],
        'field_prompts' => [
            'name' => 'Antes de avanzar, me compartes tu nombre?',
            'email' => 'Perfecto. Cual es tu correo electronico?',
            'phone' => 'En que numero te podemos contactar?',
            'requirement' => 'Cuentame brevemente que necesitas.',
        ],
    ];
}

function whatsappBotDefaultRoutingDefinition(): array
{
    return [
        'campaign_triggers' => [],
        'follow_up_stage_key' => 'contactar-de-nuevo',
        'follow_up_template_id' => '',
    ];
}

function whatsappBotDefaultBotPayload(string $projectId, string $ownerUserId, string $projectName = ''): array
{
    $resolvedProjectName = trim($projectName) !== '' ? trim($projectName) : 'Proyecto';

    return [
        'project_id' => $projectId,
        'owner_user_id' => $ownerUserId,
        'name' => 'Bot de WhatsApp ' . $resolvedProjectName,
        'tone' => 'amigable',
        'welcome_message' => 'Hola, gracias por escribirnos. Estoy aqui para ayudarte a elegir la opcion correcta.',
        'handoff_message' => 'Te transfiero con un especialista en un momento.',
        'off_hours_message' => 'Nuestros asesores estan dormidos, pero te atenderan manana a primera hora.',
        'fallback_message' => 'No termine de entenderte. Elige una opcion y te acompano.',
        'unknown_attempt_limit' => 3,
        'timezone' => 'America/Mexico_City',
        'business_phone_label' => '',
        'provider_phone_number_id' => '',
        'provider_waba_id' => '',
        'flow_definition' => whatsappBotDefaultFlowDefinition(),
        'schedule_definition' => whatsappBotDefaultScheduleDefinition(),
        'routing_definition' => whatsappBotDefaultRoutingDefinition(),
        'status' => 'draft',
    ];
}

function whatsappBotJsonEncode(array $payload): string
{
    return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
}

function whatsappBotWebhookUrl(string $publicKey): string
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

    return $scheme . '://' . $host . $basePath . '/whatsapp-webhook.php?bot=' . rawurlencode($publicKey);
}

function whatsappBotNormalizeSchedule(mixed $value): array
{
    $default = whatsappBotDefaultScheduleDefinition();

    if (!is_array($value) || !is_array($value['days'] ?? null)) {
        return $default;
    }

    foreach ($default['days'] as $dayKey => $dayConfig) {
        $candidate = is_array($value['days'][$dayKey] ?? null) ? $value['days'][$dayKey] : [];
        $default['days'][$dayKey] = [
            'enabled' => (bool) ($candidate['enabled'] ?? $dayConfig['enabled']),
            'from' => whatsappBotNormalizeTimeString((string) ($candidate['from'] ?? $dayConfig['from']), $dayConfig['from']),
            'to' => whatsappBotNormalizeTimeString((string) ($candidate['to'] ?? $dayConfig['to']), $dayConfig['to']),
        ];
    }

    return $default;
}

function whatsappBotNormalizeTimeString(string $value, string $fallback): string
{
    return preg_match('/^\d{2}:\d{2}$/', trim($value)) === 1 ? trim($value) : $fallback;
}

function whatsappBotIsWithinBusinessHours(array $scheduleDefinition, string $timezone): bool
{
    $schedule = whatsappBotNormalizeSchedule($scheduleDefinition);

    try {
        $now = new DateTimeImmutable('now', new DateTimeZone($timezone !== '' ? $timezone : 'America/Mexico_City'));
    } catch (Throwable) {
        $now = new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City'));
    }

    $dayKey = strtolower($now->format('l'));
    $dayConfig = is_array($schedule['days'][$dayKey] ?? null) ? $schedule['days'][$dayKey] : null;

    if (!is_array($dayConfig) || !($dayConfig['enabled'] ?? false)) {
        return false;
    }

    $current = $now->format('H:i');

    return $current >= (string) ($dayConfig['from'] ?? '09:00') && $current <= (string) ($dayConfig['to'] ?? '18:00');
}

function whatsappBotSanitizePhone(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

function whatsappBotResolveFieldPrompt(array $flowDefinition, string $fieldKey): string
{
    $prompts = is_array($flowDefinition['field_prompts'] ?? null) ? $flowDefinition['field_prompts'] : [];
    $fallbacks = whatsappBotDefaultFlowDefinition()['field_prompts'];

    return trim((string) ($prompts[$fieldKey] ?? $fallbacks[$fieldKey] ?? 'Cuentame un poco mas.'));
}

function whatsappBotNormalizeAttachment(mixed $value): ?array
{
    if (!is_array($value)) {
        return null;
    }

    $url = trim((string) ($value['url'] ?? ''));
    $path = trim((string) ($value['path'] ?? ''));
    $mime = trim((string) ($value['mime_type'] ?? ''));
    $fileName = trim((string) ($value['file_name'] ?? ''));
    $kind = trim((string) ($value['kind'] ?? ''));

    if ($url === '' && $path === '') {
        return null;
    }

    return [
        'url' => $url,
        'path' => $path,
        'mime_type' => $mime,
        'file_name' => $fileName,
        'kind' => $kind,
    ];
}

function whatsappBotNowIso(): string
{
    return gmdate('c');
}
