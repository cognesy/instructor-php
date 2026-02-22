<?php declare(strict_types=1);

namespace Cognesy\Agents\Session\Actions;

use Cognesy\Agents\Session\AgentSession;
use Cognesy\Agents\Session\Contracts\CanExecuteSessionAction;
use Cognesy\Messages\MessageStore\MessageStore;

final readonly class ClearSession implements CanExecuteSessionAction
{
    #[\Override]
    public function executeOn(AgentSession $session): AgentSession {
        $clearedState = $session->state()
            ->withMessageStore(new MessageStore())
            ->forNextExecution();

        return $session->withState($clearedState);
    }
}
