<?php

namespace Cognesy\Events;

use Cognesy\Events\Contracts\CanHandleEvents;
use Psr\EventDispatcher\EventDispatcherInterface;

class EventBusResolver implements CanHandleEvents
{
    private CanHandleEvents $eventHandler;

    private function __construct(
        ?CanHandleEvents $eventHandler
    ) {
        $this->eventHandler = match(true) {
            is_null($eventHandler) => new EventDispatcher(),
            $eventHandler instanceof EventBusResolver => $eventHandler->eventHandler, // avoid double wrapping
            $eventHandler instanceof CanHandleEvents => $eventHandler, // already a CanHandleEvents implementation
            $eventHandler instanceof EventDispatcherInterface =>  new EventDispatcher(parent: $eventHandler), // wrap with EventDispatcher
            default => $eventHandler,
        };
    }

    public static function default(): self {
        return new self(new EventDispatcher());
    }

    public static function using(?CanHandleEvents $events): self {
        return new self($events);
    }

    public function get(): CanHandleEvents {
        return $this->eventHandler;
    }

    public function wiretap(callable $listener): void {
        $this->eventHandler->wiretap($listener);
    }

    public function addListener(string $name, callable $listener): void {
        $this->eventHandler->addListener($name, $listener);
    }

    public function dispatch(object $event) : object{
        return $this->eventHandler->dispatch($event);
    }

    public function getListenersForEvent(object $event): iterable {
        return $this->eventHandler->getListenersForEvent($event);
    }

    public function dispatcher(): EventDispatcherInterface {
        return $this->eventHandler->dispatcher();
    }
}