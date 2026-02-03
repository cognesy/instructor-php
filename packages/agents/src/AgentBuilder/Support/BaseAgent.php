<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Support;

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Contracts\AgentInterface;
use Cognesy\Agents\AgentBuilder\Data\AgentDescriptor;
use Cognesy\Agents\Core\AgentLoop;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Base class for agents that implement AgentInterface.
 *
 * Provides lazy loop construction via configureLoop(), event handler wiring,
 * and direct listener registration (no buffering).
 */
abstract class BaseAgent implements AgentInterface
{
    private ?AgentLoop $agentLoop = null;
    private ?CanHandleEvents $events = null;

    abstract public function descriptor(): AgentDescriptor;

    abstract protected function configureLoop(AgentBuilder $builder): AgentBuilder;

    public function execute(AgentState $state): AgentState {
        return $this->ensureLoop()->execute($state);
    }

    public function iterate(AgentState $state): iterable {
        return $this->ensureLoop()->iterate($state);
    }

    public function withEventHandler(CanHandleEvents|EventDispatcherInterface $events): self {
        $this->events = EventBusResolver::using($events);
        $this->agentLoop = null;
        return $this;
    }

    /**
     * @param callable(object): void|null $listener
     */
    public function wiretap(?callable $listener): self {
        if ($listener === null) {
            return $this;
        }
        $this->ensureLoop()->wiretap($listener);
        return $this;
    }

    /**
     * @param callable(object): void|null $listener
     */
    public function onEvent(string $class, ?callable $listener): self {
        if ($listener === null) {
            return $this;
        }
        $this->ensureLoop()->onEvent($class, $listener);
        return $this;
    }

    private function ensureLoop(): AgentLoop {
        if ($this->agentLoop !== null) {
            return $this->agentLoop;
        }

        $builder = AgentBuilder::base();

        if ($this->events !== null) {
            $builder = $builder->withEvents($this->events);
        }

        $builder = $this->configureLoop($builder);

        $this->agentLoop = $builder->build();
        return $this->agentLoop;
    }
}
