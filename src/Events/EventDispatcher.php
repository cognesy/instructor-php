<?php

namespace Cognesy\Instructor\Events;

class EventDispatcher
{
    private array $listeners = [];
    private array $wiretaps = [];

    public function addListener(string $eventClass, callable $listener): self
    {
        $this->listeners[$eventClass][] = $listener;
        return $this;
    }

    public function dispatch(Event $event): void
    {
        $eventClass = get_class($event);
        if (isset($this->listeners[$eventClass])) {
            foreach ($this->listeners[$eventClass] as $listener) {
                $listener($event);
            }
        }
        $this->wiretapDispatch($event);
    }

    public function wiretap(callable $listener): self
    {
        $this->wiretaps[] = $listener;
        return $this;
    }

    private function wiretapDispatch(Event $event): void
    {
        foreach ($this->wiretaps as $wiretap) {
            $wiretap($event);
        }
    }
}
