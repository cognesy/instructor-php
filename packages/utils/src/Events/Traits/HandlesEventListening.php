<?php

namespace Cognesy\Utils\Events\Traits;

use Cognesy\Utils\Events\Contracts\EventListenerInterface;

trait HandlesEventListening
{
    protected EventListenerInterface $listener;

    /**
     * Sets the event dispatcher
     *
     * @param EventListenerInterface $listener The event dispatcher
     */
    public function withEventListener(EventListenerInterface $listener): static {
        $this->listener = $listener;
        return $this;
    }

    /**
     * Listens to all events
     *
     * @param callable $listener The listener callable to be invoked on any event
     */
    public function wiretap(callable $listener) : self {
        $this->listener->wiretap($listener);
        return $this;
    }

    /**
     * Listens to a specific event
     *
     * @param string $class The event class
     * @param callable $listener The listener callable to be invoked on event
     */
    public function onEvent(string $class, callable $listener) : self {
        $this->listener->addListener($class, $listener);
        return $this;
    }
}