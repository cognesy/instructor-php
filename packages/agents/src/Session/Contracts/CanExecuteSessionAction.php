<?php declare(strict_types=1);

namespace Cognesy\Agents\Session\Contracts;

use Cognesy\Agents\Session\AgentSession;

interface CanExecuteSessionAction
{
    public function executeOn(AgentSession $session): AgentSession;
}
