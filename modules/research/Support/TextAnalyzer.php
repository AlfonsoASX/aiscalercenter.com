<?php
declare(strict_types=1);

namespace AiScaler\Research\Support;

final class TextAnalyzer
{
    private array $stopWords = [
        'a', 'acerca', 'ahi', 'al', 'algo', 'algun', 'alguna', 'algunas', 'alguno', 'algunos',
        'alli', 'amazon', 'ante', 'antes', 'asi', 'aun', 'aunque', 'bajo', 'bien', 'cada',
        'casi', 'como', 'con', 'contra', 'cual', 'cuales', 'cualquier', 'cuando', 'cuanto',
        'de', 'del', 'desde', 'donde', 'dos', 'e', 'el', 'ella', 'ellas', 'ellos', 'en',
        'entre', 'era', 'eramos', 'eran', 'eres', 'es', 'esa', 'esas', 'ese', 'eso', 'esos',
        'esta', 'estaba', 'estaban', 'estado', 'estados', 'estan', 'estar', 'estas', 'este',
        'esto', 'estos', 'fue', 'fueron', 'fui', 'fuimos', 'google', 'gratis', 'ha', 'habia',
        'han', 'hasta', 'hay', 'he', 'hola', 'hoy', 'in', 'ir', 'la', 'las', 'le', 'les',
        'lo', 'los', 'mas', 'me', 'mercado', 'mexico', 'mi', 'mientras', 'mis', 'mismo',
        'mucho', 'muy', 'mx', 'ni', 'no', 'nos', 'nosotros', 'nuestra', 'nuestro', 'o', 'os',
        'otra', 'otro', 'otros', 'para', 'pero', 'poco', 'por', 'porque', 'que', 'quien',
        'quienes', 'se', 'sea', 'segun', 'ser', 'si', 'sin', 'sobre', 'solo', 'son', 'su',
        'sus', 'tal', 'tambien', 'te', 'tiene', 'tienen', 'todo', 'tu', 'tus', 'un', 'una',
        'unas', 'uno', 'unos', 'us', 'usa', 'usan', 'video', 'videos', 'web', 'y', 'ya',
        'youtube',
    ];

    public function extractRelatedTerms(array $texts, string $query, int $limit = 10): array
    {
        $queryTokens = array_fill_keys($this->tokenize($query), true);
        $termStats = [];

        foreach ($texts as $text) {
            $source = trim((string) $text);

            if ($source === '') {
                continue;
            }

            $tokens = $this->tokenize($source);

            if ($tokens === []) {
                continue;
            }

            $documentTerms = [];
            $tokenCount = count($tokens);

            for ($index = 0; $index < $tokenCount; $index += 1) {
                $token = $tokens[$index];

                if (!isset($queryTokens[$token]) && $this->isUsefulCandidate($token)) {
                    $documentTerms[$token] = true;
                }

                $nextToken = $tokens[$index + 1] ?? null;

                if ($nextToken === null) {
                    continue;
                }

                if (isset($queryTokens[$token]) || isset($queryTokens[$nextToken])) {
                    continue;
                }

                $bigram = $token . ' ' . $nextToken;

                if ($this->isUsefulCandidate($bigram)) {
                    $documentTerms[$bigram] = true;
                }
            }

            foreach (array_keys($documentTerms) as $term) {
                if (!isset($termStats[$term])) {
                    $termStats[$term] = [
                        'term' => $this->humanizeTerm($term),
                        'mentions' => 0,
                        'sample' => $this->truncate($source),
                    ];
                }

                $termStats[$term]['mentions'] += 1;
            }
        }

        $rows = array_values($termStats);

        usort($rows, static function (array $left, array $right): int {
            if ($left['mentions'] !== $right['mentions']) {
                return $right['mentions'] <=> $left['mentions'];
            }

            $leftWords = str_word_count((string) $left['term']);
            $rightWords = str_word_count((string) $right['term']);

            if ($leftWords !== $rightWords) {
                return $rightWords <=> $leftWords;
            }

            return strcmp((string) $left['term'], (string) $right['term']);
        });

        return array_slice($rows, 0, max(1, $limit));
    }

    private function tokenize(string $value): array
    {
        $normalized = $this->toAscii($this->toLower($value));
        $normalized = preg_replace('/[^a-z0-9\s]+/u', ' ', $normalized) ?? '';
        $parts = preg_split('/\s+/', trim($normalized)) ?: [];

        $tokens = [];

        foreach ($parts as $part) {
            if ($part === '' || strlen($part) < 3) {
                continue;
            }

            if (ctype_digit($part)) {
                continue;
            }

            if (in_array($part, $this->stopWords, true)) {
                continue;
            }

            $tokens[] = $part;
        }

        return $tokens;
    }

    private function isUsefulCandidate(string $value): bool
    {
        $trimmed = trim($value);

        if ($trimmed === '' || strlen($trimmed) < 3) {
            return false;
        }

        $words = explode(' ', $trimmed);

        foreach ($words as $word) {
            if ($word === '' || in_array($word, $this->stopWords, true)) {
                return false;
            }
        }

        return true;
    }

    private function humanizeTerm(string $term): string
    {
        return preg_replace_callback('/\b([a-z])/u', static function (array $matches): string {
            return strtoupper($matches[1]);
        }, $term) ?? $term;
    }

    private function truncate(string $value, int $limit = 110): string
    {
        $value = trim($value);

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($value, 'UTF-8') <= $limit) {
                return $value;
            }

            return rtrim(mb_substr($value, 0, $limit - 1, 'UTF-8')) . '...';
        }

        if (strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(substr($value, 0, $limit - 1)) . '...';
    }

    private function toLower(string $value): string
    {
        return function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
    }

    private function toAscii(string $value): string
    {
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return $transliterated !== false ? $transliterated : $value;
    }
}
