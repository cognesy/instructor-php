<?php

declare(strict_types=1);

namespace Cognesy\Tell\Composition;

use Cognesy\Tell\Contracts\CanAccessTellConversations;
use Cognesy\Tell\Contracts\CanManageTellWorkspace;
use Cognesy\Tell\Contracts\CanReadTellBranchConfiguration;
use Cognesy\Tell\Data\TellWorkspaceInfo;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Tell;
use Cognesy\Tell\Workspace\Arena\FilesystemArena;
use Cognesy\Tell\Workspace\Branch\BranchResolver;
use Cognesy\Tell\Workspace\Branch\Storage\BranchConfigStore;
use Cognesy\Tell\Workspace\Branch\TellBranch;
use Cognesy\Tell\Workspace\Branch\TellBranchConfig;
use Cognesy\Tell\Workspace\Branch\TellBranches;
use Cognesy\Tell\Workspace\TellConversation;
use Cognesy\Tell\Workspace\TellRef;
use Cognesy\Tell\Workspace\WorkspaceException;
use Override;

/** Cohesive owner of the existing canonical filesystem workspace semantics. */
final readonly class FilesystemWorkspaceModule implements CanAccessTellConversations, CanManageTellWorkspace, CanReadTellBranchConfiguration
{
    public function __construct(private TellAgentFactory $agents) {}

    #[Override]
    public function initialize(string $directory): TellWorkspaceInfo {
        $result = $this->agents->workspace()->initialize($directory);

        return new TellWorkspaceInfo($result->workspace->paths->root, $result->workspace->schema, $result->created);
    }

    #[Override]
    public function discover(string $directory): ?TellWorkspaceInfo {
        $workspace = $this->agents->workspace()->discover($directory);

        return $workspace === null ? null : new TellWorkspaceInfo($workspace->paths->root, $workspace->schema, false);
    }

    #[Override]
    public function validate(string $directory): TellWorkspaceInfo {
        $workspace = $this->agents->workspace()->discover($directory)
            ?? throw new WorkspaceException('Tell workspace is not initialized.');
        $workspace = $this->agents->workspace()->validate($workspace);

        return new TellWorkspaceInfo($workspace->paths->root, $workspace->schema, false);
    }

    #[Override]
    public function main(string $directory): TellConversation {
        return new TellConversation($this->forDirectory($directory), $this->agents, $directory);
    }

    #[Override]
    public function conversation(string $directory, string $name): TellConversation {
        return new TellConversation($this->forDirectory($directory), $this->agents, $directory, $name);
    }

    #[Override]
    public function branches(string $directory): TellBranches {
        return new TellBranches($this->agents, $directory);
    }

    #[Override]
    public function branch(string $directory, string $name): TellBranch {
        return new TellBranch($this->agents, $directory, $name);
    }

    #[Override]
    public function ref(string $directory, string $hash): TellRef {
        return new TellRef($this->agents, $directory, $hash);
    }

    #[Override]
    public function read(string $directory, ?string $branch = null): ?TellBranchConfig {
        $workspace = $this->agents->workspace()->discover($directory);
        if ($workspace === null) {
            return null;
        }
        $selected = (new BranchResolver(new FilesystemArena($workspace), $workspace))->resolve($branch)->branch;
        $config = (new BranchConfigStore($workspace))->read($selected);

        return new TellBranchConfig($selected, $config['version'], $config['values']);
    }

    private function forDirectory(string $directory): Tell {
        return Tell::open($directory, $this->agents);
    }
}
