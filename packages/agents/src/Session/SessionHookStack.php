<?php declare(strict_types=1);

namespace Cognesy\Agents\Session;

use Cognesy\Agents\Session\Contracts\CanControlAgentSession;
use Cognesy\Agents\Session\Data\AgentSession;
use Cognesy\Agents\Session\Enums\AgentSessionStage;

final readonly class SessionHookStack implements CanControlAgentSession
{
    /** @var list<RegisteredSessionHook> */
    private array $hooks;

    public function __construct(RegisteredSessionHook ...$hooks) {
        $this->hooks = $hooks;
    }

    public static function empty(): self {
        return new self();
    }

    public function with(CanControlAgentSession $hook, int $priority = 0, ?string $name = null): self {
        $hooks = [...$this->hooks, new RegisteredSessionHook($hook, $priority, $name)];
        usort($hooks, static fn(RegisteredSessionHook $a, RegisteredSessionHook $b): int => $b->priority() <=> $a->priority());
        return new self(...$hooks);
    }

    /** @return list<RegisteredSessionHook> */
    public function hooks(): array {
        return $this->hooks;
    }

    #[\Override]
    public function onStage(AgentSessionStage $stage, AgentSession $session): AgentSession {
        $nextSession = $session;
        foreach ($this->hooks as $hook) {
            $nextSession = $hook->onStage($stage, $nextSession);
        }
        return $nextSession;
    }
}
