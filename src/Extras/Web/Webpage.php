<?php

namespace Cognesy\Instructor\Extras\Web;

use Cognesy\Instructor\Extras\Web\Contracts\CanGetUrlContent;
use Cognesy\Instructor\Extras\Web\Html\HtmlProcessor;
use Cognesy\Instructor\Extras\Web\Scrapers\BasicReader;
use Cognesy\Instructor\Extras\Web\Scrapers\JinaReader;
use Cognesy\Instructor\Extras\Web\Scrapers\ScrapFly;
use Cognesy\Instructor\Extras\Web\Scrapers\ScrapingBee;
use Cognesy\Instructor\Utils\Settings;
use Exception;

class Webpage
{
    private CanGetUrlContent $scraper;
    private HtmlProcessor $htmlProcessor;
    private string $content;

    public function __construct(
        CanGetUrlContent $scraper = null,
    ) {
        $this->scraper = $scraper ?? new BasicReader();
        $this->htmlProcessor = new HtmlProcessor();
    }

    public static function withScraper(string $scraper = '') : Webpage {
        $scraper = $scraper ?: Settings::get('web', 'defaultScraper', 'none');

        $baseUrl = Settings::get('web', 'scrapers'.$scraper.'.base_uri', '');
        $apiKey = Settings::get('web', 'scrapers'.$scraper.'.api_key', '');

        $scraperObject = match($scraper) {
            'none' => new BasicReader(),
            'jinareader' => new JinaReader($baseUrl, $apiKey),
            'scrapfly' => new Scrapfly($baseUrl, $apiKey),
            'scrapingbee' => new ScrapingBee($baseUrl, $apiKey),
            default => throw new Exception("Unknown scraper requested: $scraper"),
        };

        return new Webpage($scraperObject);
    }

    public function get(string $url, array $options = []) : static {
        $this->content = $this->scraper->getContent($url, $options);
        return $this;
    }

    public function content() : string {
        return $this->content;
    }

    public function metadata(array $attributes = []) : array {
        return $this->htmlProcessor->getMetadata($this->content, $attributes);
    }

    public function title() : string {
        return $this->htmlProcessor->getTitle($this->content);
    }

    public function body() : string {
        return $this->htmlProcessor->getBody($this->content);
    }

    public function asMarkdown() : string {
        return $this->htmlProcessor->toMarkdown($this->content);
    }
}
