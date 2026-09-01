<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Paths;

use Cognesy\Tell\Data\TellResolvedPaths;

interface CanResolveTellPaths
{
    public function resolve(string $directory): TellResolvedPaths;
}
