<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Workspace\Memory;

use Cognesy\Tell\Core\Contract\Workspace\CanProvideTellWorkspace;
use Cognesy\Tell\Core\Workspace\Branch\BranchResolver;
use Cognesy\Tell\Core\Workspace\TellWorkspaceContext;
use Cognesy\Tell\Core\Workspace\TellWorkspaceSchema;
use Cognesy\Tell\Core\Workspace\WorkspaceException;
use Cognesy\Tell\Data\TellBranchConfig;
use Cognesy\Tell\Data\TellWorkspaceInfo;
use InvalidArgumentException;
use Override;

/** Process-local workspace implementation for fast conformance and embedding tests. */
final class InMemoryTellWorkspaceProvider implements CanProvideTellWorkspace
{
    /** @var array<string, TellWorkspaceInfo> */
    private array $workspaces = [];

    /** @var array<string, TellWorkspaceContext> */
    private array $contexts = [];

    #[Override]
    public function initialize(string $directory): TellWorkspaceInfo {
        $root = $this->root($directory);
        $current = $this->workspaces[$root] ?? null;
        if ($current !== null) {
            return new TellWorkspaceInfo($current->root, $current->schema, false);
        }

        $created = new TellWorkspaceInfo($root, TellWorkspaceSchema::VERSION, true);
        $this->workspaces[$root] = new TellWorkspaceInfo($root, TellWorkspaceSchema::VERSION, false);
        $this->contexts[$root] = new TellWorkspaceContext(
            info: $this->workspaces[$root],
            arena: new InMemoryArena(),
            branchSelection: new InMemoryBranchSelectionStore(),
            branchConfiguration: new InMemoryBranchConfigurationStore(),
        );

        return $created;
    }

    #[Override]
    public function discover(string $directory): ?TellWorkspaceInfo {
        $path = $this->root($directory);
        while (true) {
            if (isset($this->workspaces[$path])) {
                $workspace = $this->workspaces[$path];

                return new TellWorkspaceInfo($workspace->root, $workspace->schema, false);
            }
            $parent = dirname($path);
            if ($parent === $path) {
                return null;
            }
            $path = $parent;
        }
    }

    #[Override]
    public function validate(string $directory): TellWorkspaceInfo {
        return $this->discover($directory)
            ?? throw new WorkspaceException('Tell workspace is not initialized.');
    }

    #[Override]
    public function open(string $directory): TellWorkspaceContext {
        $workspace = $this->discover($directory)
            ?? throw new WorkspaceException('Tell workspace is not initialized.');

        return $this->contexts[$workspace->root];
    }

    #[Override]
    public function read(string $directory, ?string $branch = null): ?TellBranchConfig {
        $workspace = $this->discover($directory);
        if ($workspace === null) {
            return null;
        }
        $opened = $this->open($directory);
        $selected = (new BranchResolver($opened->arena, $opened->branchSelection))->resolve($branch)->branch;
        $config = $opened->branchConfiguration->read($selected);

        return new TellBranchConfig($selected, $config['version'], $config['values']);
    }

    private function root(string $directory): string {
        if (!is_dir($directory)) {
            throw new InvalidArgumentException("Workspace directory does not exist: {$directory}");
        }

        return realpath($directory) ?: throw new InvalidArgumentException("Workspace directory cannot be resolved safely: {$directory}");
    }
}
