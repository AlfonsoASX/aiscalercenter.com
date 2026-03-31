<?php
declare(strict_types=1);

namespace AiScaler\Research\Contracts;

interface ResearchProviderInterface
{
    public function analyze(string $idea, int $limit = 10): array;
}
