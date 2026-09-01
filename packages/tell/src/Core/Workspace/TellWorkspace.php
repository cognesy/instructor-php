<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Workspace;

use Cognesy\Tell\Core\Contract\Workspace\CanAccessTellConversations;
use Cognesy\Tell\Core\Contract\Workspace\CanConfigureTellBranch;
use Cognesy\Tell\Core\Contract\Workspace\CanManageTellWorkspace;
use Cognesy\Tell\Core\Contract\Workspace\CanManageTellBranches;
use Cognesy\Tell\Core\Contract\Workspace\CanUseTellBranch;
use Cognesy\Tell\Core\Contract\Workspace\CanUseTellConversation;
use Cognesy\Tell\Core\Contract\Workspace\CanUseTellRef;
use Cognesy\Tell\Data\TellWorkspaceInfo;

/** Developer-facing workspace control surface for a Tell project. */
final readonly class TellWorkspace
{
    public function __construct(
        private string $directory,
        private CanManageTellWorkspace $workspaces,
        private CanAccessTellConversations $conversations,
    ) {}

    public function initialize(): TellWorkspaceInfo {
        return $this->workspaces->initialize($this->directory);
    }

    public function discover(): ?TellWorkspaceInfo {
        return $this->workspaces->discover($this->directory);
    }

    public function main(): CanUseTellConversation {
        return $this->conversations->main($this->directory);
    }

    public function conversation(string $name): CanUseTellConversation {
        return $this->conversations->conversation($this->directory, $name);
    }

    public function branches(): CanManageTellBranches {
        return $this->conversations->branches($this->directory);
    }

    /** Open an existing branch for read-only inspection without checking it out. */
    public function branch(string $name): CanUseTellBranch {
        return $this->conversations->branch($this->directory, $name);
    }

    /** Open and verify one immutable canonical conversation head or root. */
    public function ref(string $hash): CanUseTellRef {
        return $this->conversations->ref($this->directory, $hash);
    }

    public function configuration(?string $branch = null): CanConfigureTellBranch {
        return $this->conversations->configuration($this->directory, $branch);
    }
}
