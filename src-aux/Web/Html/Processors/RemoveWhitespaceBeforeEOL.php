<?php

namespace Cognesy\Aux\Web\Html\Processors;

use Cognesy\Aux\Web\Contracts\CanCleanHtml;

class RemoveWhitespaceBeforeEOL implements CanCleanHtml
{
    public function process(string $html): string {
        return preg_replace('/[\t ]+$/m', '', $str);
    }
}