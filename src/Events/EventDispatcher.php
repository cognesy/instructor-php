<?php

namespace Cognesy\Instructor\Events;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;

class EventDispatcher implements EventDispatcherInterface, ListenerProviderInterface
{
    private string $name;
    private ?EventDispatcher $parent;

    private array $listeners = [];
    private array $wiretaps = [];

    public function __construct(
        string $name = 'default',
        EventDispatcher $parent = null,
    ) {
        $this->name = $name;
        $this->parent = $parent;
    }

    public function name() : string {
        return $this->name;
    }

    public function addListener(string $eventClass, callable $listener): self {
        $this->listeners[$eventClass][] = $listener;
        return $this;
    }

    public function getListenersForEvent(object $event): iterable {
        foreach ($this->listeners as $eventClass => $listeners) {
            if ($event instanceof $eventClass) {
                yield from $listeners;
            }
        }
    }

    public function dispatch(object $event): void {
        $this->notifyListeners($event);
        // forward event to parent dispatcher
        if (isset($this->parent)) {
            $this->parent->dispatch($event);
        }
    }

    protected function notifyListeners(object $event) : void {
        $listeners = $this->getListenersForEvent($event);
        // dispatch event to listeners
        foreach ($listeners as $listener) {
            $listener($event);
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                break;
            }
        }
        $this->wiretapDispatch($event);
    }

    public function wiretap(callable $listener): self {
        $this->wiretaps[] = $listener;
        return $this;
    }

    private function wiretapDispatch(object $event): void {
        foreach ($this->wiretaps as $wiretap) {
            $wiretap($event);
        }
    }
}
