<?php
namespace Cognesy\Instructor\Extras\Module\Modules\Search;

use Cognesy\Instructor\Extras\Module\Core\Module;
use Cognesy\Instructor\Extras\Module\Modules\Markdown\SplitMarkdown;
use Cognesy\Instructor\Extras\Module\Modules\Web\ConvertHtmlToMarkdown;
use Cognesy\Instructor\Extras\Module\Modules\Web\GetUrlContent;
use Cognesy\Instructor\Utils\BM25\SearchWithBM25;

class FindSources extends Module
{
    private GetUrlContent $getWebpageContent;
    private ConvertHtmlToMarkdown $convertToMarkdown;
    private SplitMarkdown $splitMarkdown;

    public function __construct() {
        $this->getWebpageContent = new GetUrlContent();
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
            $markdown = $this->convertToMarkdown->for(html: $webpage);
            $split = $this->splitMarkdown->for(markdown: $markdown, source: $url);
            $data[] = $split;
        }
        $documents = array_merge(...$data);
        $index = new SearchWithBM25($documents);
        $results = $index->search($query);
        $sources = [];
        foreach ($results as $result) {
            $sources[] = $documents[$result];
            if (count($sources) >= $topK) {
                break;
            }
        }
        return $sources;
    }
}
