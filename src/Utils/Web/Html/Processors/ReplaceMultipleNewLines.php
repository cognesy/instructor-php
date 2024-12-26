<?php

namespace Cognesy\Instructor\Utils\Web\Html\Processors;

use Cognesy\Instructor\Utils\Web\Contracts\CanCleanHtml;

class ReplaceMultipleNewLines implements CanCleanHtml
{
    public function process(string $html): string {
        return preg_replace('/\n{2,}/', "\n\n", $html);
    }
}