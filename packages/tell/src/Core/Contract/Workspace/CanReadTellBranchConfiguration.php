<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Workspace;

use Cognesy\Tell\Data\TellBranchConfig;

/** Optional input to aggregate configuration; branch values remain secret-free. */
interface CanReadTellBranchConfiguration
{
    public function read(string $directory, ?string $branch = null): ?TellBranchConfig;
}
