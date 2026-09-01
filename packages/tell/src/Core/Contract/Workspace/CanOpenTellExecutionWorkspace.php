<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Workspace;

/** Opens the optional durable execution context for a request directory. */
interface CanOpenTellExecutionWorkspace
{
    public function open(string $directory): ?CanUseTellExecutionWorkspace;
}
