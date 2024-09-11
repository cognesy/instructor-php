<?php

namespace Cognesy\Instructor\Extras\Web;

use Cognesy\Instructor\Contracts\CanProvideMessage;
use Cognesy\Instructor\Data\Messages\Message;
use Cognesy\Instructor\Extras\Web\Contracts\CanGetUrlContent;
use Cognesy\Instructor\Extras\Web\Html\HtmlProcessor;
use Cognesy\Instructor\Extras\Web\Scrapers\BasicReader;
use Cognesy\Instructor\Extras\Web\Scrapers\BrowsershotDriver;
use Cognesy\Instructor\Extras\Web\Scrapers\JinaReaderDriver;
use Cognesy\Instructor\Extras\Web\Scrapers\ScrapFlyDriver;
use Cognesy\Instructor\Extras\Web\Scrapers\ScrapingBeeDriver;
use Cognesy\Instructor\Utils\Settings;
use Exception;
use Generator;

class Webpage implements CanProvideMessage
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

    public static function withHtml(string $html) : Webpage {
        $webpage = new Webpage();
        $webpage->content = $html;
        return $webpage;
    }

    public static function withScraper(string $scraper = '') : Webpage {
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

    public function get(string $url, array $options = []) : static {
        $this->content = $this->scraper->getContent($url, $options);
        if ($options['cleanup'] ?? false) {
            $this->content = $this->htmlProcessor->cleanup($this->content);
        }
        return $this;
    }

    public function select(string $selector) : static {
        $this->content = $this->htmlProcessor->select($this->content, $selector);
        return $this;
    }

    /**
     * @param string $selector CSS selector
     * @param callable|null $fn Function to transform the selected item
     * @return Generator<Webpage> a generator of Webpage objects
     */
    public function selectMany(string $selector, callable $fn = null) : Generator {
        foreach ($this->htmlProcessor->selectMany($this->content, $selector) as $html) {
            yield match($fn) {
                null => Webpage::withHtml($html),
                default => $fn(Webpage::withHtml($html)),
            };
        }
    }

    public function content() : string {
        return $this->content;
    }

    public function cleanup() : static {
        $this->content = $this->htmlProcessor->cleanup($this->content);
        return $this;
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

    public function toMessage(): Message {
        return new Message(content: $this->asMarkdown());
    }
}
