<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Contracts;

use Cognesy\Agents\Core\Data\AgentState;

/**
 * Exposes state changes captured by observers inside a component.
 */
interface CanReportObserverState
{
    public function observerState(): ?AgentState;
}
