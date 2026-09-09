<?php
declare(strict_types=1);

return [
    'secret_key' => trim((string) (getenv('AISCALER_STRIPE_SECRET_KEY') ?: '')),
    'publishable_key' => trim((string) (getenv('AISCALER_STRIPE_PUBLISHABLE_KEY') ?: '')),
    'webhook_secret' => trim((string) (getenv('AISCALER_STRIPE_WEBHOOK_SECRET') ?: '')),
    'price_id' => trim((string) (getenv('AISCALER_STRIPE_PRICE_ID') ?: '')),
    'currency' => 'mxn',
    'amount_mxn' => 33000,
    'plan_key' => 'ecosistema_asx',
    'plan_label' => 'Ecosistema ASX',
];
