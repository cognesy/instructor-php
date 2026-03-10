<?php declare(strict_types=1);

namespace Cognesy\Events\Traits;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\Event;
use LogicException;

trait HandlesEvents
{
    protected CanHandleEvents $events;

    public function withEventHandler(CanHandleEvents $events) : static {
        $this->events = $events;
        return $this;
    }

    /**
     * Dispatches an event
     *
     * @param Event $event The event to be emitted
     */
    public function dispatch(Event $event) : object {
        return $this->eventsOrFail()->dispatch($event);
    }

    /**
     * Registers callback listening to all events
     *
     * @param callable(object): void|null $listener The listener callable to be invoked on any event
     */
    public function wiretap(?callable $listener) : self {
        if ($listener !== null) {
            $this->eventsOrFail()->wiretap($listener);
        }
        return $this;
    }

    /**
     * Registers callback listening to a specific event type
     *
     * @param string $class The event class
     * @param callable(object): void|null $listener The listener callable to be invoked on event
     */
    public function onEvent(string $class, ?callable $listener) : self {
        if ($listener !== null) {
            $this->eventsOrFail()->addListener($class, $listener);
        }
        return $this;
    }

    private function eventsOrFail() : CanHandleEvents {
        if (isset($this->events)) {
            return $this->events;
        }

        throw new LogicException('Event handler not configured. Call withEventHandler() before dispatching or registering listeners.');
    }
}
