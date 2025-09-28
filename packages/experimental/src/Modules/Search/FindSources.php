<?php
namespace Cognesy\Experimental\Modules\Search;

use Cognesy\Auxiliary\Web\Html\RawHtml;
use Cognesy\Experimental\Module\Core\Module;
use Cognesy\Experimental\Modules\Markdown\SplitMarkdown;
use Cognesy\Experimental\Modules\Web\ConvertHtmlToMarkdown;
use Cognesy\Experimental\Modules\Web\GetUrlContent;

//use Cognesy\Experimental\BM25\SearchWithBM25;

class FindSources extends Module
{
    protected GetUrlContent $getWebpageContent;
    protected ConvertHtmlToMarkdown $convertToMarkdown;
    protected SplitMarkdown $splitMarkdown;

    public function __construct() {
        $this->getWebpageContent = new GetUrlContent(scraper: '');
        $this->convertToMarkdown = new ConvertHtmlToMarkdown();
        $this->splitMarkdown = new SplitMarkdown();
    }

    public function for(array $sourceUrls, string $query, int $topK = 5) : array {
        return ($this)(sourceUrls: $sourceUrls, query: $query, topK: $topK)->get('sources');
    }

    protected function forward(mixed ...$callArgs) : array {
        $urls = $callArgs['sourceUrls'];
        $query = $callArgs['query'];
        $topK = $callArgs['topK'];
        return [
            'sources' => $this->retrieveAndRank($urls, $query, $topK)
        ];
    }

    protected function retrieveAndRank(array $urls, string $query, int $topK) : array {
        $data = [];
        foreach ($urls as $url) {
            $webpage = $this->getWebpageContent->for(url: $url);
            $markdown = RawHtml::fromContent($webpage)->asMarkdown();
            $split = $this->splitMarkdown->for(markdown: $markdown, source: $url);
            $data[] = $split;
        }
        return $data;
    }
}
