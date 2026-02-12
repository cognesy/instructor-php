<?php declare(strict_types=1);

namespace Cognesy\Agents\Hooks\Interceptors;

use Cognesy\Agents\Events\HookExecuted;
use Cognesy\Agents\Hooks\Collections\HookTriggers;
use Cognesy\Agents\Hooks\Collections\RegisteredHooks;
use Cognesy\Agents\Hooks\Contracts\HookInterface;
use Cognesy\Agents\Hooks\Data\HookContext;
use Cognesy\Agents\Hooks\Data\RegisteredHook;
use Cognesy\Events\Contracts\CanHandleEvents;
use DateTimeImmutable;

class HookStack implements CanInterceptAgentLifecycle
{
    private RegisteredHooks $hooks;
    private ?CanHandleEvents $events;

    public function __construct(RegisteredHooks $hooks, ?CanHandleEvents $events = null) {
        $this->hooks = $hooks;
        $this->events = $events;
    }

    public function with(HookInterface $hook, HookTriggers $triggerTypes, int $priority = 0, ?string $name = null): self {
        $registeredHook = new RegisteredHook($hook, $triggerTypes, $priority, $name);
        return $this->withHook($registeredHook);
    }

    public function withHook(RegisteredHook $hook): self {
        return new self(hooks: $this->hooks->withHook($hook), events: $this->events);
    }

    /** @return list<RegisteredHook> */
    public function hooks(): array {
        return $this->hooks->hooks();
    }

    #[\Override]
    public function intercept(HookContext $context): HookContext {
        $registeredHooks = $this->hooks->hooks();
        foreach ($registeredHooks as $hookRegistration) {
            if (!$hookRegistration->triggersOn($context->triggerType())) {
                continue;
            }
            $startedAt = new DateTimeImmutable();
            $context = $hookRegistration->handle($context);
            $this->events?->dispatch(new HookExecuted(
                triggerType: $context->triggerType()->value,
                hookName: $hookRegistration->name(),
                startedAt: $startedAt,
            ));
        }
        return $context;
    }
}
