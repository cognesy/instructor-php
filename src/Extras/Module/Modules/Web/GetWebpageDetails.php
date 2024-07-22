<?php

namespace Cognesy\Instructor\Extras\Module\Modules\Web;

use Cognesy\Instructor\Extras\Module\Core\Module;
use Cognesy\Instructor\Extras\Module\Modules\Web\Data\PageData;
use Cognesy\Instructor\Extras\Module\Modules\Web\Utils\HtmlProcessor;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\ModuleDescription;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\ModuleSignature;

#[ModuleSignature('url:string -> pageDetails:PageData')]
#[ModuleDescription('Retrieve information from a webpage')]
class GetWebpageDetails extends Module
{
    protected GetHtmlLinks $getLinks;
    protected GetUrlContent $getUrlContent;
    protected HtmlProcessor $processHtml;

    public function __construct() {
        $this->getLinks = new GetHtmlLinks();
        $this->getUrlContent = new GetUrlContent();
        $this->processHtml = new HtmlProcessor();
    }

    public function for(string $url): PageData {
        return ($this)(url: $url)->get('pageDetails');
    }

    protected function forward(...$callArgs): array {
        $url = $callArgs['url'];
        $content = $this->getUrlContent->for(url: $url);
        $pageDetails = $this->parsePage($content);
        return [
            'pageDetails' => $pageDetails
        ];
    }

    public function parsePage(string $html) : PageData {
        // GET METADATA
        $metadata = $this->processHtml->getMetadata(
            $html,
            ['title', 'description', 'keywords', 'og:title', 'og:description', 'og:image', 'og:url']
        );
        $title = $this->processHtml->getTitle($html);
        $metadata = array_merge(['title' => $title], $metadata);

        // CONVERT TO MARKDOWN
        $markdown = $this->processHtml->toMarkdown($html);

        return new PageData(
            title: $metadata['title'],
            markdown: $markdown,
            metadata: $metadata,
            links: ($this->getLinks)($html)->get('links')
        );
    }
}