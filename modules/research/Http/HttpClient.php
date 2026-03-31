<?php
declare(strict_types=1);

namespace AiScaler\Research\Http;

use RuntimeException;

final class HttpClient
{
    public function __construct(
        private readonly int $timeout = 12
    ) {
    }

    public function getJson(string $url, array $query = [], array $headers = []): array
    {
        $requestUrl = $url;

        if ($query !== []) {
            $separator = str_contains($requestUrl, '?') ? '&' : '?';
            $requestUrl .= $separator . http_build_query($query);
        }

        $curl = curl_init($requestUrl);

        if ($curl === false) {
            throw new RuntimeException('No fue posible inicializar cURL.');
        }

        $requestHeaders = array_merge(
            [
                'Accept: application/json',
                'User-Agent: AiScalerResearch/1.0',
            ],
            $headers
        );

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => min(5, $this->timeout),
            CURLOPT_HTTPHEADER => $requestHeaders,
        ]);

        $responseBody = curl_exec($curl);

        if ($responseBody === false) {
            $error = curl_error($curl);
            curl_close($curl);

            throw new RuntimeException('Error de conexion HTTP: ' . $error);
        }

        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $decoded = json_decode($responseBody, true);

        if ($statusCode >= 400) {
            $message = is_array($decoded)
                ? (string) ($decoded['message'] ?? $decoded['error_description'] ?? json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                : $responseBody;

            throw new RuntimeException('HTTP ' . $statusCode . ': ' . $message);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('La API no devolvio un JSON valido.');
        }

        return $decoded;
    }
}
