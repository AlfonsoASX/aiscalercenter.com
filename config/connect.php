<?php
declare(strict_types=1);

return [
    'providers' => [
        'facebook_page' => [
            'oauth' => [
                'auth_url' => 'https://www.facebook.com/dialog/oauth',
                'client_id' => 'tu_facebook_client_id',
                'client_secret' => 'tu_facebook_client_secret',
                'redirect_uri' => 'https://aiscalercenter.com/api/connect.php?provider=facebook_page&callback=1',
                'response_type' => 'code',
                'scope_separator' => ',',
                'scopes' => [
                    'public_profile',
                    'pages_show_list',
                    'pages_read_engagement',
                    'pages_manage_posts',
                ],
            ],
        ],
        'facebook_profile' => [
            'oauth' => [
                'auth_url' => 'https://www.facebook.com/dialog/oauth',
                'client_id' => 'tu_facebook_client_id',
                'client_secret' => 'tu_facebook_client_secret',
                'redirect_uri' => 'https://aiscalercenter.com/api/connect.php?provider=facebook_profile&callback=1',
                'response_type' => 'code',
                'scope_separator' => ',',
                'scopes' => [
                    'public_profile',
                    'email',
                ],
            ],
        ],
        'youtube_channel' => [
            'oauth' => [
                'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'client_id' => 'tu_google_client_id',
                'client_secret' => 'tu_google_client_secret',
                'redirect_uri' => 'https://aiscalercenter.com/api/connect.php?provider=youtube_channel&callback=1',
                'response_type' => 'code',
                'scope_separator' => ' ',
                'scopes' => [
                    'openid',
                    'email',
                    'profile',
                    'https://www.googleapis.com/auth/youtube.readonly',
                ],
                'query' => [
                    'access_type' => 'offline',
                    'include_granted_scopes' => 'true',
                    'prompt' => 'consent',
                ],
            ],
        ],
        'linkedin_profile' => [
            'oauth' => [
                'auth_url' => 'https://www.linkedin.com/oauth/v2/authorization',
                'client_id' => 'tu_linkedin_client_id',
                'client_secret' => 'tu_linkedin_client_secret',
                'redirect_uri' => 'https://aiscalercenter.com/api/connect.php?provider=linkedin_profile&callback=1',
                'response_type' => 'code',
                'scope_separator' => ' ',
                'scopes' => [
                    'openid',
                    'profile',
                    'email',
                ],
            ],
        ],
        'linkedin_company' => [
            'oauth' => [
                'auth_url' => 'https://www.linkedin.com/oauth/v2/authorization',
                'client_id' => 'tu_linkedin_client_id',
                'client_secret' => 'tu_linkedin_client_secret',
                'redirect_uri' => 'https://aiscalercenter.com/api/connect.php?provider=linkedin_company&callback=1',
                'response_type' => 'code',
                'scope_separator' => ' ',
                'scopes' => [
                    'openid',
                    'profile',
                    'email',
                    'w_organization_social',
                    'r_organization_social',
                ],
            ],
        ],
        'instagram' => [
            'oauth' => [
                'auth_url' => 'https://www.instagram.com/oauth/authorize',
                'client_id' => 'tu_instagram_client_id',
                'client_secret' => 'tu_instagram_client_secret',
                'redirect_uri' => 'https://aiscalercenter.com/api/connect.php?provider=instagram&callback=1',
                'response_type' => 'code',
                'scope_separator' => ',',
                'scopes' => [
                    'instagram_basic',
                    'pages_show_list',
                ],
            ],
        ],
        'google_business_profile' => [
            'oauth' => [
                'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'client_id' => 'tu_google_client_id',
                'client_secret' => 'tu_google_client_secret',
                'redirect_uri' => 'https://aiscalercenter.com/api/connect.php?provider=google_business_profile&callback=1',
                'response_type' => 'code',
                'scope_separator' => ' ',
                'scopes' => [
                    'openid',
                    'email',
                    'profile',
                    'https://www.googleapis.com/auth/business.manage',
                ],
                'query' => [
                    'access_type' => 'offline',
                    'include_granted_scopes' => 'true',
                    'prompt' => 'consent',
                ],
            ],
        ],
    ],
];
