<?php
declare(strict_types=1);

return [
    'project' => [
        'url' => 'https://tu-project-ref.supabase.co',
        'publishable_key' => 'tu_publishable_key',
        'secret_key' => 'tu_secret_key_opcional_para_backend',
    ],
    'database' => [
        'host' => 'aws-0-tu-region.pooler.supabase.com',
        'port' => '5432',
        'name' => 'postgres',
        'user' => 'postgres.tu_project_ref',
        'password' => 'tu_db_password',
        'sslmode' => 'require',
    ],
];
