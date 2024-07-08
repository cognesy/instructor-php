<?php
namespace Cognesy\Instructor\Extras\Module\Modules\Web;

use Closure;
use Cognesy\Instructor\Extras\Module\Core\Module;
use Cognesy\Instructor\Extras\Module\Modules\Text\SummarizeText;
use Cognesy\Instructor\Extras\Module\Modules\Web\Data\PageSummary;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\ModuleDescription;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\ModuleSignature;

#[ModuleSignature('url:string -> summary:PageSummary')]
#[ModuleDescription('Summarize a webpage')]
class SummarizeWebpage extends Module
{
    private SummarizeText $summarize;
    private GetHtmlLinks $getLinks;
    private ConvertHtmlToMarkdown $convertToMarkdown;
    private GetUrlContent $getContent;

    public function __construct(
        string|Closure $scraper = 'browsershot',
    ) {
        $this->summarize = new SummarizeText();
        $this->getLinks = new GetHtmlLinks();
        $this->convertToMarkdown = new ConvertHtmlToMarkdown();
        $this->getContent = new GetUrlContent($scraper);
    }

    public function for(string $url): PageSummary {
        return ($this)(url: $url)->get('summary');
    }

    protected function forward(mixed ...$callArgs): array {
        $url = $callArgs['url'];
        $html = $this->getContent->for(url: $url);
        $markdown = $this->convertToMarkdown->for(html: $html);
        $summary = $this->summarize->for(text: $markdown);
        $pageSummary = new PageSummary(
            summary: $summary,
            links: $this->getLinks->for(html: $html)
        );
        return [
            'summary' => $pageSummary
        ];
    }
}