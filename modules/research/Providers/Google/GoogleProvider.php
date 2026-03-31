<?php
declare(strict_types=1);

namespace AiScaler\Research\Providers\Google;

use AiScaler\Research\Providers\AbstractResearchProvider;
use Throwable;

final class GoogleProvider extends AbstractResearchProvider
{
    protected function providerId(): string
    {
        return 'google';
    }

    protected function providerLabel(): string
    {
        return $this->stringConfig('label', 'Google');
    }

    public function analyze(string $idea, int $limit = 10): array
    {
        if (!$this->isEnabled()) {
            return $this->disabledResponse();
        }

        if (!$this->hasValidConfigValue('api_key') || !$this->hasValidConfigValue('search_engine_id')) {
            return $this->pendingResponse(
                'Completa google.api_key y google.search_engine_id en config/research.php para consultar Google Custom Search desde PHP.'
            );
        }

        try {
            $response = $this->httpClient->getJson(
                'https://customsearch.googleapis.com/customsearch/v1',
                [
                    'key' => $this->stringConfig('api_key'),
                    'cx' => $this->stringConfig('search_engine_id'),
                    'q' => $idea,
                    'num' => min(10, $this->intConfig('max_results', 10)),
                    'hl' => 'es',
                    'lr' => $this->stringConfig('locale', 'lang_es'),
                    'gl' => 'mx',
                    'cr' => $this->stringConfig('country', 'countryMX'),
                ]
            );

            $items = is_array($response['items'] ?? null) ? $response['items'] : [];
            $texts = [];

            foreach ($items as $item) {
                $texts[] = trim((string) ($item['title'] ?? '') . ' ' . (string) ($item['snippet'] ?? ''));
            }

            $entries = $this->textAnalyzer->extractRelatedTerms($texts, $idea, $limit);
            $totalResults = isset($response['searchInformation']['totalResults'])
                ? (int) $response['searchInformation']['totalResults']
                : count($items);

            return $this->successResponse(
                $entries,
                $totalResults,
                count($items),
                'Terminos relacionados obtenidos a partir de los resultados recuperados por Google.'
            );
        } catch (Throwable $exception) {
            return $this->errorResponse('No fue posible consultar Google: ' . $exception->getMessage());
        }
    }
}
