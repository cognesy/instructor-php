<?php

namespace Cognesy\Aux\Web\Html\Processors;

use Cognesy\Aux\Web\Contracts\CanCleanHtml;

class CleanBodyTag implements CanCleanHtml
{
    public function process(string $html): string {
        return preg_replace('/<body[^>]*>/', '<body>', $html);
    }
}