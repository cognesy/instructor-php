<?php
namespace Cognesy\Instructor\Extras\Module\Modules\Web;

use Closure;
use Cognesy\Instructor\Extras\Module\Core\Module;
use Cognesy\Instructor\Extras\Module\Modules\Text\SummarizeText;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\ModuleDescription;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\ModuleSignature;
use Cognesy\Instructor\Utils\Web\Data\PageSummary;

#[ModuleSignature('url:string -> summary:PageSummary')]
#[ModuleDescription('Summarize a webpage')]
class SummarizeWebpage extends Module
{
    protected SummarizeText $summarize;
    protected GetHtmlLinks $getLinks;
    protected ConvertHtmlToMarkdown $convertToMarkdown;
    protected GetUrlContent $getContent;

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