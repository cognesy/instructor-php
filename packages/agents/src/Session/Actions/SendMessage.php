<?php declare(strict_types=1);

namespace Cognesy\Agents\Session\Actions;

use Cognesy\Agents\Session\Contracts\CanExecuteSessionAction;
use Cognesy\Agents\Session\Data\AgentSession;
use Cognesy\Agents\Template\Contracts\CanInstantiateAgentLoop;
use Cognesy\Messages\Message;

final readonly class SendMessage implements CanExecuteSessionAction
{
    private string|Message $message;

    public function __construct(
        string|\Stringable|Message $message,
        private CanInstantiateAgentLoop $loopFactory,
    ) {
        $this->message = $message instanceof Message ? $message : (string) $message;
    }

    #[\Override]
    public function executeOn(AgentSession $session): AgentSession {
        $loop = $this->loopFactory->instantiateAgentLoop($session->definition());
        $state = $session->state()->withUserMessage($this->message);
        $nextState = $loop->execute($state);
        return $session->withState($nextState);
    }
}
