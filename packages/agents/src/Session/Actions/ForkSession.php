<?php declare(strict_types=1);

namespace Cognesy\Agents\Session\Actions;

use Cognesy\Agents\Session\AgentSession;
use Cognesy\Agents\Session\AgentSessionInfo;
use Cognesy\Agents\Session\Contracts\CanExecuteSessionAction;
use Cognesy\Agents\Session\SessionId;

final readonly class ForkSession implements CanExecuteSessionAction
{
    public function __construct(
        private ?SessionId $forkedSessionId = null,
    ) {}

    #[\Override]
    public function executeOn(AgentSession $session): AgentSession {
        $parentId = SessionId::from($session->sessionId());
        $forkedId = $this->forkedSessionId ?? SessionId::generate();

        return new AgentSession(
            header: AgentSessionInfo::fresh(
                sessionId: $forkedId,
                agentName: $session->info()->agentName(),
                agentLabel: $session->info()->agentLabel(),
                parentId: $parentId,
            ),
            definition: $session->definition(),
            state: $session->state()->forNextExecution(),
        );
    }
}
