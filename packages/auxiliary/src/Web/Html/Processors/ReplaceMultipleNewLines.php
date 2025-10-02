<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Web\Html\Processors;

use Cognesy\Auxiliary\Web\Contracts\CanCleanHtml;

class ReplaceMultipleNewLines implements CanCleanHtml
{
    #[\Override]
    public function process(string $html): string {
        return preg_replace('/\n{2,}/', "\n\n", $html);
    }
}