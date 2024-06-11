<?php

namespace Cognesy\Instructor\Events;

class EventDispatcher
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

    public function dispatch(Event $event): void {
        $this->notifyListeners($event);
        // forward event to parent dispatcher
        if (isset($this->parent)) {
            $this->parent->dispatch($event);
        }
    }

    protected function notifyListeners(Event $event) : void {
        // dispatch event to listeners
        $eventClass = get_class($event);
        $listeners = $this->listeners[$eventClass] ?? [];
        foreach ($listeners as $listener) {
            $listener($event);
        }
        $this->wiretapDispatch($event);
    }

    public function wiretap(callable $listener): self {
        $this->wiretaps[] = $listener;
        return $this;
    }

    private function wiretapDispatch(Event $event): void {
        foreach ($this->wiretaps as $wiretap) {
            $wiretap($event);
        }
    }
}
