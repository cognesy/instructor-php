<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Support;

use Cognesy\Agents\Agent\Agent;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\AgentBuilder\Contracts\AgentInterface;
use Cognesy\Agents\AgentBuilder\Data\AgentDescriptor;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Events\Traits\HandlesEvents;
use Psr\EventDispatcher\EventDispatcherInterface;

abstract class AbstractAgent implements AgentInterface
{
    use HandlesEvents;

    private ?Agent $agent = null;
    /** @var array<int, callable(object): void> */
    private array $wiretaps = [];
    /** @var array<string, array<int, callable(object): void>> */
    private array $listeners = [];

    #[\Override]
    abstract public function descriptor(): AgentDescriptor;

    abstract protected function buildAgent(): Agent;

    #[\Override]
    public function build(): Agent {
        if ($this->agent !== null) {
            return $this->agent;
        }
        $this->agent = $this->buildAgent();
        $this->applyEventHandlerIfProvided();
        $this->applyStoredListeners();
        // $this->agent is reassigned by applyEventHandlerIfProvided(), so we must return it
        assert($this->agent !== null);
        return $this->agent;
    }

    #[\Override]
    public function run(AgentState $state): AgentState {
        return $this->execute($state);
    }

    #[\Override]
    public function execute(AgentState $state): AgentState {
        return $this->build()->execute($state);
    }

    #[\Override]
    public function iterate(AgentState $state): iterable {
        return $this->build()->iterate($state);
    }

    #[\Override]
    public function withEventHandler(CanHandleEvents|EventDispatcherInterface $events): self {
        $this->events = EventBusResolver::using($events);
        $this->applyEventHandlerIfProvided();
        $this->applyStoredListeners();
        return $this;
    }

    #[\Override]
    public function wiretap(?callable $listener): self {
        if ($listener === null) {
            return $this;
        }
        if ($this->agent === null) {
            $this->wiretaps[] = $listener;
            return $this;
        }
        $this->ensureEventHandler();
        $this->applyEventHandlerIfProvided();
        $this->agent->wiretap($listener);
        return $this;
    }

    #[\Override]
    public function onEvent(string $class, ?callable $listener): self {
        if ($listener === null) {
            return $this;
        }
        if ($this->agent === null) {
            $this->listeners[$class][] = $listener;
            return $this;
        }
        $this->ensureEventHandler();
        $this->applyEventHandlerIfProvided();
        $this->agent->onEvent($class, $listener);
        return $this;
    }

    private function applyEventHandlerIfProvided(): void {
        if ($this->agent === null) {
            return;
        }
        if (!isset($this->events)) {
            return;
        }
        $this->agent = $this->agent->with(
            events: $this->events,
        );
    }

    private function applyStoredListeners(): void {
        if ($this->agent === null) {
            return;
        }
        foreach ($this->wiretaps as $listener) {
            $this->agent->wiretap($listener);
        }
        foreach ($this->listeners as $class => $listeners) {
            foreach ($listeners as $listener) {
                $this->agent->onEvent($class, $listener);
            }
        }
    }

    private function ensureEventHandler(): void {
        if (isset($this->events)) {
            return;
        }
        $this->events = EventBusResolver::default();
    }
}
