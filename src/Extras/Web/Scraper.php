<?php

namespace Cognesy\Instructor\Extras\Web;

use Cognesy\Instructor\Extras\Web\Contracts\CanGetUrlContent;
use Cognesy\Instructor\Extras\Web\Scrapers\BasicReader;
use Cognesy\Instructor\Extras\Web\Scrapers\BrowsershotDriver;
use Cognesy\Instructor\Extras\Web\Scrapers\JinaReaderDriver;
use Cognesy\Instructor\Extras\Web\Scrapers\ScrapFlyDriver;
use Cognesy\Instructor\Extras\Web\Scrapers\ScrapingBeeDriver;
use Cognesy\Instructor\Utils\Settings;
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
            default => throw new Exception("Unknown scraper requested: $scraper"),
        };
    }

    public function getContent(string $url, array $options = []): string {
        return $this->driver->getContent($url, $options);
    }

    public function batch(array $urls, array $options = []): array {
        // use Guzzle to fetch multiple URLs in parallel

    }
}