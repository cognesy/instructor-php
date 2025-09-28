<?php
namespace Cognesy\Experimental\Modules\Web;

use Closure;
use Cognesy\Experimental\Module\Core\Module;
use Cognesy\Experimental\Signature\Attributes\ModuleDescription;
use Cognesy\Experimental\Signature\Attributes\ModuleSignature;

#[ModuleSignature('url:string -> content:string')]
#[ModuleDescription('Retrieve the content of a URL')]
class GetUrlContent extends Module
{
    protected Closure $getUrlFn;

    public function __construct(
        Closure|string|null $scraper = null
    ) {
        $this->getUrlFn = match(true) {
            empty($scraper) => fn(string $url) => file_get_contents($url),
            is_callable($scraper) => fn(string $url) => $scraper($url),
            is_string($scraper) => fn(string $url) => \Cognesy\Auxiliary\Web\Scraper::withDriver($scraper)->getContent($url),
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
