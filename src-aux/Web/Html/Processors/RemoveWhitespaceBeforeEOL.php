<?php

namespace Cognesy\Auxiliary\Web\Html\Processors;

use Cognesy\Auxiliary\Web\Contracts\CanCleanHtml;

class RemoveWhitespaceBeforeEOL implements CanCleanHtml
{
    public function process(string $html): string {
        return preg_replace('/[\t ]+$/m', '', $str);
    }
}