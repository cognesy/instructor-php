<?php
namespace Cognesy\Instructor\Extras\Web\Traits;

use Cognesy\Instructor\Extras\Web\Link;
use Cognesy\Instructor\Extras\Web\Scrapers\BasicReader;
use Cognesy\Instructor\Extras\Web\Scrapers\BrowsershotDriver;
use Cognesy\Instructor\Extras\Web\Scrapers\JinaReaderDriver;
use Cognesy\Instructor\Extras\Web\Scrapers\ScrapFlyDriver;
use Cognesy\Instructor\Extras\Web\Scrapers\ScrapingBeeDriver;
use Cognesy\Instructor\Extras\Web\Webpage;
use Cognesy\Instructor\Utils\Settings;
use Exception;

trait HandlesCreation
{
    public static function fromLink(Link $link, string $scraper = '') : static {
        return static::withScraper($scraper)->get($link->url);
    }

    public static function withHtml(string $html, string $url = '') : static {
        $webpage = new Webpage();
        $webpage->url = $url;
        $webpage->content = $html;
        return $webpage;
    }

    public static function withScraper(string $scraper = '') : static {
        $scraper = $scraper ?: Settings::get('web', 'defaultScraper', 'none');

        $baseUrl = Settings::get('web', 'scrapers'.$scraper.'.base_uri', '');
        $apiKey = Settings::get('web', 'scrapers'.$scraper.'.api_key', '');

        $scraperObject = match($scraper) {
            'none' => new BasicReader(),
            'browsershot' => new BrowsershotDriver(),
            'jinareader' => new JinaReaderDriver($baseUrl, $apiKey),
            'scrapfly' => new ScrapFlyDriver($baseUrl, $apiKey),
            'scrapingbee' => new ScrapingBeeDriver($baseUrl, $apiKey),
            default => throw new Exception("Unknown scraper requested: $scraper"),
        };

        return new Webpage($scraperObject);
    }
}
