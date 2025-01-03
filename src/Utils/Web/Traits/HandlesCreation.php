<?php
namespace Cognesy\Instructor\Utils\Web\Traits;

use Cognesy\Instructor\Utils\Web\Link;
use Cognesy\Instructor\Utils\Web\Scraper;
use Cognesy\Instructor\Utils\Web\Webpage;

trait HandlesCreation
{
    public static function fromUrl(string $url, string $scraper = '') : static {
        return static::withScraper($scraper)->get($url);
    }

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
        return new Webpage(Scraper::withDriver($scraper));
    }
}
