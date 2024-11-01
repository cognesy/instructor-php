<?php

namespace Cognesy\Instructor\Utils\Web\Filters;

use Cognesy\Instructor\Utils\Str;
use Cognesy\Instructor\Utils\Web\Contracts\CanFilterContent;

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