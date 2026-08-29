<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Workspace\Branch\ResolvedBranch;
use Cognesy\Tell\Workspace\WorkspaceState;

/** Tell-owned authority for one sequential delegated child execution. */
final readonly class TellDelegationScope
{
    public function __construct(
        public WorkspaceState $workspace,
        public ResolvedBranch $parent,
        public int $depth = 0,
        public ?CanProvideCancellationSignal $cancellation = null,
    ) {}
}
