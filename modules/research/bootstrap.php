<?php
declare(strict_types=1);

require_once __DIR__ . '/Contracts/ResearchProviderInterface.php';
require_once __DIR__ . '/Http/HttpClient.php';
require_once __DIR__ . '/Support/TextAnalyzer.php';
require_once __DIR__ . '/Providers/AbstractResearchProvider.php';
require_once __DIR__ . '/Providers/Google/GoogleProvider.php';
require_once __DIR__ . '/Providers/YouTube/YouTubeProvider.php';
require_once __DIR__ . '/Providers/MercadoLibre/MercadoLibreProvider.php';
require_once __DIR__ . '/Providers/Amazon/AmazonProvider.php';
require_once __DIR__ . '/ResearchService.php';
