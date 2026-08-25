<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Workspace\BranchSelection;
use Cognesy\Tell\Workspace\TellWorkspace;

/** Tell-owned authority for one sequential delegated child execution. */
final readonly class TellDelegationScope
{
    public function __construct(
        public TellWorkspace $workspace,
        public BranchSelection $parent,
        public int $depth = 0,
        public ?CanProvideCancellationSignal $cancellation = null,
    ) {}
}
