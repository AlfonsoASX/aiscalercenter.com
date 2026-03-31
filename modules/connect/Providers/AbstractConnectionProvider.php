<?php
declare(strict_types=1);

namespace AiScaler\Connect\Providers;

use AiScaler\Connect\Contracts\ConnectionProviderInterface;

abstract class AbstractConnectionProvider implements ConnectionProviderInterface
{
    public function __construct(
        protected readonly array $config = []
    ) {
    }

    protected function buildDefinition(array $data): array
    {
        $oauth = is_array($this->config['oauth'] ?? null) ? $this->config['oauth'] : [];
        $oauthReady = $this->hasConfigValue($oauth['auth_url'] ?? '')
            && $this->hasConfigValue($oauth['client_id'] ?? '')
            && $this->hasConfigValue($oauth['client_secret'] ?? '')
            && $this->hasConfigValue($oauth['redirect_uri'] ?? '');

        return array_merge(
            [
                'key' => '',
                'platform' => '',
                'label' => '',
                'icon' => 'link',
                'description' => '',
                'helper' => '',
                'handle_label' => 'Usuario o handle',
                'external_id_label' => 'ID o identificador',
                'url_label' => 'URL del activo',
                'features' => ['Analiticas', 'Publicacion'],
                'oauth_ready' => $oauthReady,
            ],
            $data
        );
    }

    private function hasConfigValue(string $value): bool
    {
        $trimmed = trim($value);

        return $trimmed !== '' && !str_starts_with($trimmed, 'tu_');
    }
}
