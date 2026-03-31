<?php
declare(strict_types=1);

namespace AiScaler\Connect;

use AiScaler\Connect\Contracts\ConnectionProviderInterface;

final class ConnectService
{
    /**
     * @param ConnectionProviderInterface[] $providers
     */
    public function __construct(
        private readonly array $providers
    ) {
    }

    public function catalog(): array
    {
        return array_map(static function (ConnectionProviderInterface $provider): array {
            return $provider->definition();
        }, $this->providers);
    }

    public function find(string $providerKey): ?array
    {
        foreach ($this->providers as $provider) {
            $definition = $provider->definition();

            if (($definition['key'] ?? '') === $providerKey) {
                return $definition;
            }
        }

        return null;
    }
}
