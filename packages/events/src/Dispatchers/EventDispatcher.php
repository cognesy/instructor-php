<?php declare(strict_types=1);

namespace Cognesy\Events\Dispatchers;

use Cognesy\Events\Contracts\CanHandleEvents;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\StoppableEventInterface;

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

    /** @var array<string, array<int, array{listener: callable(object): void, priority: int, order: int}>> */
    private array $listeners = [];
    private int $listenerOrder = 0;

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
     * @param callable(object): void $listener A callable function or method that will handle the event.
     * @param int $priority The priority of the listener, higher values indicate higher priority.
     */
    #[\Override]
    public function addListener(string $name, callable $listener, int $priority = 0): void {
        $this->listeners[$name] ??= [];
        $this->listeners[$name][] = [
            'listener' => $listener,
            'priority' => $priority,
            'order' => $this->listenerOrder++,
        ];
    }

    /**
     * Registers a wiretap listener that will be called for every dispatched event.
     *
     * Wiretaps are not specific to any event class and are always executed after class-specific listeners.
     *
     * @param callable(object): void $listener A callable function or method that will handle the event.
     */
    #[\Override]
    public function wiretap(callable $listener): void {
        $this->addListener('*', $listener);
    }

    /**
     * Retrieves the listeners associated with a given event object.
     *
     * @param object $event The event object for which to retrieve listeners.
     * @return iterable<callable(object): void> An iterable list of listeners that are registered for the event's class or its parent classes.
     */
    #[\Override]
    public function getListenersForEvent(object $event): iterable {
        yield from $this->classListeners($event);

        foreach ($this->tapListeners() as $tap) {
            yield $tap;
        }
    }

    /**
     * Dispatches an event to all registered listeners and forwards it to the parent dispatcher if available.
     *
     * @param object $event The event object to be dispatched.
     * @return object The same event object after processing.
     */
    #[\Override]
    public function dispatch(object $event): object {
        // class-specific listeners (honour stopPropagation)
        foreach ($this->classListeners($event) as $listener) {
            $listener($event);
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                break;
            }
        }

        // taps — always run
        foreach ($this->tapListeners() as $tap) {
            $tap($event);
        }

        // bubble up, if parent dispatcher present
        $this->parent?->dispatch($event);

        return $event;
    }

    // INTERNAL /////////////////////////////////////////////////////////////////////

    /**
     * @return iterable<callable(object): void>
     *
     * Ordering policy:
     * 1) higher listener priority first (global across class/parent/interface buckets),
     * 2) when priority ties, more specific type bucket first (class -> parents -> interfaces),
     * 3) when both tie, registration order.
     */
    private function classListeners(object $event): iterable
    {
        $types = array_merge(
            [get_class($event)],
            class_parents($event),
            class_implements($event)
        );

        $candidates = [];
        foreach ($types as $typeOrder => $type) {
            if (!isset($this->listeners[$type])) {
                continue;
            }

            foreach ($this->listeners[$type] as $entry) {
                $candidates[] = [
                    'listener' => $entry['listener'],
                    'priority' => $entry['priority'],
                    'order' => $entry['order'],
                    'typeOrder' => $typeOrder,
                ];
            }
        }

        usort($candidates, static function (array $left, array $right): int {
            $priorityOrder = $right['priority'] <=> $left['priority'];
            if ($priorityOrder !== 0) {
                return $priorityOrder;
            }

            $typeOrder = $left['typeOrder'] <=> $right['typeOrder'];
            if ($typeOrder !== 0) {
                return $typeOrder;
            }

            return $left['order'] <=> $right['order'];
        });

        foreach ($candidates as $entry) {
            yield $entry['listener'];
        }
    }

    /**
     * @return iterable<callable(object): void>
     *
     * Taps are always ordered by priority and then registration order.
     */
    private function tapListeners(): iterable
    {
        $taps = $this->listeners['*'] ?? [];
        usort($taps, static function (array $left, array $right): int {
            $priorityOrder = $right['priority'] <=> $left['priority'];
            if ($priorityOrder !== 0) {
                return $priorityOrder;
            }
            return $left['order'] <=> $right['order'];
        });

        foreach ($taps as $tap) {
            yield $tap['listener'];
        }
    }
}
