<?php
declare(strict_types=1);

namespace AiScaler\Connect\Providers\FacebookProfile;

use AiScaler\Connect\Providers\AbstractConnectionProvider;

final class FacebookProfileProvider extends AbstractConnectionProvider
{
    public function definition(): array
    {
        return $this->buildDefinition([
            'key' => 'facebook_profile',
            'platform' => 'Facebook',
            'label' => 'Perfil personal de Facebook',
            'icon' => 'person',
            'description' => 'Registra un perfil personal para consolidar activos sociales por usuario.',
            'helper' => 'Ideal cuando la estrategia tambien depende del perfil personal del fundador o vocero.',
            'handle_label' => 'Usuario del perfil',
            'external_id_label' => 'ID del perfil',
            'url_label' => 'URL del perfil',
        ]);
    }
}
