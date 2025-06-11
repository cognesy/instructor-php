<?php

namespace Cognesy\Events\Dispatchers;

use Cognesy\Events\Contracts\CanHandleEvents;
use Illuminate\Contracts\Events\Dispatcher;
use SplPriorityQueue;

final class LaravelEventDispatcher implements CanHandleEvents
{
    /** Laravel’s own dispatcher (sync or queued) */
    private Dispatcher $dispatcher;

    /** @var array<class-string, SplPriorityQueue<callable>> */
    private array $registry = [];

    /** @var SplPriorityQueue<callable>  wire-tap listeners (“*”) */
    private SplPriorityQueue $taps;

    public function __construct(Dispatcher $laravel)
    {
        $this->dispatcher = $laravel;
        $this->taps    = new SplPriorityQueue();
    }

    /* ---------------------------------------------------------------------
     * Registration helpers
     * ------------------------------------------------------------------- */

    public function addListener(string $name, callable $listener, int $priority = 0): void
    {
        if ($name === '*') {
            $this->taps->insert($listener, $priority);
            return;
        }

        // remember for introspection
        $queue = $this->registry[$name] ??= new SplPriorityQueue();
        $queue->insert($listener, $priority);

        // hand off to Laravel (priority ignored by Laravel, but order is preserved per registration)
        $this->dispatcher->listen($name, $listener);
    }

    public function wiretap(callable $listener, int $priority = \PHP_INT_MIN): void
    {
        $this->addListener('*', $listener, $priority);
    }

    /* ---------------------------------------------------------------------
     * Dispatch
     * ------------------------------------------------------------------- */

    public function dispatch(object $event): object
    {
        // Laravel executes all framework / user listeners.
        $this->dispatcher->dispatch($event);

        // Now run taps (guaranteed, final state of the event).
        foreach (clone $this->taps as $tap) {
            $tap($event);
        }

        return $event;
    }

    /* ---------------------------------------------------------------------
     * Introspection (workaround for Laravel’s lack of introspection)
     * ------------------------------------------------------------------- */

    public function getListenersForEvent(object $event): iterable
    {
        $name = $event::class;

        // class-specific listeners registered through this bridge
        if (isset($this->registry[$name])) {
            foreach (clone $this->registry[$name] as $listener) {
                yield $listener;
            }
        }

        // taps
        foreach (clone $this->taps as $tap) {
            yield $tap;
        }
    }
}