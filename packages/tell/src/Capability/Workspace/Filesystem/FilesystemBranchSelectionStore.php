<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Workspace\Filesystem;

use Cognesy\Tell\Core\Contract\Workspace\CanUseTellBranchSelectionStore;
use Cognesy\Tell\Core\Workspace\Branch\Storage\BranchCurrentSelection;
use Cognesy\Tell\Capability\Workspace\Filesystem\PrivateFilesystem;
use Cognesy\Tell\Capability\Workspace\Filesystem\WorkspaceState;
use Override;

/** Persists the current branch independently from Arena refs. */
final readonly class FilesystemBranchSelectionStore implements CanUseTellBranchSelectionStore
{
    private PrivateFilesystem $files;

    public function __construct(
        private WorkspaceState $workspace,
        ?PrivateFilesystem $files = null,
    ) {
        $this->files = $files ?? PrivateFilesystem::forWorkspace();
    }

    #[Override]
    public function read(): BranchCurrentSelection {
        if (!$this->files->exists($this->workspace->paths->currentBranch)) {
            return BranchCurrentSelection::main();
        }

        return BranchCurrentSelection::fromBytes(
            $this->files->read($this->workspace->paths->currentBranch, 'current branch selector'),
        );
    }

    #[Override]
    public function write(string $branch): BranchCurrentSelection {
        $selection = new BranchCurrentSelection($branch);

        return $this->files->withExclusiveLock(
            $this->workspace->paths->locks . DIRECTORY_SEPARATOR . 'current-branch.lock',
            'current branch lock',
            function () use ($selection): BranchCurrentSelection {
                $this->files->writeAtomically(
                    $this->workspace->paths->currentBranch,
                    $selection->toBytes(),
                    'current branch selector',
                    true,
                );

                return $this->read();
            },
        );
    }
}
