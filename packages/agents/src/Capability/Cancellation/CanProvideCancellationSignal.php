<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Cancellation;

use Cognesy\Agents\Continuation\StopSignal;
use Cognesy\Agents\Data\AgentState;

interface CanProvideCancellationSignal
{
    public function cancellationSignal(AgentState $state): ?StopSignal;
}
