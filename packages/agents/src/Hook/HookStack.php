<?php declare(strict_types=1);

namespace Cognesy\Agents\Hook;

use Cognesy\Agents\Events\HookContractViolated;
use Cognesy\Agents\Events\HookExecuted;
use Cognesy\Agents\Exceptions\HookContractViolationException;
use Cognesy\Agents\Hook\Collections\HookTriggers;
use Cognesy\Agents\Hook\Collections\RegisteredHooks;
use Cognesy\Agents\Hook\Contracts\HookInterface;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Agents\Hook\Data\RegisteredHook;
use Cognesy\Agents\Interception\CanInterceptAgentLifecycle;
use Cognesy\Events\Contracts\CanHandleEvents;
use DateTimeImmutable;
use Override;

/**
 * @phpstan-consistent-constructor the private `copy()` helper relies on `new static()`;
 *     the only subclass in this repo (an anonymous class in HookStackTest) does not
 *     override the constructor, so the promise holds.
 */
class HookStack implements CanInterceptAgentLifecycle
{
    private RegisteredHooks $hooks;
    private ?CanHandleEvents $events;
    private bool $contractDiagnostics;
    private bool $strictContracts;

    public function __construct(
        RegisteredHooks $hooks,
        ?CanHandleEvents $events = null,
        bool $contractDiagnostics = false,
        bool $strictContracts = false,
    ) {
        $this->hooks = $hooks;
        $this->events = $events;
        $this->contractDiagnostics = $contractDiagnostics;
        $this->strictContracts = $strictContracts;
    }

    public function with(HookInterface $hook, HookTriggers $triggerTypes, int $priority = 0, ?string $name = null): self {
        $registeredHook = new RegisteredHook($hook, $triggerTypes, $priority, $name);
        return $this->withHook($registeredHook);
    }

    public function withHook(RegisteredHook $hook): self {
        return $this->copy(hooks: $this->hooks->withHook($hook));
    }

    /** @param callable(RegisteredHook): RegisteredHook $mapper */
    public function mapHooks(callable $mapper): self {
        $mapped = array_map($mapper, $this->hooks->hooks());
        return $this->copy(hooks: new RegisteredHooks(...$mapped));
    }

    public function withEventHandler(CanHandleEvents $events): self {
        return $this->copy(events: $events);
    }

    public function withContractDiagnostics(): self {
        return $this->copy(contractDiagnostics: true);
    }

    public function strict(): self {
        return $this->copy(contractDiagnostics: true, strictContracts: true);
    }

    /** @return list<RegisteredHook> */
    public function hooks(): array {
        return $this->hooks->hooks();
    }

    #[Override]
    public function intercept(HookContext $context): HookContext {
        $registeredHooks = $this->hooks->hooks();
        foreach ($registeredHooks as $hookRegistration) {
            if (!$hookRegistration->triggersOn($context->triggerType())) {
                continue;
            }
            $startedAt = new DateTimeImmutable();
            $next = $hookRegistration->handle($context);
            $this->validateContract($context, $next, $hookRegistration);
            $context = $next;
            $this->events?->dispatch(new HookExecuted(
                triggerType: $context->triggerType()->value,
                hookName: $hookRegistration->name(),
                startedAt: $startedAt,
            ));
        }
        return $context;
    }

    private function validateContract(
        HookContext $before,
        HookContext $after,
        RegisteredHook $hook,
    ): void {
        if (!$this->contractDiagnostics) {
            return;
        }
        foreach ($before->disallowedChangesIn($after) as $field) {
            $this->events?->dispatch(new HookContractViolated(
                trigger: $before->triggerType(),
                hookName: $hook->name(),
                field: $field,
            ));
            if ($this->strictContracts) {
                throw new HookContractViolationException(
                    trigger: $before->triggerType(),
                    hookName: $hook->name(),
                    field: $field,
                );
            }
        }
    }

    private function copy(
        ?RegisteredHooks $hooks = null,
        ?CanHandleEvents $events = null,
        ?bool $contractDiagnostics = null,
        ?bool $strictContracts = null,
    ): static {
        return new static(
            hooks: $hooks ?? $this->hooks,
            events: $events ?? $this->events,
            contractDiagnostics: $contractDiagnostics ?? $this->contractDiagnostics,
            strictContracts: $strictContracts ?? $this->strictContracts,
        );
    }
}
