<?php

namespace Cognesy\Auxiliary\Web\Html\Processors;

use Cognesy\Auxiliary\Web\Contracts\CanCleanHtml;

class StripTagsWithContent implements CanCleanHtml
{
    private $tags;

    public function __construct($tags = null) {
        $this->tags = $tags;
    }

    public function process(string $html): string {
        if (empty($this->tags)) {
            return $html;
        }
        foreach ($this->tags as $tag) {
            $html = $this->removeTagWithContent($html, $tag);
        }
        return $html;
    }

    private function removeTagWithContent(string $html, string $tag) : string {
        $pattern = "/<($tag)[^>]*>.*?<\/\\1>/si";
        return preg_replace($pattern, '', $html);
    }
}