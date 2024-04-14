<?php

namespace Cognesy\Instructor\Events\Traits;

trait HandlesEventListeners
{
    use HandlesEvents;

    /**
     * Listens to all events
     */
    public function wiretap(callable $listener) : self {
        $this?->events->wiretap($listener);
        return $this;
    }

    /**
     * Listens to a specific event
     */
    public function onEvent(string $class, callable $listener) : self {
        $this?->events->addListener($class, $listener);
        return $this;
    }
}