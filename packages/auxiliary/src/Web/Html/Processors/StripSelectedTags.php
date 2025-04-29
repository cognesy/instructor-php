<?php

namespace Cognesy\Auxiliary\Web\Html\Processors;

use Cognesy\Auxiliary\Web\Contracts\CanCleanHtml;

class StripSelectedTags implements CanCleanHtml
{
    private array $tags;

    public function __construct(?array $tags = null)
    {
        $this->tags = $tags;
    }

    public function process(string $html): string
    {
        if (empty($this->tags)) {
            return $html;
        }

        $tags = implode('|', $this->tags);
        $pattern = "/<($tags)[^>]*>.*?<\/\\1>/si";
        return preg_replace($pattern, '', $html);
    }
}