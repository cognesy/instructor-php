<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Web;

use Cognesy\Auxiliary\Web\Config\ScraperConfig;
use Cognesy\Auxiliary\Web\Contracts\CanGetUrlContent;
use Cognesy\Auxiliary\Web\Scrapers\BasicReader;
use Cognesy\Auxiliary\Web\Scrapers\BrowsershotDriver;
use Cognesy\Auxiliary\Web\Scrapers\FirecrawlDriver;
use Cognesy\Auxiliary\Web\Scrapers\JinaReaderDriver;
use Cognesy\Auxiliary\Web\Scrapers\ScrapFlyDriver;
use Cognesy\Auxiliary\Web\Scrapers\ScrapingBeeDriver;
use Exception;

class Scraper
{
    public static function fromDriver(string $scraper = '', ?ScraperConfig $config = null) : CanGetUrlContent {
        $config ??= new ScraperConfig();
        $scraper = $config->selectedScraper($scraper);
        $baseUrl = $config->baseUriFor($scraper);
        $apiKey = $config->apiKeyFor($scraper);

        return match($scraper) {
            'none' => new BasicReader(),
            'browsershot' => new BrowsershotDriver(),
            'firecrawl' => new FirecrawlDriver($baseUrl, $apiKey),
            'jinareader' => new JinaReaderDriver($baseUrl, $apiKey),
            'scrapfly' => new ScrapFlyDriver($baseUrl, $apiKey),
            'scrapingbee' => new ScrapingBeeDriver($baseUrl, $apiKey),
            default => throw new Exception("Unknown scraper requested: $scraper"),
        };
    }
}
