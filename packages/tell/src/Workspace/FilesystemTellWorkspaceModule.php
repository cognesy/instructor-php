<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Tell\Branch\TellBranch;
use Cognesy\Tell\Branch\TellBranchConfig;
use Cognesy\Tell\Branch\TellBranches;
use Cognesy\Tell\Branch\TellRef;
use Cognesy\Tell\Contracts\CanAccessTellConversations;
use Cognesy\Tell\Contracts\CanManageTellWorkspace;
use Cognesy\Tell\Contracts\CanReadTellBranchConfiguration;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Tell;
use Cognesy\Tell\TellConversation;
use Cognesy\Tell\TellWorkspaceInfo;

/** Cohesive owner of the existing canonical filesystem workspace semantics. */
final readonly class FilesystemTellWorkspaceModule implements CanAccessTellConversations, CanManageTellWorkspace, CanReadTellBranchConfiguration
{
    public function __construct(private TellAgentFactory $agents) {}

    public function initialize(string $directory): TellWorkspaceInfo
    {
        $result = $this->agents->workspace()->initialize($directory);

        return new TellWorkspaceInfo($result->workspace->paths->root, $result->workspace->schema, $result->created);
    }

    public function discover(string $directory): ?TellWorkspaceInfo
    {
        $workspace = $this->agents->workspace()->discover($directory);

        return $workspace === null ? null : new TellWorkspaceInfo($workspace->paths->root, $workspace->schema, false);
    }

    public function validate(string $directory): TellWorkspaceInfo
    {
        $workspace = $this->agents->workspace()->discover($directory)
            ?? throw new WorkspaceException('Tell workspace is not initialized.');
        $workspace = $this->agents->workspace()->validate($workspace);

        return new TellWorkspaceInfo($workspace->paths->root, $workspace->schema, false);
    }

    public function main(string $directory): TellConversation
    {
        return new TellConversation($this->forDirectory($directory), $this->agents, $directory);
    }

    public function conversation(string $directory, string $name): TellConversation
    {
        return new TellConversation($this->forDirectory($directory), $this->agents, $directory, $name);
    }

    public function branches(string $directory): TellBranches
    {
        return new TellBranches($this->agents, $directory);
    }

    public function branch(string $directory, string $name): TellBranch
    {
        return new TellBranch($this->agents, $directory, $name);
    }

    public function ref(string $directory, string $hash): TellRef
    {
        return new TellRef($this->agents, $directory, $hash);
    }

    public function read(string $directory, ?string $branch = null): ?TellBranchConfig
    {
        $workspace = $this->agents->workspace()->discover($directory);
        if ($workspace === null) {
            return null;
        }
        $selected = (new BranchResolver(new ArenaStore($workspace)))->resolve($branch)->branch;
        $config = (new BranchConfigStore($workspace))->read($selected);

        return new TellBranchConfig($selected, $config['version'], $config['values']);
    }

    private function forDirectory(string $directory): Tell
    {
        return Tell::open($directory, $this->agents);
    }
}
