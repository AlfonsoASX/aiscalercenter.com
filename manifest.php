<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/pwa.php';

header('Content-Type: application/manifest+json; charset=UTF-8');

if (!appShouldEnablePwa()) {
    http_response_code(404);
    echo json_encode([
        'error' => 'PWA disponible solo en el subdominio principal.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$manifest = [
    'id' => appPanelUrl(),
    'name' => 'AiScaler Center',
    'short_name' => 'AiScaler',
    'description' => 'Panel y herramientas de AiScaler Center para operar proyectos y flujos de trabajo con IA desde cualquier dispositivo.',
    'start_url' => appPanelUrl(),
    'scope' => appHomeUrl(),
    'display' => 'standalone',
    'orientation' => 'any',
    'lang' => 'es-MX',
    'dir' => 'ltr',
    'theme_color' => '#2f7cef',
    'background_color' => '#f5f7fb',
    'categories' => ['business', 'productivity', 'education'],
    'icons' => [
        [
            'src' => pwaAssetUrl('img/pwa/icon-192.png'),
            'sizes' => '192x192',
            'type' => 'image/png',
        ],
        [
            'src' => pwaAssetUrl('img/pwa/icon-512.png'),
            'sizes' => '512x512',
            'type' => 'image/png',
        ],
        [
            'src' => pwaAssetUrl('img/pwa/apple-touch-icon.png'),
            'sizes' => '180x180',
            'type' => 'image/png',
        ],
    ],
    'shortcuts' => [
        [
            'name' => 'Panel',
            'short_name' => 'Panel',
            'url' => appPanelUrl(),
            'icons' => [
                [
                    'src' => pwaAssetUrl('img/pwa/icon-192.png'),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                ],
            ],
        ],
        [
            'name' => 'Acceso',
            'short_name' => 'Acceso',
            'url' => appLoginUrl(),
            'icons' => [
                [
                    'src' => pwaAssetUrl('img/pwa/icon-192.png'),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                ],
            ],
        ],
    ],
];

echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
