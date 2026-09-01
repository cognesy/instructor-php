<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Agent;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Core\Contract\Agent\CanDescribeTellDelegation;
use Cognesy\Tell\Core\Workspace\Branch\ResolvedBranch;
use Cognesy\Tell\Core\Workspace\TellWorkspaceContext;

/** Tell-owned authority for one sequential delegated child execution. */
final readonly class TellDelegationScope implements CanDescribeTellDelegation
{
    public function __construct(
        public TellWorkspaceContext $workspace,
        public ResolvedBranch $parent,
        public int $depth = 0,
        public ?CanProvideCancellationSignal $cancellation = null,
    ) {}
}
