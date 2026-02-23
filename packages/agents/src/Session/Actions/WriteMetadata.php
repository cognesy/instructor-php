<?php declare(strict_types=1);

namespace Cognesy\Agents\Session\Actions;

use Cognesy\Agents\Session\Contracts\CanExecuteSessionAction;
use Cognesy\Agents\Session\Data\AgentSession;

final readonly class WriteMetadata implements CanExecuteSessionAction
{
    public function __construct(
        private string $key,
        private mixed $value,
    ) {}

    #[\Override]
    public function executeOn(AgentSession $session): AgentSession {
        return $session->withState($session->state()->withMetadata($this->key, $this->value));
    }
}
