<?php
declare(strict_types=1);

namespace AiScaler\Connect\Providers\LinkedInProfile;

use AiScaler\Connect\Providers\AbstractConnectionProvider;

final class LinkedInProfileProvider extends AbstractConnectionProvider
{
    public function definition(): array
    {
        return $this->buildDefinition([
            'key' => 'linkedin_profile',
            'platform' => 'LinkedIn',
            'label' => 'Perfil personal de LinkedIn',
            'icon' => 'badge',
            'description' => 'Consolida perfiles profesionales de fundadores, ejecutivos o equipo comercial.',
            'helper' => 'Sirve para preparar analiticas personales y futuras publicaciones desde un solo panel.',
            'handle_label' => 'Public identifier del perfil',
            'external_id_label' => 'ID interno del perfil',
            'url_label' => 'URL del perfil',
        ]);
    }
}
