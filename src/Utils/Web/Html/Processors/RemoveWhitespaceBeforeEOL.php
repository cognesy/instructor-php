<?php

namespace Cognesy\Instructor\Utils\Web\Html\Processors;

use Cognesy\Instructor\Utils\Web\Contracts\CanCleanHtml;

class RemoveWhitespaceBeforeEOL implements CanCleanHtml
{
    public function process(string $html): string {
        return preg_replace('/[\t ]+$/m', '', $str);
    }
}