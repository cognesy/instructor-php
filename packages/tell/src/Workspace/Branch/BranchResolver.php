<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Branch;

use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Tell\Workspace\Arena\CanUseArena;
use Cognesy\Tell\Workspace\Branch\Storage\BranchCurrentSelectionStore;
use Cognesy\Tell\Workspace\WorkspaceException;
use Cognesy\Tell\Workspace\WorkspaceState;
use InvalidArgumentException;

/** Resolves the one branch ref shared by all non-session workspace workflows. */
final readonly class BranchResolver
{
    public function __construct(
        private CanUseArena $arena,
        WorkspaceState $workspace,
    ) {
        $this->current = new BranchCurrentSelectionStore($workspace);
    }

    private BranchCurrentSelectionStore $current;

    public function resolve(?string $requestedBranch = null, ?SessionId $session = null, bool $allowTellOwned = false): ResolvedBranch {
        if ($requestedBranch !== null && $session !== null) {
            throw new InvalidArgumentException('--branch and --session cannot be used together.');
        }
        $branch = $requestedBranch ?? $this->current->read()->branch;
        if ($branch !== 'main') {
            $branch = ($allowTellOwned ? BranchName::fromStored($branch) : BranchName::from($branch))->toString();
        }
        $ref = $branch === 'main' ? 'main' : 'branches/' . $branch;
        $reference = $this->arena->readOptionalRef($ref);
        if ($reference === null) {
            throw new WorkspaceException("Tell branch '{$branch}' does not exist.");
        }
        if ($reference->head !== null) {
            $this->arena->get($reference->head);
        }

        return new ResolvedBranch($branch, $ref, $requestedBranch !== null);
    }
}
