<?php

namespace Cognesy\Instructor\Utils\Web;

use Cognesy\Instructor\Utils\Settings;
use Cognesy\Instructor\Utils\Web\Contracts\CanGetUrlContent;
use Cognesy\Instructor\Utils\Web\Scrapers\BasicReader;
use Cognesy\Instructor\Utils\Web\Scrapers\BrowsershotDriver;
use Cognesy\Instructor\Utils\Web\Scrapers\FirecrawlDriver;
use Cognesy\Instructor\Utils\Web\Scrapers\JinaReaderDriver;
use Cognesy\Instructor\Utils\Web\Scrapers\ScrapFlyDriver;
use Cognesy\Instructor\Utils\Web\Scrapers\ScrapingBeeDriver;
use Exception;

class Scraper
{
    protected CanGetUrlContent $driver;

    public static function withDriver(string $scraper = '') : CanGetUrlContent {
        $scraper = $scraper ?: Settings::get('web', 'defaultScraper', 'none');

        $baseUrl = Settings::get('web', 'scrapers'.$scraper.'.baseUri', '');
        $apiKey = Settings::get('web', 'scrapers'.$scraper.'.apiKey', '');

        return match($scraper) {
            'none' => new BasicReader(),
            'browsershot' => new BrowsershotDriver(),
            'jinareader' => new JinaReaderDriver($baseUrl, $apiKey),
            'scrapfly' => new ScrapFlyDriver($baseUrl, $apiKey),
            'scrapingbee' => new ScrapingBeeDriver($baseUrl, $apiKey),
            'firecrawl' => new FirecrawlDriver($baseUrl, $apiKey),
            default => throw new Exception("Unknown scraper requested: $scraper"),
        };
    }

    public function getContent(string $url, array $options = []): string {
        return $this->driver->getContent($url, $options);
    }
}