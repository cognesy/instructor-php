<?php declare(strict_types=1);

namespace Cognesy\Agents\Hooks;

class HookStack implements CanInterceptAgentLifecycle
{
    private RegisteredHooks $hooks;

    public function __construct(RegisteredHooks $hooks) {
        $this->hooks = $hooks;
    }

    public function with(HookInterface $hook, HookTriggers $triggerTypes, int $priority = 0, ?string $name = null): self {
        $registeredHook = new RegisteredHook($hook, $triggerTypes, $priority, $name);
        return $this->withHook($registeredHook);
    }

    public function withHook(RegisteredHook $hook): self {
        return new self(hooks: $this->hooks->withHook($hook));
    }

    public function intercept(HookContext $context): HookContext {
        $registeredHooks = $this->hooks->hooks();
        foreach ($registeredHooks as $hookRegistration) {
            $context = $hookRegistration->tryOn($context);
        }
        return $context;
    }
}