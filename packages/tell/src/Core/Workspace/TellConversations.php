<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Workspace;

use Cognesy\Tell\Core\Contract\Workspace\CanAccessTellConversations;
use Cognesy\Tell\Core\Contract\Workspace\CanOpenTellWorkspace;
use Cognesy\Tell\Core\Contract\Agent\CanBuildTellAgent;
use Cognesy\Tell\Core\Contract\Discovery\CanCatalogueTellProviders;
use Cognesy\Tell\Core\Contract\Execution\CanRunTell;
use Cognesy\Tell\Core\Contract\Observation\CanTraceTellExecution;
use Cognesy\Tell\Core\Paths\TellPaths;
use Cognesy\Tell\Core\Workspace\Branch\TellBranch;
use Cognesy\Tell\Core\Workspace\Branch\TellBranchConfiguration;
use Cognesy\Tell\Core\Workspace\Branch\TellBranches;
use Cognesy\Tell\Core\Workspace\Session\TellSessions;

/** Backend-neutral conversation and branch facade factory. */
final readonly class TellConversations implements CanAccessTellConversations
{
    public function __construct(
        private CanBuildTellAgent $agents,
        private CanRunTell $runner,
        private CanTraceTellExecution $tracer,
        private CanOpenTellWorkspace $workspaces,
        private TellPaths $paths,
        private CanCatalogueTellProviders $providers,
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
    public function current(string $directory): TellBranch {
        $current = (new TellBranches($this->workspaces, $directory))->current();

        return new TellBranch(
            $this->agents,
            $this->tracer,
            $this->workspaces,
            $directory,
            $current->name,
            invocationLocal: false,
        );
    }

    #[\Override]
    public function branches(string $directory): TellBranches {
        return new TellBranches($this->workspaces, $directory);
    }

    #[\Override]
    public function branch(string $directory, string $name): TellBranch {
        return new TellBranch($this->agents, $this->tracer, $this->workspaces, $directory, $name);
    }

    #[\Override]
    public function ref(string $directory, string $hash): TellRef {
        return new TellRef($this->agents, $this->workspaces, $directory, $hash);
    }

    #[\Override]
    public function configuration(string $directory, ?string $branch = null): TellBranchConfiguration {
        return new TellBranchConfiguration($this->workspaces, $this->paths, $this->providers, $directory, $branch);
    }

    #[\Override]
    public function sessions(string $directory): TellSessions {
        return new TellSessions($this->workspaces, $directory);
    }
}
