<?php declare(strict_types=1);

namespace Cognesy\Events\Traits;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\Event;
use Cognesy\Events\EventBusResolver;
use Psr\EventDispatcher\EventDispatcherInterface;

trait HandlesEvents
{
    protected CanHandleEvents $events;

    public function withEventHandler(CanHandleEvents|EventDispatcherInterface $events) : static {
        $this->events = EventBusResolver::using($events);
        return $this;
    }

    /**
     * Dispatches an event
     *
     * @param Event $event The event to be emitted
     */
    public function dispatch(Event $event) : object {
        return $this->events->dispatch($event);
    }

    /**
     * Registers callback listening to all events
     *
     * @param callable $listener The listener callable to be invoked on any event
     */
    public function wiretap(?callable $listener) : self {
        if ($listener !== null) {
            $this->events->wiretap($listener);
        }
        return $this;
    }

    /**
     * Registers callback listening to a specific event type
     *
     * @param string $class The event class
     * @param callable $listener The listener callable to be invoked on event
     */
    public function onEvent(string $class, ?callable $listener) : self {
        if ($listener !== null) {
            $this->events->addListener($class, $listener);
        }
        return $this;
    }
}