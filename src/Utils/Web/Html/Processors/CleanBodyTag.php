<?php

namespace Cognesy\Instructor\Utils\Web\Html\Processors;

use Cognesy\Instructor\Utils\Web\Contracts\CanCleanHtml;

class CleanBodyTag implements CanCleanHtml
{
    public function process(string $html): string {
        return preg_replace('/<body[^>]*>/', '<body>', $html);
    }
}