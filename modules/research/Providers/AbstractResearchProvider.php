<?php
declare(strict_types=1);

namespace AiScaler\Research\Providers;

use AiScaler\Research\Contracts\ResearchProviderInterface;
use AiScaler\Research\Http\HttpClient;
use AiScaler\Research\Support\TextAnalyzer;

abstract class AbstractResearchProvider implements ResearchProviderInterface
{
    public function __construct(
        protected readonly array $config,
        protected readonly HttpClient $httpClient,
        protected readonly TextAnalyzer $textAnalyzer
    ) {
    }

    abstract protected function providerId(): string;

    abstract protected function providerLabel(): string;

    protected function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    protected function hasValidConfigValue(string $key): bool
    {
        $value = trim((string) ($this->config[$key] ?? ''));

        return $value !== '' && !str_starts_with($value, 'tu_');
    }

    protected function stringConfig(string $key, string $default = ''): string
    {
        return trim((string) ($this->config[$key] ?? $default));
    }

    protected function intConfig(string $key, int $default = 10): int
    {
        $value = (int) ($this->config[$key] ?? $default);

        return $value > 0 ? $value : $default;
    }

    protected function disabledResponse(string $message = 'Esta integracion esta deshabilitada.'): array
    {
        return $this->baseResponse('disabled', 'Deshabilitado', $message);
    }

    protected function pendingResponse(string $message): array
    {
        return $this->baseResponse('needs_configuration', 'Config pendiente', $message);
    }

    protected function errorResponse(string $message): array
    {
        return $this->baseResponse('error', 'Error', $message);
    }

    protected function successResponse(
        array $entries,
        ?int $totalResults,
        int $analyzedItems,
        string $message = ''
    ): array {
        return array_merge(
            $this->baseResponse('ready', 'Listo', $message),
            [
                'summary' => [
                    'total_results' => $totalResults,
                    'analyzed_items' => $analyzedItems,
                    'related_terms' => count($entries),
                ],
                'entries' => $entries,
            ]
        );
    }

    private function baseResponse(string $status, string $statusLabel, string $message): array
    {
        return [
            'id' => $this->providerId(),
            'label' => $this->providerLabel(),
            'status' => $status,
            'status_label' => $statusLabel,
            'message' => $message,
            'summary' => [
                'total_results' => null,
                'analyzed_items' => 0,
                'related_terms' => 0,
            ],
            'entries' => [],
        ];
    }
}
