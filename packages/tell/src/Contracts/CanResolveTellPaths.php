<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

use Cognesy\Tell\Data\TellResolvedPaths;

interface CanResolveTellPaths
{
    public function resolve(string $directory): TellResolvedPaths;
}
