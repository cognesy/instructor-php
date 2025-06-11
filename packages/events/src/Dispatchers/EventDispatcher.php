<?php
namespace Cognesy\Events\Dispatchers;

use Cognesy\Events\Contracts\CanHandleEvents;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\StoppableEventInterface;
use SplPriorityQueue;

/**
 * A class responsible for managing and dispatching events to corresponding listeners.
 *
 * The EventDispatcher class provides functionality to register listeners for specific event classes,
 * notify those listeners when an event is dispatched, and manage a hierarchical chain of dispatchers
 * with an optional parent dispatcher to forward events.
 *
 * Additionally, it supports wiretapping, enabling callbacks to observe all dispatched events.
 *
 * It implements PSR-14, which defines a standard for event dispatching in PHP.
 */
class EventDispatcher implements CanHandleEvents
{
    private string $name;
    private ?EventDispatcherInterface $parent;

    /** @var array<string, \SplPriorityQueue> */
    private array $listeners = [];

    public function __construct(
        string $name = 'default',
        ?EventDispatcherInterface $parent = null,
    ) {
        $this->name = $name;
        $this->parent = $parent;
    }

    /**
     * Retrieves the name of the dispatcher.
     *
     * @return string The name.
     */
    public function name() : string {
        return $this->name;
    }

    public function dispatcher() : EventDispatcherInterface {
        return $this->parent ?? $this;
    }

    /**
     * Registers a listener for a specific event class.
     *
     * @param string $name Event name - Instructor uses fully qualified name of the event class to listen for.
     * @param callable $listener A callable function or method that will handle the event.
     * @param int $priority The priority of the listener, higher values indicate higher priority.
     * @return self Returns the current instance for method chaining.
     */
    public function addListener(string $name, callable $listener, int $priority = 0): void {
        $queue = $this->listeners[$name] ??= new SplPriorityQueue();
        $queue->insert($listener, $priority);
    }

    /**
     * Registers a wiretap listener that will be called for every dispatched event.
     *
     * Wiretaps are not specific to any event class and are always executed after class-specific listeners.
     *
     * @param callable $listener A callable function or method that will handle the event.
     */
    public function wiretap(callable $listener): void {
        $this->addListener('*', $listener);
    }

    /**
     * Retrieves the listeners associated with a given event object.
     *
     * @param object $event The event object for which to retrieve listeners.
     * @return iterable An iterable list of listeners that are registered for the event's class or its parent classes.
     */
    public function getListenersForEvent(object $event): iterable {
        yield from $this->classListeners($event);

        if (isset($this->listeners['*'])) {
            foreach (clone $this->listeners['*'] as $tap) {
                yield $tap;
            }
        }
    }

    /**
     * Dispatches an event to all registered listeners and forwards it to the parent dispatcher if available.
     *
     * @param object $event The event object to be dispatched.
     * @return void
     */
    public function dispatch(object $event): object {
        // class-specific listeners (honour stopPropagation)
        foreach ($this->classListeners($event) as $listener) {
            $listener($event);
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                break;
            }
        }

        // taps — always run
        if (isset($this->listeners['*'])) {
            foreach (clone $this->listeners['*'] as $tap) {
                $tap($event);
            }
        }

        // bubble up, if parent dispatcher present
        $this->parent?->dispatch($event);

        return $event;
    }

    // INTERNAL /////////////////////////////////////////////////////////////////////

    private function classListeners(object $event): iterable
    {
        $types = array_merge(
            [get_class($event)],
            class_parents($event),
            class_implements($event)
        );

        foreach ($types as $type) {
            if (!isset($this->listeners[$type])) {
                continue;
            }
            foreach (clone $this->listeners[$type] as $listener) {
                yield $listener;
            }
        }
    }
}
