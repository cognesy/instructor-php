<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Workspace\Execution;

use Cognesy\Tell\Core\Contract\Workspace\CanOpenTellExecutionWorkspace;
use Cognesy\Tell\Core\Contract\Workspace\CanOpenTellWorkspace;
use Cognesy\Tell\Core\Contract\Workspace\CanUseTellExecutionWorkspace;

/** Opens an execution handle over the configured workspace backend. */
final readonly class TellExecutionWorkspaceProvider implements CanOpenTellExecutionWorkspace
{
    public function __construct(private CanOpenTellWorkspace $workspaces) {}

    #[\Override]
    public function open(string $directory): ?CanUseTellExecutionWorkspace {
        $workspace = $this->workspaces->discover($directory);

        return $workspace === null ? null : new TellExecutionWorkspace($this->workspaces->open($directory));
    }
}
