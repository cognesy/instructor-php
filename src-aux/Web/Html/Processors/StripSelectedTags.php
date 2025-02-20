<?php

namespace Cognesy\Aux\Web\Html\Processors;

use Cognesy\Aux\Web\Contracts\CanCleanHtml;

class StripSelectedTags implements CanCleanHtml
{
    private $tags;

    public function __construct($tags = null)
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