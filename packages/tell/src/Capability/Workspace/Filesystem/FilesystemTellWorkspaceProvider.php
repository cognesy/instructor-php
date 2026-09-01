<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Workspace\Filesystem;

use Cognesy\Tell\Core\Contract\Workspace\CanProvideTellWorkspace;
use Cognesy\Tell\Data\TellWorkspaceInfo;
use Cognesy\Tell\Capability\Workspace\Filesystem\FilesystemArena;
use Cognesy\Tell\Core\Workspace\Branch\BranchResolver;
use Cognesy\Tell\Capability\Workspace\Filesystem\FilesystemBranchConfigurationStore;
use Cognesy\Tell\Data\TellBranchConfig;
use Cognesy\Tell\Core\Workspace\WorkspaceException;
use Cognesy\Tell\Core\Workspace\TellWorkspaceContext;
use Cognesy\Tell\Capability\Workspace\Filesystem\WorkspaceRepository;
use Override;

/** Cohesive owner of the existing canonical filesystem workspace semantics. */
final readonly class FilesystemTellWorkspaceProvider implements CanProvideTellWorkspace
{
    public function __construct(private WorkspaceRepository $workspaces) {}

    #[Override]
    public function initialize(string $directory): TellWorkspaceInfo {
        $result = $this->workspaces->initialize($directory);

        return new TellWorkspaceInfo($result->workspace->paths->root, $result->workspace->schema, $result->created, $result->workspace->paths->arena);
    }

    #[Override]
    public function discover(string $directory): ?TellWorkspaceInfo {
        $workspace = $this->workspaces->discover($directory);

        return $workspace === null ? null : new TellWorkspaceInfo($workspace->paths->root, $workspace->schema, false, $workspace->paths->arena);
    }

    #[Override]
    public function validate(string $directory): TellWorkspaceInfo {
        $workspace = $this->workspaces->discover($directory)
            ?? throw new WorkspaceException('Tell workspace is not initialized.');
        $workspace = $this->workspaces->validate($workspace);

        return new TellWorkspaceInfo($workspace->paths->root, $workspace->schema, false, $workspace->paths->arena);
    }

    #[Override]
    public function open(string $directory): TellWorkspaceContext {
        $workspace = $this->workspaces->discover($directory)
            ?? throw new WorkspaceException('Tell workspace is not initialized.');

        return new TellWorkspaceContext(
            info: new TellWorkspaceInfo($workspace->paths->root, $workspace->schema, false, $workspace->paths->arena),
            arena: new FilesystemArena($workspace),
            branchSelection: new FilesystemBranchSelectionStore($workspace),
            branchConfiguration: new FilesystemBranchConfigurationStore($workspace),
        );
    }

    #[Override]
    public function read(string $directory, ?string $branch = null): ?TellBranchConfig {
        $workspace = $this->workspaces->discover($directory);
        if ($workspace === null) {
            return null;
        }
        $opened = $this->open($directory);
        $selected = (new BranchResolver($opened->arena, $opened->branchSelection))->resolve($branch)->branch;
        $config = $opened->branchConfiguration->read($selected);

        return new TellBranchConfig($selected, $config['version'], $config['values']);
    }
}
