<?php

namespace Cognesy\Auxiliary\Web\Html\Processors;

use Cognesy\Auxiliary\Web\Contracts\CanCleanHtml;

class StripTags implements CanCleanHtml
{
    public function process(string $html): string
    {
        return strip_tags($html);
    }
}