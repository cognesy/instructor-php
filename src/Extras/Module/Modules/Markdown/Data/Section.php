<?php

namespace Cognesy\Instructor\Extras\Module\Modules\Markdown\Data;

class Section
{
    private string $title;
    private string $content;
    private string $source;

    public function __construct(string $title, string $content, string $source = '') {
        $this->title = $title;
        $this->content = $content;
        $this->source = $source;
    }

    public function getTitle(): string {
        return $this->title;
    }

    public function getContent(): string {
        return $this->content;
    }

    public function getSource(): string {
        return $this->source;
    }

    public function __toString(): string {
        return $this->title
            . "\n" . $this->content
            . "\n" . $this->source
            . "\n";
    }
}
