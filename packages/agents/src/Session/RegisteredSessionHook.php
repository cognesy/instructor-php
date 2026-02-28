<?php declare(strict_types=1);

namespace Cognesy\Agents\Session;

use Cognesy\Agents\Session\Contracts\CanControlAgentSession;
use Cognesy\Agents\Session\Data\AgentSession;
use Cognesy\Agents\Session\Enums\AgentSessionStage;

final readonly class RegisteredSessionHook implements CanControlAgentSession
{
    public function __construct(
        private CanControlAgentSession $hook,
        private int $priority = 0,
        private ?string $name = null,
    ) {}

    public function priority(): int {
        return $this->priority;
    }

    public function name(): string {
        return $this->name ?? $this->hook::class;
    }

    #[\Override]
    public function onStage(AgentSessionStage $stage, AgentSession $session): AgentSession {
        return $this->hook->onStage($stage, $session);
    }
}
