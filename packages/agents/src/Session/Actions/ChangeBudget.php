<?php declare(strict_types=1);

namespace Cognesy\Agents\Session\Actions;

use Cognesy\Agents\Data\AgentBudget;
use Cognesy\Agents\Session\Contracts\CanExecuteSessionAction;
use Cognesy\Agents\Session\Data\AgentSession;

final readonly class ChangeBudget implements CanExecuteSessionAction
{
    public function __construct(
        private AgentBudget $budget,
    ) {}

    #[\Override]
    public function executeOn(AgentSession $session): AgentSession {
        return $session->withState($session->state()->withBudget($this->budget));
    }
}
