<?php

namespace Cognesy\Instructor\Extras\Module\Modules\Markdown;

use Cognesy\Instructor\Extras\Module\Core\Module;
use Cognesy\Instructor\Extras\Module\Modules\Markdown\Data\Section;
use Cognesy\Instructor\Extras\Module\Modules\Markdown\Utils\MarkdownSplitter;

class SplitMarkdown extends Module
{
    protected MarkdownSplitter $splitter;

    public function __construct() {
        $this->splitter = new MarkdownSplitter();
    }

    /** @return \Cognesy\Instructor\Extras\Module\Modules\Markdown\Data\Section[] */
    public function for(string $markdown, string $source): array {
        return ($this)(markdown: $markdown, source: $source)->get('sections');
    }

    protected function forward(...$callArgs): array {
        $markdown = $callArgs['markdown'];
        $source = $callArgs['source'];
        $sections = $this->splitIntoSections($markdown, $source);
        return [
            'sections' => $sections
        ];
    }

    private function splitIntoSections(string $markdown, string $source = '') : array {
        $sections = [];
        $split = $this->splitter->splitMarkdownAtLevel($markdown, true, 2);
        foreach ($split as $section) {
            $sections[] = new Section(
                title: $section['header'],
                content: $section['body'],
                source: $source
            );
        }
        return $sections;
    }
}