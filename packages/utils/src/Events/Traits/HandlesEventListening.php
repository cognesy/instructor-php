<?php

namespace Cognesy\Utils\Events\Traits;

use Cognesy\Utils\Events\Contracts\CanRegisterEventListeners;

trait HandlesEventListening
{
    protected CanRegisterEventListeners $listener;

    /**
     * Sets the event dispatcher
     *
     * @param CanRegisterEventListeners $listener The event dispatcher
     */
    public function withEventListener(CanRegisterEventListeners $listener): static {
        $this->listener = $listener;
        return $this;
    }

    /**
     * Registers callback listening to all events
     *
     * @param callable $listener The listener callable to be invoked on any event
     */
    public function wiretap(?callable $listener) : self {
        if ($listener !== null) {
            $this->listener->wiretap($listener);
        }
        return $this;
    }

    /**
     * Registers callback listening to a specific event type
     *
     * @param string $class The event class
     * @param callable $listener The listener callable to be invoked on event
     */
    public function onEvent(string $class, ?callable $listener) : self {
        if ($listener !== null) {
            $this->listener->addListener($class, $listener);
        }
        return $this;
    }
}