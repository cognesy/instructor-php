<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Workspace;

use Cognesy\Tell\Core\Workspace\TellWorkspaceContext;

interface CanOpenTellWorkspace extends CanManageTellWorkspace
{
    public function open(string $directory): TellWorkspaceContext;
}
