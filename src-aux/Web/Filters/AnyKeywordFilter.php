<?php

namespace Cognesy\Aux\Web\Filters;

use Cognesy\Aux\Web\Contracts\CanFilterContent;
use Cognesy\Utils\Str;

class AnyKeywordFilter implements CanFilterContent
{
    public function __construct(
        readonly private array $keywords = [],
        readonly private bool $isCaseSensitive = false
    ) {}

    public function filter(string $content): bool {
        return Str::containsAll($content, $this->keywords, $this->isCaseSensitive);
    }
}