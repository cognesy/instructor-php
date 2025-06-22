<?php

namespace Cognesy\Events;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Psr\EventDispatcher\EventDispatcherInterface;

class EventBusResolver implements CanHandleEvents
{
    private CanHandleEvents $eventHandler;

    private function __construct(
        null|CanHandleEvents|EventDispatcher $eventHandler
    ) {
        $this->eventHandler = match(true) {
            is_null($eventHandler) => new EventDispatcher(),
            $eventHandler instanceof EventBusResolver => $eventHandler->eventHandler, // avoid double wrapping
            $eventHandler instanceof EventDispatcher => $eventHandler, // already an EventDispatcher
            $eventHandler instanceof CanHandleEvents => $eventHandler, // CanHandleEvents implementation
            $eventHandler instanceof EventDispatcherInterface =>  new EventDispatcher(parent: $eventHandler), // wrap with EventDispatcher
            default => $eventHandler,
        };
    }

    // FACTORY METHODS ////////////////////////////////////////////

    public static function default(): self {
        return new self(new EventDispatcher());
    }

    public static function using(null|EventDispatcherInterface|CanHandleEvents $events): self {
        return new self($events);
    }

    // PUBLIC ////////////////////////////////////////////////////

    public function wiretap(callable $listener): void {
        $this->eventHandler->wiretap($listener);
    }

    public function addListener(string $name, callable $listener, int $priority = 0): void {
        $this->eventHandler->addListener($name, $listener, $priority);
    }

    public function dispatch(object $event) : object{
        return $this->eventHandler->dispatch($event);
    }

    public function getListenersForEvent(object $event): iterable {
        return $this->eventHandler->getListenersForEvent($event);
    }
}