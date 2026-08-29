<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Branch\Storage;

use Cognesy\Tell\Workspace\Filesystem\PrivateFilesystem;
use Cognesy\Tell\Workspace\WorkspaceState;

/** Persists the current branch independently from Arena refs. */
final readonly class BranchCurrentSelectionStore
{
    private PrivateFilesystem $files;

    public function __construct(
        private WorkspaceState $workspace,
        ?PrivateFilesystem $files = null,
    ) {
        $this->files = $files ?? PrivateFilesystem::forWorkspace();
    }

    public function read(): BranchCurrentSelection {
        if (!$this->files->exists($this->workspace->paths->currentBranch)) {
            return BranchCurrentSelection::main();
        }

        return BranchCurrentSelection::fromBytes(
            $this->files->read($this->workspace->paths->currentBranch, 'current branch selector'),
        );
    }

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
