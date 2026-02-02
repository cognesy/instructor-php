<?php declare(strict_types=1);

namespace Cognesy\Agents\Hooks;

class RegisteredHook
{
    public function __construct(
        protected HookInterface $hook,
        protected HookTriggers $triggers,
        protected int $priority = 0,
        protected ?string $name = null,
    ) {}

    public function hook(): HookInterface {
        return $this->hook;
    }

    public function tryOn(HookContext $context): HookContext {
        if (!$this->triggers->triggersOn($context->triggerType())) {
            return $context;
        }
        return $this->hook->handle($context);
    }

    public function priority(): int {
        return $this->priority;
    }

    public function name(): ?string {
        return $this->name;
    }

    public function compare(RegisteredHook $other): int {
        return $other->priority() <=> $this->priority();
    }
}