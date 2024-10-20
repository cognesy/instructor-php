<?php

namespace Cognesy\Instructor\Utils\Web;

use Cognesy\Instructor\Utils\Web\Contracts\CanFilterContent;
use Cognesy\Instructor\Utils\Web\Contracts\CanGetUrlContent;
use Cognesy\Instructor\Utils\Web\Filters\NoFilter;

class Website
{
    protected string $rootUrl;
    protected string $scraperType;
    protected CanGetUrlContent $scraper;
    protected $count = 0;
    protected $maxPages = 10;

    /** @var Webpage[] */
    protected array $pages = [];
    /** @var string[] */
    protected array $queue = [];
    /** @var string[] */
    protected array $crawled = [];


    public function __construct(string $rootUrl, int $maxPages = 10, string $scraper = '') {
        $this->rootUrl = $rootUrl;
        $this->queue[] = $rootUrl;
        $this->scraper = Scraper::withDriver($scraper);
        $this->maxPages = $maxPages;
    }

    public static function crawl(string $rootUrl, CanFilterContent $filter = null) : Website {
        if (is_null($filter)) {
            $filter = new NoFilter();
        }
        $website = new Website($rootUrl);
        $website->crawler($filter);
        return $website;
    }

    // INTERNAL ///////////////////////////////////////////////////////

    protected function crawler(CanFilterContent $filter) {
        while (!empty($this->queue) && $this->maxCountReached()) {
            $url = array_shift($this->queue);
            $this->crawled[] = $this->linkHash($url);
            $html = $this->scraper->getContent($url);
            $page = Webpage::withHtml($html, $url);

            if (!$filter->filter($page->asMarkdown())) {
                continue;
            }

            foreach ($page->links() as $link) {
                if ($this->canCrawl($link)) {
                    $this->queue[] = $link->url;
                }
            }
            $this->count++;
        }
    }

    protected function canCrawl(Link $link): bool {
        return $link->isInternal && !$this->isCrawled($link->url) && !$this->isQueued($link->url);
    }

    protected function isCrawled(string $url): bool {
        return in_array($this->linkHash($url), $this->crawled);
    }

    protected function isQueued(string $url): bool {
        return in_array($this->linkHash($url), $this->queue);
    }

    protected function linkHash(string $url): string {
        return md5($url);
    }

    private function maxCountReached() : bool {
        return $this->count < $this->maxPages;
    }
}