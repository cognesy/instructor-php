<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Web\Traits;

use Cognesy\Auxiliary\Web\Link;
use Cognesy\Auxiliary\Web\Scraper;
use Cognesy\Auxiliary\Web\Webpage;

trait HandlesCreation
{
    public static function fromUrl(string $url, string $scraper = '') : static {
        return static::withScraper($scraper)->get($url);
    }

    public static function fromLink(Link $link, string $scraper = '') : static {
        return static::withScraper($scraper)->get($link->url);
    }

    public static function withHtml(string $html, string $url = '') : static {
        $webpage = new static();
        $webpage->url = $url;
        $webpage->content = $html;
        return $webpage;
    }

    public static function withScraper(string $scraper = '') : static {
        return new static(Scraper::withDriver($scraper));
    }
}
