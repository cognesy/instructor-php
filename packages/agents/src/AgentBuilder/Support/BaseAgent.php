<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Support;

use Cognesy\Agents\AgentBuilder\Contracts\AgentInterface;
use Cognesy\Agents\AgentBuilder\Data\AgentDescriptor;
use Cognesy\Agents\Core\AgentLoop;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Events\AgentEventEmitter;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Events\Traits\HandlesEvents;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Base class for agents that implement AgentInterface.
 *
 * This class provides a wrapper around AgentLoop, handling:
 * - Lazy building of the agent loop
 * - Event handler configuration
 * - Listener registration (wiretap, onEvent)
 *
 * Extend this class to create concrete agent implementations.
 */
abstract class BaseAgent implements AgentInterface
{
    use HandlesEvents;

    private ?AgentLoop $agentLoop = null;
    /** @var array<int, callable(object): void> */
    private array $wiretaps = [];
    /** @var array<string, array<int, callable(object): void>> */
    private array $listeners = [];

    #[\Override]
    abstract public function descriptor(): AgentDescriptor;

    abstract protected function buildAgentLoop(): AgentLoop;

    #[\Override]
    public function build(): AgentLoop {
        if ($this->agentLoop !== null) {
            return $this->agentLoop;
        }
        $this->agentLoop = $this->buildAgentLoop();
        $this->applyEventEmitterIfProvided();
        $this->applyStoredListeners();
        return $this->agentLoop;
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
        return $this;
    }

    #[\Override]
    public function wiretap(?callable $listener): self {
        if ($listener === null) {
            return $this;
        }
        if ($this->agentLoop === null) {
            $this->wiretaps[] = $listener;
            return $this;
        }
        $this->agentLoop->wiretap($listener);
        return $this;
    }

    #[\Override]
    public function onEvent(string $class, ?callable $listener): self {
        if ($listener === null) {
            return $this;
        }
        if ($this->agentLoop === null) {
            $this->listeners[$class][] = $listener;
            return $this;
        }
        $this->agentLoop->onEvent($class, $listener);
        return $this;
    }

    private function applyEventEmitterIfProvided(): void {
        if ($this->agentLoop === null || !isset($this->events)) {
            return;
        }
        $this->agentLoop = $this->agentLoop->with(
            eventEmitter: new AgentEventEmitter($this->events),
        );
    }

    private function applyStoredListeners(): void {
        if ($this->agentLoop === null) {
            return;
        }
        foreach ($this->wiretaps as $listener) {
            $this->agentLoop->wiretap($listener);
        }
        foreach ($this->listeners as $class => $listeners) {
            foreach ($listeners as $listener) {
                $this->agentLoop->onEvent($class, $listener);
            }
        }
    }
}
