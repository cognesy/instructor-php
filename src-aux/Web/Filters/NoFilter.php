<?php

namespace Cognesy\Aux\Web\Filters;

use Cognesy\Aux\Web\Contracts\CanFilterContent;

class NoFilter implements CanFilterContent
{
    public function filter(string $content): bool {
        return true;
    }
}
