<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Workspace\Branch\Storage;

use Cognesy\Tell\Core\Contract\Workspace\CanUseTellWorkspaceArena;
use Cognesy\Tell\Core\Contract\Workspace\CanUseTellBranchSelectionStore;
use Cognesy\Tell\Core\Workspace\Arena\Ref;
use Cognesy\Tell\Core\Workspace\Branch\BranchName;
use Cognesy\Tell\Core\Workspace\WorkspaceException;

/** Branch operations composed from generic Arena refs and branch-owned selection. */
final readonly class BranchStore
{
    public function __construct(
        private CanUseTellWorkspaceArena $arena,
        private CanUseTellBranchSelectionStore $current,
    ) {}

    /** @return list<BranchName> */
    public function names(): array {
        return array_map(
            static fn (string $ref): BranchName => BranchName::fromStored(substr($ref, 9)),
            $this->arena->refNames('branches'),
        );
    }

    public function create(BranchName $name, Ref $reference): Ref {
        return $this->arena->createRef('branches/' . $name->toString(), $reference);
    }

    public function checkout(string $branch): BranchCurrentSelection {
        $branch = $branch === 'main' ? 'main' : BranchName::from($branch)->toString();
        $ref = $branch === 'main' ? 'main' : 'branches/' . $branch;
        $reference = $this->arena->readOptionalRef($ref);
        if ($reference === null) {
            throw new WorkspaceException("Tell branch '{$branch}' does not exist.");
        }
        if ($reference->head !== null) {
            $this->arena->get($reference->head);
        }

        return $this->current->write($branch);
    }

    public function current(): BranchCurrentSelection {
        return $this->current->read();
    }
}
