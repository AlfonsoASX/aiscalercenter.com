<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/ai_images.php';

function aiImagesConfig(): array
{
    $config = require __DIR__ . '/../../config/ai_images.php';

    return is_array($config) ? $config : [];
}

function aiImagesProviderReady(): bool
{
    $config = aiImagesConfig();

    return filter_var($config['enabled'] ?? false, FILTER_VALIDATE_BOOL)
        && trim((string) ($config['provider'] ?? '')) !== ''
        && trim((string) ($config['provider'] ?? '')) !== 'disabled'
        && trim((string) ($config['api_key'] ?? '')) !== ''
        && trim((string) ($config['api_key'] ?? '')) !== 'completa_tu_api_key'
        && trim((string) ($config['endpoint'] ?? '')) !== '';
}

function normalizeAiImagesException(Throwable $exception): string
{
    $message = trim($exception->getMessage());

    if ($message !== '') {
        return $message;
    }

    return 'Ocurrio un error inesperado al preparar el generador de imagenes.';
}

function aiImagesStylePresets(): array
{
    return [
        ['key' => 'foto-producto', 'label' => 'Foto de producto', 'hint' => 'Ideal para e-commerce y catalogos.'],
        ['key' => 'editorial', 'label' => 'Editorial', 'hint' => 'Mas aspiracional y de marca.'],
        ['key' => 'ilustracion', 'label' => 'Ilustracion', 'hint' => 'Para conceptos, personajes o iconografia.'],
        ['key' => 'social-ads', 'label' => 'Ads para redes', 'hint' => 'Pensado para creativos de campana.'],
        ['key' => 'mockup', 'label' => 'Mockup', 'hint' => 'Escenas con pantallas o empaques.'],
    ];
}

function aiImagesAspectRatios(): array
{
    return [
        ['key' => '1:1', 'label' => 'Cuadrada'],
        ['key' => '4:5', 'label' => 'Vertical social'],
        ['key' => '16:9', 'label' => 'Horizontal'],
        ['key' => '9:16', 'label' => 'Historia / Reel'],
    ];
}

function aiImagesDefaultState(): array
{
    return [
        'prompt' => '',
        'style' => 'foto-producto',
        'aspect_ratio' => '1:1',
        'quantity' => 1,
        'brand_note' => '',
        'negative_prompt' => '',
        'results' => [],
    ];
}
