<?php

namespace Cognesy\Aux\Web\Html\Processors;

use Cognesy\Aux\Web\Contracts\CanCleanHtml;

class ReplaceMultipleNewLines implements CanCleanHtml
{
    public function process(string $html): string {
        return preg_replace('/\n{2,}/', "\n\n", $html);
    }
}