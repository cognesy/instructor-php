<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Web\Contracts;

interface CanCleanHtml
{
    public function process(string $html): string;
}