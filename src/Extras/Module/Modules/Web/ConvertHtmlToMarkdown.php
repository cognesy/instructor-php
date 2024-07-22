<?php

namespace Cognesy\Instructor\Extras\Module\Modules\Web;

use Cognesy\Instructor\Extras\Module\Core\Module;
use Cognesy\Instructor\Extras\Module\Modules\Web\Utils\HtmlProcessor;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\ModuleDescription;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\ModuleSignature;

#[ModuleSignature('html:string -> markdown:string')]
#[ModuleDescription('Convert HTML to Markdown')]
class ConvertHtmlToMarkdown extends Module
{
    protected HtmlProcessor $processHtml;

    public function __construct() {
        $this->processHtml = new HtmlProcessor();
    }

    public function for(string $html): string {
        return ($this)(html: $html)->get('markdown');
    }

    protected function forward(mixed ...$args): array {
        $html = $args['html'];
        $result = $this->processHtml->toMarkdown($html);
        return [
            'markdown' => $result
        ];
    }
}
