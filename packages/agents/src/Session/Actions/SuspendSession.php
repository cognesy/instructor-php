<?php declare(strict_types=1);

namespace Cognesy\Agents\Session\Actions;

use Cognesy\Agents\Session\AgentSession;
use Cognesy\Agents\Session\Contracts\CanExecuteSessionAction;

final readonly class SuspendSession implements CanExecuteSessionAction
{
    #[\Override]
    public function executeOn(AgentSession $session): AgentSession {
        return $session->suspended();
    }
}
