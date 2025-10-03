<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Web\Html\Processors;

use Cognesy\Auxiliary\Web\Contracts\CanCleanHtml;

class CleanBodyTag implements CanCleanHtml
{
    #[\Override]
    public function process(string $html): string {
        $result = preg_replace('/<body[^>]*>/', '<body>', $html);
        return is_string($result) ? $result : $html;
    }
}