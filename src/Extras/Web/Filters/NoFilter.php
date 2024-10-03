<?php

namespace Cognesy\Instructor\Extras\Web\Filters;

use Cognesy\Instructor\Extras\Web\Contracts\CanFilterContent;

class NoFilter implements CanFilterContent
{
    public function filter(string $content): bool {
        return true;
    }
}
