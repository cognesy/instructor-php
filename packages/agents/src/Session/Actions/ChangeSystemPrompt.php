<?php declare(strict_types=1);

namespace Cognesy\Agents\Session\Actions;

use Cognesy\Agents\Session\AgentSession;
use Cognesy\Agents\Session\Contracts\CanExecuteSessionAction;

final readonly class ChangeSystemPrompt implements CanExecuteSessionAction
{
    public function __construct(
        private string $systemPrompt,
    ) {}

    #[\Override]
    public function executeOn(AgentSession $session): AgentSession {
        return $session->withState($session->state()->withSystemPrompt($this->systemPrompt));
    }
}
