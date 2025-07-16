<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Web\Contracts;

interface CanFilterContent
{
    public function filter(string $content) : bool;
}