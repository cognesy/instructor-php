<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Tell\Diagnostics\StartupScanCounter;
use InvalidArgumentException;

final class WorkspaceManager
{
    public function __construct(private readonly ?StartupScanCounter $startupScans = null) {}

    public function initialize(string $directory): WorkspaceInitialization
    {
        $paths = $this->pathsForDirectory($directory);
        // Keyed on the schema record for the same reason discovery is: a bare
        // .tell directory is not a workspace. Other things live there - spilled
        // tool output, Tell's own user storage under $HOME - and treating one
        // of those as an existing workspace would report a broken workspace
        // and leave the directory permanently uninitializable.
        if ($this->exists($paths->schema)) {
            return new WorkspaceInitialization($this->read($paths), false);
        }

        $this->ensurePrivateDirectory($paths->marker, 'workspace marker');
        $this->ensurePrivateDirectory($paths->arena, 'workspace arena');
        $this->ensurePrivateDirectory($paths->objects, 'workspace objects');
        $this->ensurePrivateDirectory($paths->refs, 'workspace refs');
        $this->ensurePrivateDirectory($paths->locks, 'workspace locks');
        $this->writePrivateFile($paths->schema, WorkspacePaths::SCHEMA_VERSION."\n", 'workspace schema');
        $this->writePrivateFile($paths->mainRef, ArenaRef::empty()->toBytes(), 'main branch ref');
        $this->writePrivateFile($paths->currentBranch, CurrentBranchSelector::main()->toBytes(), 'current branch selector');

        return new WorkspaceInitialization($this->read($paths), true);
    }

    public function discover(string $directory): ?TellWorkspace
    {
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
            if ($this->exists($paths->schema)) {
                return $this->read($paths);
            }

            $parent = dirname($path);
            if ($parent === $path) {
                return null;
            }
            $path = $parent;
        }
    }

    public function validate(TellWorkspace $workspace): TellWorkspace
    {
        return $this->read($workspace->paths);
    }

    private function pathsForDirectory(string $directory): WorkspacePaths
    {
        if (! is_dir($directory)) {
            throw new InvalidArgumentException("Workspace directory does not exist: {$directory}");
        }
        $resolved = realpath($directory);
        if ($resolved === false) {
            throw new InvalidArgumentException("Workspace directory cannot be resolved safely: {$directory}");
        }

        return new WorkspacePaths($resolved);
    }

    private function read(WorkspacePaths $paths): TellWorkspace
    {
        $this->assertDirectory($paths->marker, 'workspace marker');
        $this->assertDirectory($paths->arena, 'workspace arena');
        $this->assertFile($paths->schema, 'workspace schema');
        $this->assertDirectory($paths->objects, 'workspace objects');
        $this->assertDirectory($paths->refs, 'workspace refs');
        $this->assertDirectory($paths->locks, 'workspace locks');
        $this->assertFile($paths->mainRef, 'main branch ref');
        // P1 workspaces add this selector. Its absence is a read-only legacy
        // main selection until an explicit checkout writes the new record.
        if ($this->exists($paths->currentBranch)) {
            $this->assertFile($paths->currentBranch, 'current branch selector');
        }

        $contents = file_get_contents($paths->schema);
        if ($contents === false) {
            throw new WorkspaceException("Unable to read Tell workspace schema: {$paths->schema}");
        }
        $schema = trim($contents);
        if (! ctype_digit($schema)) {
            throw new WorkspaceException("Malformed Tell workspace schema: {$paths->schema}");
        }
        if ((int) $schema !== WorkspacePaths::SCHEMA_VERSION) {
            throw new WorkspaceException(
                "Unsupported Tell workspace schema {$schema}; supported schema is ".WorkspacePaths::SCHEMA_VERSION.'.',
            );
        }

        return new TellWorkspace($paths, (int) $schema);
    }

    private function ensurePrivateDirectory(string $path, string $label): void
    {
        if ($this->exists($path)) {
            $this->assertDirectory($path, $label);

            return;
        }
        if (! @mkdir($path, 0700, true) && ! is_dir($path)) {
            throw new WorkspaceException("Unable to create Tell {$label}: {$path}");
        }
        @chmod($path, 0700);
    }

    private function writePrivateFile(string $path, string $contents, string $label): void
    {
        if ($this->exists($path)) {
            throw new WorkspaceException("Tell {$label} already exists: {$path}");
        }
        $handle = @fopen($path, 'x');
        if ($handle === false) {
            throw new WorkspaceException("Unable to create Tell {$label}: {$path}");
        }
        try {
            if (fwrite($handle, $contents) !== strlen($contents)) {
                throw new WorkspaceException("Unable to write Tell {$label}: {$path}");
            }
        } finally {
            fclose($handle);
        }
        @chmod($path, 0600);
    }

    private function assertDirectory(string $path, string $label): void
    {
        if (is_link($path)) {
            throw new WorkspaceException("Unsafe symlinked Tell {$label}: {$path}");
        }
        if (! is_dir($path)) {
            throw new WorkspaceException("Tell {$label} is not a directory: {$path}");
        }
    }

    private function assertFile(string $path, string $label): void
    {
        if (is_link($path)) {
            throw new WorkspaceException("Unsafe symlinked Tell {$label}: {$path}");
        }
        if (! is_file($path)) {
            throw new WorkspaceException("Tell {$label} is not a file: {$path}");
        }
    }

    private function exists(string $path): bool
    {
        return file_exists($path) || is_link($path);
    }
}
