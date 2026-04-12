<?php
declare(strict_types=1);

return [
    'app_host' => trim((string) (getenv('AISCALER_APP_HOST') ?: 'aiscaler.asx.mx')),
    'forms_host' => trim((string) (getenv('AISCALER_FORMS_HOST') ?: 'f.asx.mx')),
    'landing_host' => trim((string) (getenv('AISCALER_LANDING_HOST') ?: 'p.asx.mx')),
];
