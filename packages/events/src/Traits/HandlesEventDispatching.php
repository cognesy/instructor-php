<?php
namespace Cognesy\Events\Traits;

use Cognesy\Events\Event;
use Psr\EventDispatcher\EventDispatcherInterface;

trait HandlesEventDispatching
{
    protected EventDispatcherInterface $events;

    /**
     * Sets the event dispatcher
     *
     * @param EventDispatcherInterface $events The event dispatcher
     */
    public function withEventDispatcher(EventDispatcherInterface $events) : static {
        $this->events = $events;
        return $this;
    }

    /**
     * Returns the event dispatcher
     */
    public function events() : EventDispatcherInterface {
        return $this->events;
    }

    /**
     * Dispatches an event
     *
     * @param \Cognesy\Events\Event $event The event to be emitted
     */
    public function dispatch(Event $event) : void {
        $this->events->dispatch($event);
    }
}