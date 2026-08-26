<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Tell\Contracts\CanManageTellWorkspace;
use Cognesy\Tell\TellWorkspaceInfo;
use InvalidArgumentException;

/** Process-local workspace manager for fast conformance and embedding tests. */
final class InMemoryTellWorkspaceManager implements CanManageTellWorkspace
{
    /** @var array<string, TellWorkspaceInfo> */
    private array $workspaces = [];

    public function initialize(string $directory): TellWorkspaceInfo
    {
        $root = $this->root($directory);
        $current = $this->workspaces[$root] ?? null;
        if ($current !== null) {
            return new TellWorkspaceInfo($current->root, $current->schema, false);
        }
        return $this->workspaces[$root] = new TellWorkspaceInfo($root, WorkspacePaths::SCHEMA_VERSION, true);
    }

    public function discover(string $directory): ?TellWorkspaceInfo
    {
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

    public function validate(string $directory): TellWorkspaceInfo
    {
        return $this->discover($directory)
            ?? throw new WorkspaceException('Tell workspace is not initialized.');
    }

    private function root(string $directory): string
    {
        if (! is_dir($directory)) {
            throw new InvalidArgumentException("Workspace directory does not exist: {$directory}");
        }
        return realpath($directory) ?: throw new InvalidArgumentException("Workspace directory cannot be resolved safely: {$directory}");
    }
}
