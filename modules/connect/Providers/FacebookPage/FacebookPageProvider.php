<?php
declare(strict_types=1);

namespace AiScaler\Connect\Providers\FacebookPage;

use AiScaler\Connect\Providers\AbstractConnectionProvider;

final class FacebookPageProvider extends AbstractConnectionProvider
{
    public function definition(): array
    {
        return $this->buildDefinition([
            'key' => 'facebook_page',
            'platform' => 'Facebook',
            'label' => 'Pagina de Facebook',
            'icon' => 'campaign',
            'description' => 'Conecta una pagina empresarial para futuras analiticas y publicacion.',
            'helper' => 'Puedes registrar todas las paginas que administres para centralizarlas en un solo lugar.',
            'handle_label' => 'Usuario corto de la pagina',
            'external_id_label' => 'ID o nombre interno de la pagina',
            'url_label' => 'URL de la pagina',
        ]);
    }
}
