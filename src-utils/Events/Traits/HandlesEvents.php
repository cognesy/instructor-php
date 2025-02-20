<?php
namespace Cognesy\Utils\Events\Traits;

use Cognesy\Utils\Events\Event;
use Cognesy\Utils\Events\EventDispatcher;

trait HandlesEvents
{
    protected EventDispatcher $events;

    /**
     * Sets the event dispatcher
     *
     * @param EventDispatcher $events The event dispatcher
     */
    public function withEventDispatcher(EventDispatcher $events): static {
        $this->events = $events;
        return $this;
    }

    /**
     * Returns the event dispatcher
     */
    public function events() : EventDispatcher {
        return $this->events;
    }

    /**
     * Emits an event
     *
     * @param Event $event The event to be emitted
     */
    public function emit(Event $event) : void {
        $this->events->dispatch($event);
    }

    /**
     * Listens to all events
     *
     * @param callable $listener The listener callable to be invoked on any event
     */
    public function wiretap(callable $listener) : self {
        $this->events->wiretap($listener);
        return $this;
    }

    /**
     * Listens to a specific event
     *
     * @param string $class The event class
     * @param callable $listener The listener callable to be invoked on event
     */
    public function onEvent(string $class, callable $listener) : self {
        $this->events->addListener($class, $listener);
        return $this;
    }
}