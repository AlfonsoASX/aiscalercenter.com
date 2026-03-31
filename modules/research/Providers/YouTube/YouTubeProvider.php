<?php
declare(strict_types=1);

namespace AiScaler\Research\Providers\YouTube;

use AiScaler\Research\Providers\AbstractResearchProvider;
use Throwable;

final class YouTubeProvider extends AbstractResearchProvider
{
    protected function providerId(): string
    {
        return 'youtube';
    }

    protected function providerLabel(): string
    {
        return $this->stringConfig('label', 'YouTube');
    }

    public function analyze(string $idea, int $limit = 10): array
    {
        if (!$this->isEnabled()) {
            return $this->disabledResponse();
        }

        if (!$this->hasValidConfigValue('api_key')) {
            return $this->pendingResponse(
                'Completa youtube.api_key en config/research.php para consultar YouTube Data API desde PHP.'
            );
        }

        try {
            $response = $this->httpClient->getJson(
                'https://www.googleapis.com/youtube/v3/search',
                [
                    'part' => 'snippet',
                    'type' => 'video',
                    'maxResults' => min(25, $this->intConfig('max_results', 12)),
                    'q' => $idea,
                    'key' => $this->stringConfig('api_key'),
                    'regionCode' => $this->stringConfig('region_code', 'MX'),
                    'relevanceLanguage' => $this->stringConfig('relevance_language', 'es'),
                    'safeSearch' => 'moderate',
                ]
            );

            $items = is_array($response['items'] ?? null) ? $response['items'] : [];
            $texts = [];

            foreach ($items as $item) {
                $snippet = is_array($item['snippet'] ?? null) ? $item['snippet'] : [];
                $texts[] = trim(
                    (string) ($snippet['title'] ?? '')
                    . ' '
                    . (string) ($snippet['channelTitle'] ?? '')
                    . ' '
                    . (string) ($snippet['description'] ?? '')
                );
            }

            $entries = $this->textAnalyzer->extractRelatedTerms($texts, $idea, $limit);
            $pageInfo = is_array($response['pageInfo'] ?? null) ? $response['pageInfo'] : [];
            $totalResults = isset($pageInfo['totalResults']) ? (int) $pageInfo['totalResults'] : count($items);

            return $this->successResponse(
                $entries,
                $totalResults,
                count($items),
                'Terminos relacionados derivados de titulos y descripciones de YouTube.'
            );
        } catch (Throwable $exception) {
            return $this->errorResponse('No fue posible consultar YouTube: ' . $exception->getMessage());
        }
    }
}
