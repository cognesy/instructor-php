<?php declare(strict_types=1);

namespace Cognesy\Agents\Session\Actions;

use Cognesy\Agents\Session\AgentSession;
use Cognesy\Agents\Session\Contracts\CanExecuteSessionAction;
use Cognesy\Agents\Template\Contracts\CanInstantiateAgentLoop;
use Cognesy\Messages\Message;

final readonly class SendMessage implements CanExecuteSessionAction
{
    public function __construct(
        private string|Message $message,
        private CanInstantiateAgentLoop $loopFactory,
    ) {}

    #[\Override]
    public function executeOn(AgentSession $session): AgentSession {
        $loop = $this->loopFactory->instantiateAgentLoop($session->definition());
        $state = $session->state()->withUserMessage($this->message);
        $nextState = $loop->execute($state);
        return $session->withState($nextState);
    }
}
