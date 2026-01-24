<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\StateProcessing;

use Cognesy\Agents\Agent\Data\AgentState;

/**
 * Interface for classes that can apply processing steps to AgentState.
 */
interface CanApplyProcessors
{
    /**
     * Apply processing steps to the state.
     *
     * @param (callable(AgentState): AgentState)|null $terminalFn
     */
    public function apply(AgentState $state, ?callable $terminalFn = null): AgentState;
}
