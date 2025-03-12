<?php
namespace Cognesy\Utils\Events;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;

/**
 * A class responsible for managing and dispatching events to corresponding listeners.
 *
 * The EventDispatcher provides functionality to register listeners for specific
 * event classes, notify those listeners when an event is dispatched, and manage
 * a hierarchical chain of dispatchers with an optional parent dispatcher to forward events.
 * Additionally, it supports wiretapping, enabling callbacks to observe all dispatched events.
 */
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

    /**
     * Retrieves the name of the dispatcher.
     *
     * @return string The name.
     */
    public function name() : string {
        return $this->name;
    }

    /**
     * Registers a listener for a specific event class.
     *
     * @param string $eventClass The fully qualified name of the event class to listen for.
     * @param callable $listener A callable function or method that will handle the event.
     * @return self Returns the current instance for method chaining.
     */
    public function addListener(string $eventClass, callable $listener): self {
        $this->listeners[$eventClass][] = $listener;
        return $this;
    }

    /**
     * Retrieves the listeners associated with a given event object.
     *
     * @param object $event The event object for which to retrieve listeners.
     * @return iterable An iterable list of listeners that are registered for the event's class or its parent classes.
     */
//    public function getListenersForEvent(object $event): iterable {
//        foreach ($this->listeners as $eventClass => $listeners) {
//            if ($event instanceof $eventClass) {
//                yield from $listeners;
//            }
//        }
//    }
    public function getListenersForEvent(object $event): iterable {
        $listenersToReturn = [];

        // Loop through all registered event classes
        foreach ($this->listeners as $eventClass => $listeners) {
            // If the event is an instance of this class, add all its listeners
            if ($event instanceof $eventClass) {
                foreach ($listeners as $listener) {
                    // Using an array ensures each listener only appears once
                    $listenersToReturn[] = $listener;
                }
            }
        }

        // Return the listeners
        return $listenersToReturn;
    }

    /**
     * Dispatches an event to all registered listeners and forwards it to the parent dispatcher if available.
     *
     * @param object $event The event object to be dispatched.
     * @return void
     */
    public function dispatch(object $event): void {
        $this->notifyListeners($event);
        // forward event to parent dispatcher
        if (isset($this->parent)) {
            $this->parent->dispatch($event);
        }
    }

    /**
     * Notifies all registered listeners of the given event.
     *
     * @param object $event The event object being dispatched to the listeners.
     * @return void This method does not return a value.
     */
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

    /**
     * Adds a wiretap listener that will be triggered for all events.
     *
     * @param callable $listener A callable function or method to handle the events.
     * @return self Returns the current instance for method chaining.
     */
    public function wiretap(callable $listener): self {
        $this->wiretaps[] = $listener;
        return $this;
    }

    /**
     * Dispatches an event to all registered wiretap listeners.
     *
     * @param object $event The event object to be passed to each wiretap listener.
     * @return void Does not return any value.
     */
    private function wiretapDispatch(object $event): void {
        foreach ($this->wiretaps as $wiretap) {
            $wiretap($event);
        }
    }
}
