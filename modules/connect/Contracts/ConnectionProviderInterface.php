<?php
declare(strict_types=1);

namespace AiScaler\Connect\Contracts;

interface ConnectionProviderInterface
{
    public function definition(): array;
}
