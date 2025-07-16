<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Web\Html\Processors;

use Cognesy\Auxiliary\Web\Contracts\CanCleanHtml;

class CleanBodyTag implements CanCleanHtml
{
    public function process(string $html): string {
        return preg_replace('/<body[^>]*>/', '<body>', $html);
    }
}