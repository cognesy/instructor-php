<?php
namespace Cognesy\Instructor\Experimental\Module\Modules\Web;

use Closure;
use Cognesy\Instructor\Experimental\Module\Core\Module;
use Cognesy\Instructor\Experimental\Module\Signature\Attributes\ModuleDescription;
use Cognesy\Instructor\Experimental\Module\Signature\Attributes\ModuleSignature;
use Cognesy\Instructor\Utils\Web\Scraper;

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
            is_string($scraper) => fn(string $url) => Scraper::withDriver($scraper)->getContent($url),
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
