<?php
declare(strict_types=1);

namespace AiScaler\Research\Providers\MercadoLibre;

use AiScaler\Research\Providers\AbstractResearchProvider;
use Throwable;

final class MercadoLibreProvider extends AbstractResearchProvider
{
    protected function providerId(): string
    {
        return 'mercado_libre';
    }

    protected function providerLabel(): string
    {
        return $this->stringConfig('label', 'Mercado Libre');
    }

    public function analyze(string $idea, int $limit = 10): array
    {
        if (!$this->isEnabled()) {
            return $this->disabledResponse();
        }

        try {
            $siteId = $this->stringConfig('site_id', 'MLM');
            $response = $this->httpClient->getJson(
                sprintf('https://api.mercadolibre.com/sites/%s/search', rawurlencode($siteId)),
                [
                    'q' => $idea,
                    'limit' => min(50, $this->intConfig('max_results', 20)),
                ]
            );

            $results = is_array($response['results'] ?? null) ? $response['results'] : [];
            $texts = [];

            foreach ($results as $item) {
                $texts[] = trim(
                    (string) ($item['title'] ?? '')
                    . ' '
                    . (string) ($item['condition'] ?? '')
                    . ' '
                    . (string) ($item['domain_id'] ?? '')
                );
            }

            $entries = $this->textAnalyzer->extractRelatedTerms($texts, $idea, $limit);
            $paging = is_array($response['paging'] ?? null) ? $response['paging'] : [];
            $totalResults = isset($paging['total']) ? (int) $paging['total'] : count($results);

            return $this->successResponse(
                $entries,
                $totalResults,
                count($results),
                'Terminos relacionados generados a partir de los resultados publicos de Mercado Libre.'
            );
        } catch (Throwable $exception) {
            return $this->errorResponse('No fue posible consultar Mercado Libre: ' . $exception->getMessage());
        }
    }
}
