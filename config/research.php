<?php
declare(strict_types=1);

return [
    'default_limit' => 10,
    'http_timeout' => 12,
    'providers' => [
        'google' => [
            'enabled' => true,
            'label' => 'Google',
            'api_key' => 'tu_google_api_key',
            'search_engine_id' => 'tu_google_search_engine_id',
            'max_results' => 10,
            'locale' => 'lang_es',
            'country' => 'countryMX',
        ],
        'youtube' => [
            'enabled' => true,
            'label' => 'YouTube',
            'api_key' => 'tu_youtube_api_key',
            'max_results' => 12,
            'region_code' => 'MX',
            'relevance_language' => 'es',
        ],
        'mercado_libre' => [
            'enabled' => true,
            'label' => 'Mercado Libre',
            'site_id' => 'MLM',
            'max_results' => 20,
        ],
        'amazon' => [
            'enabled' => true,
            'label' => 'Amazon',
            'access_key' => 'tu_amazon_access_key',
            'secret_key' => 'tu_amazon_secret_key',
            'partner_tag' => 'tu_partner_tag',
            'marketplace' => 'www.amazon.com.mx',
            'region' => 'us-east-1',
            'max_results' => 10,
        ],
    ],
];
