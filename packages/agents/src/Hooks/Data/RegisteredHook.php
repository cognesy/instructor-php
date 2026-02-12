<?php declare(strict_types=1);

namespace Cognesy\Agents\Hooks\Data;

use Cognesy\Agents\Hooks\Collections\HookTriggers;
use Cognesy\Agents\Hooks\Contracts\HookInterface;
use Cognesy\Agents\Hooks\Enums\HookTrigger;

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

    public function triggersOn(HookTrigger $trigger): bool {
        return $this->triggers->triggersOn($trigger);
    }

    public function handle(HookContext $context): HookContext {
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