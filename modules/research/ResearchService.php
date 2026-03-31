<?php
declare(strict_types=1);

namespace AiScaler\Research;

use AiScaler\Research\Contracts\ResearchProviderInterface;
use InvalidArgumentException;

final class ResearchService
{
    /**
     * @param ResearchProviderInterface[] $providers
     */
    public function __construct(
        private readonly array $providers,
        private readonly int $defaultLimit = 10
    ) {
    }

    public function analyze(string $idea, ?int $limit = null): array
    {
        $query = trim($idea);

        if ($query === '') {
            throw new InvalidArgumentException('Escribe una idea para investigar.');
        }

        $resolvedLimit = $limit ?? $this->defaultLimit;
        $results = [];

        foreach ($this->providers as $provider) {
            $results[] = $provider->analyze($query, $resolvedLimit);
        }

        return [
            'query' => $query,
            'limit' => $resolvedLimit,
            'providers' => $results,
        ];
    }
}
