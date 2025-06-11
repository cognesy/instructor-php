<?php

namespace Cognesy\Events\Contracts;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;

interface CanHandleEvents extends EventDispatcherInterface, ListenerProviderInterface
{
    /**
     * Registers a listener for a specific event class.
     *
     * @param string $name Event name - Instructor uses fully qualified name of the event class to listen for.
     * @param callable $listener A callable function or method that will handle the event.
     * @param int $priority The priority of the listener, higher values indicate higher priority.
     * @return self Returns the current instance for method chaining.
     */
    public function addListener(string $name, callable $listener, int $priority = 0): void;

    /**
     * Registers a wiretap listener that will be called for every dispatched event.
     *
     * Wiretaps are not specific to any event class and are always executed after class-specific listeners.
     *
     * @param callable $listener A callable function or method that will handle the event.
     */
    public function wiretap(callable $listener): void;
}