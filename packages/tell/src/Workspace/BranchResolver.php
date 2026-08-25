<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Agents\Session\Data\SessionId;
use InvalidArgumentException;

/** Resolves the one branch ref shared by all non-session workspace workflows. */
final readonly class BranchResolver
{
    public function __construct(private ArenaStore $arena) {}

    public function resolve(?string $requestedBranch = null, ?SessionId $session = null, bool $allowTellOwned = false): BranchSelection
    {
        if ($requestedBranch !== null && $session !== null) {
            throw new InvalidArgumentException('--branch and --session cannot be used together.');
        }
        $branch = $requestedBranch ?? $this->arena->readCurrentBranch()->branch;
        if ($branch !== 'main') {
            $branch = ($allowTellOwned ? BranchName::fromStored($branch) : BranchName::from($branch))->toString();
        }
        $ref = $branch === 'main' ? 'main' : 'branches/'.$branch;
        $reference = $this->arena->readOptionalRef($ref);
        if ($reference === null) {
            throw new WorkspaceException("Tell branch '{$branch}' does not exist.");
        }
        if ($reference->head !== null) {
            $this->arena->get($reference->head);
        }

        return new BranchSelection($branch, $ref, $requestedBranch !== null);
    }
}
