<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Tell\Contracts\CanManageTellWorkspace;
use Cognesy\Tell\Contracts\CanReadTellBranchConfiguration;
use Cognesy\Tell\Data\TellWorkspaceInfo;
use Cognesy\Tell\Workspace\Arena\FilesystemArena;
use Cognesy\Tell\Workspace\Branch\BranchResolver;
use Cognesy\Tell\Workspace\Branch\Storage\BranchConfigStore;
use Cognesy\Tell\Workspace\Branch\TellBranchConfig;
use Cognesy\Tell\Workspace\WorkspaceException;
use Cognesy\Tell\Workspace\WorkspaceRepository;
use Override;

/** Cohesive owner of the existing canonical filesystem workspace semantics. */
final readonly class FilesystemWorkspaceProvider implements CanManageTellWorkspace, CanReadTellBranchConfiguration
{
    public function __construct(private WorkspaceRepository $workspaces) {}

    #[Override]
    public function initialize(string $directory): TellWorkspaceInfo {
        $result = $this->workspaces->initialize($directory);

        return new TellWorkspaceInfo($result->workspace->paths->root, $result->workspace->schema, $result->created);
    }

    #[Override]
    public function discover(string $directory): ?TellWorkspaceInfo {
        $workspace = $this->workspaces->discover($directory);

        return $workspace === null ? null : new TellWorkspaceInfo($workspace->paths->root, $workspace->schema, false);
    }

    #[Override]
    public function validate(string $directory): TellWorkspaceInfo {
        $workspace = $this->workspaces->discover($directory)
            ?? throw new WorkspaceException('Tell workspace is not initialized.');
        $workspace = $this->workspaces->validate($workspace);

        return new TellWorkspaceInfo($workspace->paths->root, $workspace->schema, false);
    }

    #[Override]
    public function read(string $directory, ?string $branch = null): ?TellBranchConfig {
        $workspace = $this->workspaces->discover($directory);
        if ($workspace === null) {
            return null;
        }
        $selected = (new BranchResolver(new FilesystemArena($workspace), $workspace))->resolve($branch)->branch;
        $config = (new BranchConfigStore($workspace))->read($selected);

        return new TellBranchConfig($selected, $config['version'], $config['values']);
    }
}
