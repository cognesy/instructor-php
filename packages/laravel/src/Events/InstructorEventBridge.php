<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Events;

use Illuminate\Contracts\Events\Dispatcher;

/**
 * Bridges Instructor PSR-14 events to Laravel's event dispatcher.
 *
 * This enables:
 * - Listening to Instructor events using Laravel's event system
 * - Broadcasting events to queues
 * - Using Laravel's event listeners and subscribers
 * - Integration with Laravel Telescope and other debugging tools
 *
 * @example
 * ```php
 * // In EventServiceProvider
 * protected $listen = [
 *     \Cognesy\Instructor\Events\ExtractionComplete::class => [
 *         MyExtractionListener::class,
 *     ],
 * ];
 * ```
 */
class InstructorEventBridge
{
    /**
     * Event classes to bridge to Laravel.
     *
     * @var array<class-string>
     */
    protected array $bridgedEvents = [];

    public function __construct(
        protected readonly Dispatcher $dispatcher,
        array $events = [],
    ) {
        $this->bridgedEvents = $events;
    }

    /**
     * Handle an Instructor event and dispatch it to Laravel.
     */
    public function handle(object $event): void
    {
        // Check if we should bridge this event
        if (!$this->shouldBridge($event)) {
            return;
        }

        // Dispatch to Laravel's event system
        $this->dispatcher->dispatch($event);
    }

    /**
     * Check if an event should be bridged to Laravel.
     */
    protected function shouldBridge(object $event): bool
    {
        // If no specific events configured, bridge all
        if (empty($this->bridgedEvents)) {
            return true;
        }

        // Check if event class is in the list
        foreach ($this->bridgedEvents as $eventClass) {
            if ($event instanceof $eventClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add an event class to the bridge list.
     */
    public function bridge(string $eventClass): self
    {
        $this->bridgedEvents[] = $eventClass;
        return $this;
    }

    /**
     * Add multiple event classes to the bridge list.
     */
    public function bridgeMany(array $eventClasses): self
    {
        foreach ($eventClasses as $eventClass) {
            $this->bridge($eventClass);
        }
        return $this;
    }

    /**
     * Bridge all events (clear the filter list).
     */
    public function bridgeAll(): self
    {
        $this->bridgedEvents = [];
        return $this;
    }

    /**
     * Get the list of bridged event classes.
     */
    public function getBridgedEvents(): array
    {
        return $this->bridgedEvents;
    }
}
