<?php declare(strict_types=1);

namespace Cognesy\Agents\Hooks\Interceptors;

use Closure;
use Cognesy\Agents\Hooks\Collections\HookTriggers;
use Cognesy\Agents\Hooks\Collections\RegisteredHooks;
use Cognesy\Agents\Hooks\Contracts\HookInterface;
use Cognesy\Agents\Hooks\Data\HookContext;
use Cognesy\Agents\Hooks\Data\RegisteredHook;
use DateTimeImmutable;

class HookStack implements CanInterceptAgentLifecycle
{
    private RegisteredHooks $hooks;

    /** @var Closure(string, ?string, DateTimeImmutable): void|null */
    private ?Closure $onHookExecuted;

    /**
     * @param Closure(string, ?string, DateTimeImmutable): void|null $onHookExecuted
     */
    public function __construct(RegisteredHooks $hooks, ?Closure $onHookExecuted = null) {
        $this->hooks = $hooks;
        $this->onHookExecuted = $onHookExecuted;
    }

    public function with(HookInterface $hook, HookTriggers $triggerTypes, int $priority = 0, ?string $name = null): self {
        $registeredHook = new RegisteredHook($hook, $triggerTypes, $priority, $name);
        return $this->withHook($registeredHook);
    }

    public function withHook(RegisteredHook $hook): self {
        return new self(hooks: $this->hooks->withHook($hook), onHookExecuted: $this->onHookExecuted);
    }

    /** @return list<RegisteredHook> */
    public function hooks(): array {
        return $this->hooks->hooks();
    }

    public function intercept(HookContext $context): HookContext {
        $registeredHooks = $this->hooks->hooks();
        foreach ($registeredHooks as $hookRegistration) {
            if (!$hookRegistration->triggersOn($context->triggerType())) {
                continue;
            }
            $startedAt = new DateTimeImmutable();
            $context = $hookRegistration->handle($context);
            $this->onHookExecuted?->call(
                $this,
                $context->triggerType()->value,
                $hookRegistration->name(),
                $startedAt,
            );
        }
        return $context;
    }
}
