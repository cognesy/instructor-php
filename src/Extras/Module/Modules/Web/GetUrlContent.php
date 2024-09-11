<?php
namespace Cognesy\Instructor\Extras\Module\Modules\Web;

use Closure;
use Cognesy\Instructor\Extras\Module\Core\Module;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\ModuleDescription;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\ModuleSignature;
use Cognesy\Instructor\Extras\Web\Scrapers\JinaReaderDriver;
use Cognesy\Instructor\Extras\Web\Scrapers\ScrapFlyDriver;
use Cognesy\Instructor\Extras\Web\Scrapers\ScrapingBeeDriver;
use Spatie\Browsershot\Browsershot;

#[ModuleSignature('url:string -> content:string')]
#[ModuleDescription('Retrieve the content of a URL')]
class GetUrlContent extends Module
{
    protected Closure $getUrlFn;

    public function __construct(
        Closure|string $scraper = null
    ) {
        $this->getUrlFn = match(true) {
            empty($scraper) => fn(string $url) => file_get_contents($url),
            is_callable($scraper) => fn(string $url) => $scraper($url),
            is_string($scraper) => match($scraper) {
                'jina' => fn(string $url) => JinaReaderDriver::fromUrl($url),
                'scrapingbee' => fn(string $url) => ScrapingBeeDriver::fromUrl($url),
                'scrapfly' => fn(string $url) => ScrapFlyDriver::fromUrl($url),
                'browsershot' => fn(string $url) => Browsershot::url($url)->bodyHtml(),
                default => fn(string $url) => file_get_contents($url)
            },
            default => $scraper
        };
    }

    public function for(string $url): string {
        return ($this)(url: $url)->get('content');
    }

    protected function forward(mixed ...$args): array {
        $url = $args['url'];
        return [
            'content' => ($this->getUrlFn)($url)
        ];
    }
}
