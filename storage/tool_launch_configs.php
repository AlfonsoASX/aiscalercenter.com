<?php
declare(strict_types=1);

return [
    'validar-mercado' => [
        'launch_mode' => 'panel_module',
        'panel_module_key' => 'research_market_signals',
        'app_folder' => 'internal/research',
        'entry_file' => 'index.php',
    ],
    'investigar-google' => [
        'launch_mode' => 'php_folder',
        'panel_module_key' => '',
        'app_folder' => 'apps/google',
        'entry_file' => 'index.php',
    ],
    'investigar-youtube' => [
        'launch_mode' => 'php_folder',
        'panel_module_key' => '',
        'app_folder' => 'apps/youtube',
        'entry_file' => 'index.php',
    ],
    'investigar-mercado-libre' => [
        'launch_mode' => 'php_folder',
        'panel_module_key' => '',
        'app_folder' => 'apps/mercado-libre',
        'entry_file' => 'index.php',
    ],
    'investigar-amazon' => [
        'launch_mode' => 'php_folder',
        'panel_module_key' => '',
        'app_folder' => 'apps/amazon',
        'entry_file' => 'index.php',
    ],
    'generador-formularios' => [
        'launch_mode' => 'php_folder',
        'panel_module_key' => '',
        'app_folder' => 'apps/form-generator',
        'entry_file' => 'index.php',
    ],
    'creador-landing-pages' => [
        'launch_mode' => 'php_folder',
        'panel_module_key' => '',
        'app_folder' => 'apps/landing-builder',
        'entry_file' => 'index.php',
    ],
    'planificar-publicaciones' => [
        'launch_mode' => 'panel_module',
        'panel_module_key' => 'social_post_scheduler',
        'app_folder' => 'internal/execute',
        'entry_file' => 'index.php',
    ],
    'seguimiento-clientes' => [
        'launch_mode' => 'php_folder',
        'panel_module_key' => '',
        'app_folder' => 'apps/customer-follow-up',
        'entry_file' => 'index.php',
    ],
    'creacion-bots-whatsapp' => [
        'launch_mode' => 'php_folder',
        'panel_module_key' => '',
        'app_folder' => 'apps/whatsapp-bots',
        'entry_file' => 'index.php',
    ],
];
