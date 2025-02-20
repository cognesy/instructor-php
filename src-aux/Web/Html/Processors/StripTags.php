<?php

namespace Cognesy\Aux\Web\Html\Processors;

use Cognesy\Aux\Web\Contracts\CanCleanHtml;

class StripTags implements CanCleanHtml
{
    public function process(string $html): string
    {
        return strip_tags($html);
    }
}