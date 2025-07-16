<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Web;

use Cognesy\Auxiliary\Web\Contracts\CanGetUrlContent;
use Cognesy\Auxiliary\Web\Scrapers\BasicReader;
use Cognesy\Auxiliary\Web\Scrapers\BrowsershotDriver;
use Cognesy\Auxiliary\Web\Scrapers\FirecrawlDriver;
use Cognesy\Auxiliary\Web\Scrapers\JinaReaderDriver;
use Cognesy\Auxiliary\Web\Scrapers\ScrapFlyDriver;
use Cognesy\Auxiliary\Web\Scrapers\ScrapingBeeDriver;
use Cognesy\Config\Settings;
use Exception;

class Scraper
{
    protected CanGetUrlContent $driver;

    public static function withDriver(string $scraper = '') : CanGetUrlContent {
        $scraper = $scraper ?: Settings::get('web', 'defaultScraper', 'none');

        $baseUrl = Settings::get('web', 'scrapers.'.$scraper.'.baseUri', '');
        $apiKey = Settings::get('web', 'scrapers.'.$scraper.'.apiKey', '');

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

    public function getContent(string $url, array $options = []): string {
        return $this->driver->getContent($url, $options);
    }
}