<?php
declare(strict_types=1);

return [
    'providers' => [
        'facebook_page' => [
            'oauth' => [
                'auth_url' => 'https://www.facebook.com/dialog/oauth',
                'client_id' => 'tu_facebook_client_id',
                'client_secret' => 'tu_facebook_client_secret',
                'redirect_uri' => 'https://tu-dominio.com/api/connect.php?provider=facebook_page&callback=1',
            ],
        ],
        'facebook_profile' => [
            'oauth' => [
                'auth_url' => 'https://www.facebook.com/dialog/oauth',
                'client_id' => 'tu_facebook_client_id',
                'client_secret' => 'tu_facebook_client_secret',
                'redirect_uri' => 'https://tu-dominio.com/api/connect.php?provider=facebook_profile&callback=1',
            ],
        ],
        'youtube_channel' => [
            'oauth' => [
                'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'client_id' => 'tu_google_client_id',
                'client_secret' => 'tu_google_client_secret',
                'redirect_uri' => 'https://tu-dominio.com/api/connect.php?provider=youtube_channel&callback=1',
            ],
        ],
        'linkedin_profile' => [
            'oauth' => [
                'auth_url' => 'https://www.linkedin.com/oauth/v2/authorization',
                'client_id' => 'tu_linkedin_client_id',
                'client_secret' => 'tu_linkedin_client_secret',
                'redirect_uri' => 'https://tu-dominio.com/api/connect.php?provider=linkedin_profile&callback=1',
            ],
        ],
        'linkedin_company' => [
            'oauth' => [
                'auth_url' => 'https://www.linkedin.com/oauth/v2/authorization',
                'client_id' => 'tu_linkedin_client_id',
                'client_secret' => 'tu_linkedin_client_secret',
                'redirect_uri' => 'https://tu-dominio.com/api/connect.php?provider=linkedin_company&callback=1',
            ],
        ],
        'instagram' => [
            'oauth' => [
                'auth_url' => 'https://www.instagram.com/oauth/authorize',
                'client_id' => 'tu_instagram_client_id',
                'client_secret' => 'tu_instagram_client_secret',
                'redirect_uri' => 'https://tu-dominio.com/api/connect.php?provider=instagram&callback=1',
            ],
        ],
        'google_business_profile' => [
            'oauth' => [
                'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'client_id' => 'tu_google_client_id',
                'client_secret' => 'tu_google_client_secret',
                'redirect_uri' => 'https://tu-dominio.com/api/connect.php?provider=google_business_profile&callback=1',
            ],
        ],
    ],
];
