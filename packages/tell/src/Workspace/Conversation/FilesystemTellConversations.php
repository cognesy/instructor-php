<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Conversation;

use Cognesy\Tell\Contracts\CanAccessTellConversations;
use Cognesy\Tell\Contracts\CanBuildTellAgent;
use Cognesy\Tell\Contracts\CanRunTell;
use Cognesy\Tell\Contracts\CanTraceTellExecution;
use Cognesy\Tell\Configuration\TellPaths;
use Cognesy\Tell\Workspace\Branch\TellBranch;
use Cognesy\Tell\Workspace\Branch\TellBranchConfiguration;
use Cognesy\Tell\Workspace\Branch\TellBranches;
use Cognesy\Tell\Workspace\TellConversation;
use Cognesy\Tell\Workspace\TellRef;
use Cognesy\Tell\Workspace\WorkspaceRepository;

/** Filesystem-backed conversation and branch facade factory. */
final readonly class FilesystemTellConversations implements CanAccessTellConversations
{
    public function __construct(
        private CanBuildTellAgent $agents,
        private CanRunTell $runner,
        private CanTraceTellExecution $tracer,
        private WorkspaceRepository $workspaces,
        private TellPaths $paths,
    ) {}

    #[\Override]
    public function main(string $directory): TellConversation {
        return new TellConversation($this->runner, $this->agents, $this->tracer, $this->workspaces, $directory);
    }

    #[\Override]
    public function conversation(string $directory, string $name): TellConversation {
        return new TellConversation($this->runner, $this->agents, $this->tracer, $this->workspaces, $directory, $name);
    }

    #[\Override]
    public function branches(string $directory): TellBranches {
        return new TellBranches($this->workspaces, $directory);
    }

    #[\Override]
    public function branch(string $directory, string $name): TellBranch {
        return new TellBranch($this->agents, $this->workspaces, $directory, $name);
    }

    #[\Override]
    public function ref(string $directory, string $hash): TellRef {
        return new TellRef($this->agents, $this->workspaces, $directory, $hash);
    }

    #[\Override]
    public function configuration(string $directory, ?string $branch = null): TellBranchConfiguration {
        return new TellBranchConfiguration($this->workspaces, $this->paths, $directory, $branch);
    }
}
