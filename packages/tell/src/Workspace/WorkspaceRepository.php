<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Tell\Discovery\StartupScanCounter;
use Cognesy\Tell\Workspace\Arena\Ref;
use Cognesy\Tell\Workspace\Branch\Storage\BranchCurrentSelection;
use Cognesy\Tell\Workspace\Filesystem\PrivateFilesystem;
use InvalidArgumentException;

final class WorkspaceRepository
{
    private readonly PrivateFilesystem $files;

    public function __construct(
        private readonly ?StartupScanCounter $startupScans = null,
        ?PrivateFilesystem $files = null,
    ) {
        $this->files = $files ?? PrivateFilesystem::forWorkspace();
    }

    public function initialize(string $directory): WorkspaceInitialization {
        $paths = $this->pathsForDirectory($directory);
        // Keyed on the schema record for the same reason discovery is: a bare
        // .tell directory is not a workspace. Other things live there - spilled
        // tool output, Tell's own user storage under $HOME - and treating one
        // of those as an existing workspace would report a broken workspace
        // and leave the directory permanently uninitializable.
        if ($this->files->exists($paths->schema)) {
            return new WorkspaceInitialization($this->read($paths), false);
        }

        $this->files->ensureDirectory($paths->marker, 'workspace marker');
        $this->files->ensureDirectory($paths->arena, 'workspace arena');
        $this->files->ensureDirectory($paths->objects, 'workspace objects');
        $this->files->ensureDirectory($paths->refs, 'workspace refs');
        $this->files->ensureDirectory($paths->locks, 'workspace locks');
        $this->files->writeNew($paths->schema, WorkspacePaths::SCHEMA_VERSION . "\n", 'workspace schema');
        $this->files->writeNew($paths->mainRef, Ref::empty()->toBytes(), 'main branch ref');
        $this->files->writeNew($paths->currentBranch, BranchCurrentSelection::main()->toBytes(), 'current branch selector');

        return new WorkspaceInitialization($this->read($paths), true);
    }

    public function discover(string $directory): ?WorkspaceState {
        $this->startupScans?->recordWorkspaceDiscovery();
        $path = $this->pathsForDirectory($directory)->root;

        while (true) {
            $paths = new WorkspacePaths($path);
            // The schema record identifies a workspace, not the bare .tell
            // directory. Tell's own user storage root is $HOME/.tell, so the
            // first run writes an execution trace there and creates it; keying
            // discovery on the marker alone would turn $HOME into a broken
            // workspace for every project beneath it. Anything that does carry
            // a schema is still validated strictly by read().
            if ($this->files->exists($paths->schema)) {
                return $this->read($paths);
            }

            $parent = dirname($path);
            if ($parent === $path) {
                return null;
            }
            $path = $parent;
        }
    }

    public function validate(WorkspaceState $workspace): WorkspaceState {
        return $this->read($workspace->paths);
    }

    private function pathsForDirectory(string $directory): WorkspacePaths {
        if (!is_dir($directory)) {
            throw new InvalidArgumentException("Workspace directory does not exist: {$directory}");
        }
        $resolved = realpath($directory);
        if ($resolved === false) {
            throw new InvalidArgumentException("Workspace directory cannot be resolved safely: {$directory}");
        }

        return new WorkspacePaths($resolved);
    }

    private function read(WorkspacePaths $paths): WorkspaceState {
        $this->files->assertDirectory($paths->marker, 'workspace marker');
        $this->files->assertDirectory($paths->arena, 'workspace arena');
        $this->files->assertFile($paths->schema, 'workspace schema');
        $this->files->assertDirectory($paths->objects, 'workspace objects');
        $this->files->assertDirectory($paths->refs, 'workspace refs');
        $this->files->assertDirectory($paths->locks, 'workspace locks');
        $this->files->assertFile($paths->mainRef, 'main branch ref');
        // The selector is optional until an explicit checkout writes it.
        if ($this->files->exists($paths->currentBranch)) {
            $this->files->assertFile($paths->currentBranch, 'current branch selector');
        }

        $contents = $this->files->read($paths->schema, 'workspace schema');
        $schema = trim($contents);
        if (!ctype_digit($schema)) {
            throw new WorkspaceException("Malformed Tell workspace schema: {$paths->schema}");
        }
        if ((int) $schema !== WorkspacePaths::SCHEMA_VERSION) {
            throw new WorkspaceException(
                "Unsupported Tell workspace schema {$schema}; supported schema is " . WorkspacePaths::SCHEMA_VERSION . '.',
            );
        }

        return new WorkspaceState($paths, (int) $schema);
    }
}
