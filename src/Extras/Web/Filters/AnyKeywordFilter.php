<?php

namespace Cognesy\Instructor\Extras\Web\Filters;

use Cognesy\Instructor\Extras\Web\Contracts\CanFilterContent;
use Cognesy\Instructor\Utils\Str;

class AnyKeywordFilter implements CanFilterContent
{
    public function __construct(
        readonly private array $keywords = [],
        readonly private bool $isCaseSensitive = false
    ) {}

    public function filter(string $content): bool {
        return Str::contains($content, $this->keywords, $this->isCaseSensitive);
    }
}