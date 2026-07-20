<?php declare(strict_types=1);

namespace Cognesy\Agents\Session\Actions;

use Cognesy\Agents\Session\Contracts\CanExecuteSessionAction;
use Cognesy\Agents\Session\Data\AgentSession;
use Override;

final readonly class SuspendSession implements CanExecuteSessionAction
{
    #[Override]
    public function executeOn(AgentSession $session): AgentSession {
        return $session->suspended();
    }
}
