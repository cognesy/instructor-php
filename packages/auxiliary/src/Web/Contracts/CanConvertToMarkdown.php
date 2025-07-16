<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Web\Contracts;

interface CanConvertToMarkdown
{
    public function toMarkdown(string $html) : string;
}
