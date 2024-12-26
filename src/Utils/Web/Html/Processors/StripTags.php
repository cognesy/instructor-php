<?php

namespace Cognesy\Instructor\Utils\Web\Html\Processors;

use Cognesy\Instructor\Utils\Web\Contracts\CanCleanHtml;

class StripTags implements CanCleanHtml
{
    public function process(string $html): string
    {
        return strip_tags($html);
    }
}