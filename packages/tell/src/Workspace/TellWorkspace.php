<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Tell\Contracts\CanAccessTellConversations;
use Cognesy\Tell\Contracts\CanManageTellWorkspace;
use Cognesy\Tell\Data\TellWorkspaceInfo;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Workspace\Branch\TellBranch;
use Cognesy\Tell\Workspace\Branch\TellBranchConfiguration;
use Cognesy\Tell\Workspace\Branch\TellBranches;

/** Developer-facing workspace control surface for a Tell project. */
final readonly class TellWorkspace
{
    public function __construct(
        private TellAgentFactory $agents,
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

    public function main(): TellConversation {
        return $this->conversations->main($this->directory);
    }

    public function conversation(string $name): TellConversation {
        return $this->conversations->conversation($this->directory, $name);
    }

    public function branches(): TellBranches {
        return $this->conversations->branches($this->directory);
    }

    /** Open an existing branch for read-only inspection without checking it out. */
    public function branch(string $name): TellBranch {
        return $this->conversations->branch($this->directory, $name);
    }

    /** Open and verify one immutable canonical conversation head or root. */
    public function ref(string $hash): TellRef {
        return $this->conversations->ref($this->directory, $hash);
    }

    public function configuration(?string $branch = null): TellBranchConfiguration {
        return new TellBranchConfiguration($this->agents, $this->directory, $branch);
    }
}
