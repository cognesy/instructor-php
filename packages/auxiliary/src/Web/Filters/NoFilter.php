<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Web\Filters;

use Cognesy\Auxiliary\Web\Contracts\CanFilterContent;

class NoFilter implements CanFilterContent
{
    public function filter(string $content): bool {
        return true;
    }
}
