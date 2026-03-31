<?php
declare(strict_types=1);

namespace AiScaler\Connect\Providers\LinkedInCompany;

use AiScaler\Connect\Providers\AbstractConnectionProvider;

final class LinkedInCompanyProvider extends AbstractConnectionProvider
{
    public function definition(): array
    {
        return $this->buildDefinition([
            'key' => 'linkedin_company',
            'platform' => 'LinkedIn',
            'label' => 'Pagina de empresa en LinkedIn',
            'icon' => 'apartment',
            'description' => 'Registra paginas de empresa para futuras analiticas, pauta y publicacion corporativa.',
            'helper' => 'Un mismo usuario puede administrar multiples paginas de empresa sin limite fijo.',
            'handle_label' => 'Public identifier de la pagina',
            'external_id_label' => 'Organization ID',
            'url_label' => 'URL de la pagina',
        ]);
    }
}
