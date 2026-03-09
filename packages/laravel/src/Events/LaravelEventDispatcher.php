<?php declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Events;

use Cognesy\Events\Contracts\CanHandleEvents;
use Illuminate\Contracts\Events\Dispatcher;
use SplPriorityQueue;

final class LaravelEventDispatcher implements CanHandleEvents
{
    private Dispatcher $dispatcher;

    /** @var array<class-string, SplPriorityQueue<int, callable(object): void>> */
    private array $registry = [];

    /** @var SplPriorityQueue<int, callable(object): void> */
    private SplPriorityQueue $taps;
    private bool $dispatchToLaravel;

    /** @var array<class-string> */
    private array $bridgedEvents;

    /**
     * @param array<class-string> $bridgedEvents
     */
    public function __construct(
        Dispatcher $laravel,
        bool $dispatchToLaravel = true,
        array $bridgedEvents = [],
    ) {
        $this->dispatcher = $laravel;
        $this->taps = $this->newListenerQueue();
        $this->dispatchToLaravel = $dispatchToLaravel;
        $this->bridgedEvents = $bridgedEvents;
    }

    #[\Override]
    public function addListener(string $name, callable $listener, int $priority = 0): void
    {
        if ($name === '*') {
            $this->taps->insert($listener, $priority);
            return;
        }

        /** @var class-string $name */
        $queue = $this->registry[$name] ??= $this->newListenerQueue();
        $queue->insert($listener, $priority);
    }

    /**
     * @param callable(object): void $listener
     */
    #[\Override]
    public function wiretap(callable $listener, int $priority = \PHP_INT_MIN): void
    {
        $this->addListener('*', $listener, $priority);
    }

    #[\Override]
    public function dispatch(object $event): object
    {
        $this->dispatchClassListeners($event);

        if ($this->shouldBridgeToLaravel($event)) {
            $this->dispatcher->dispatch($event);
        }

        $taps = clone $this->taps;
        foreach ($taps as $tap) {
            $tap($event);
        }

        return $event;
    }

    /**
     * @return iterable<callable(object): void>
     */
    #[\Override]
    public function getListenersForEvent(object $event): iterable
    {
        $name = $event::class;

        if (isset($this->registry[$name])) {
            $listeners = clone $this->registry[$name];
            foreach ($listeners as $listener) {
                yield $listener;
            }
        }

        $taps = clone $this->taps;
        foreach ($taps as $tap) {
            yield $tap;
        }
    }

    private function shouldBridgeToLaravel(object $event): bool
    {
        if (!$this->dispatchToLaravel) {
            return false;
        }

        if ($this->bridgedEvents === []) {
            return true;
        }

        foreach ($this->bridgedEvents as $eventClass) {
            if ($event instanceof $eventClass) {
                return true;
            }
        }

        return false;
    }

    private function dispatchClassListeners(object $event): void
    {
        $name = $event::class;
        if (!isset($this->registry[$name])) {
            return;
        }

        $listeners = clone $this->registry[$name];
        foreach ($listeners as $listener) {
            $listener($event);
        }
    }

    /** @return SplPriorityQueue<int, callable(object): void> */
    private function newListenerQueue(): SplPriorityQueue
    {
        $queue = new SplPriorityQueue();
        $queue->setExtractFlags(SplPriorityQueue::EXTR_DATA);

        return $queue;
    }
}
