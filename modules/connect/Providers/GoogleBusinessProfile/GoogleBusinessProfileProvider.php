<?php
declare(strict_types=1);

namespace AiScaler\Connect\Providers\GoogleBusinessProfile;

use AiScaler\Connect\Providers\AbstractConnectionProvider;

final class GoogleBusinessProfileProvider extends AbstractConnectionProvider
{
    public function definition(): array
    {
        return $this->buildDefinition([
            'key' => 'google_business_profile',
            'platform' => 'Google',
            'label' => 'Google Business Profile',
            'icon' => 'storefront',
            'description' => 'Prepara ubicaciones y fichas para futuras analiticas, reputacion y publicaciones locales.',
            'helper' => 'Cada usuario puede registrar multiples ubicaciones o perfiles de negocio.',
            'handle_label' => 'Nombre corto o etiqueta interna',
            'external_id_label' => 'Location ID o Account ID',
            'url_label' => 'URL de la ficha',
            'features' => ['Analiticas', 'Publicacion', 'Resenas'],
        ]);
    }
}
