<?php declare(strict_types=1);

namespace Cognesy\Agents\Session;

use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Template\Contracts\CanInstantiateAgentState;
use Cognesy\Agents\Template\Data\AgentDefinition;

final readonly class SessionFactory
{
    public function __construct(
        private CanInstantiateAgentState $stateFactory,
    ) {}

    public function create(AgentDefinition $definition, ?AgentState $seed = null): AgentSession {
        $header = AgentSessionInfo::fresh(
            sessionId: SessionId::generate(),
            agentName: $definition->name,
            agentLabel: $definition->label(),
        );

        $state = $this->stateFactory->instantiateAgentState($definition, $seed);

        return new AgentSession(
            header: $header,
            definition: $definition,
            state: $state,
        );
    }
}
