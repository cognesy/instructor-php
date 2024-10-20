<?php

namespace Cognesy\Instructor\Utils\Web\Filters;

use Cognesy\Instructor\Utils\Web\Contracts\CanFilterContent;

class NoFilter implements CanFilterContent
{
    public function filter(string $content): bool {
        return true;
    }
}
