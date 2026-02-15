<?php declare(strict_types=1);

namespace Cognesy\Agents\Tool\Traits;

use Cognesy\Agents\Data\AgentState;

trait HasAgentState
{
    protected ?AgentState $agentState = null;

    #[\Override]
    public function withAgentState(AgentState $state): static {
        $clone = clone $this;
        $clone->agentState = $state;
        return $clone;
    }
}
