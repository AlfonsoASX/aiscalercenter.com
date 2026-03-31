<?php
declare(strict_types=1);

namespace AiScaler\Connect\Providers\Instagram;

use AiScaler\Connect\Providers\AbstractConnectionProvider;

final class InstagramProvider extends AbstractConnectionProvider
{
    public function definition(): array
    {
        return $this->buildDefinition([
            'key' => 'instagram',
            'platform' => 'Instagram',
            'label' => 'Cuenta de Instagram',
            'icon' => 'photo_camera',
            'description' => 'Administra cuentas de Instagram desde el mismo centro de activos digitales.',
            'helper' => 'Puedes conectar cuentas de marca, creador o negocio de forma independiente.',
            'handle_label' => 'Usuario de Instagram',
            'external_id_label' => 'Instagram User ID',
            'url_label' => 'URL del perfil',
        ]);
    }
}
