<?php declare(strict_types=1);

namespace Cognesy\Events\Dispatchers;

use Cognesy\Events\Contracts\CanHandleEvents;
use SplPriorityQueue;
use Symfony\Component\EventDispatcher\EventDispatcherInterface as SymfonyDispatcher;

final class SymfonyEventDispatcher implements CanHandleEvents
{
    /** @var SplPriorityQueue */
    private SplPriorityQueue $taps;

    public function __construct(
        private SymfonyDispatcher $dispatcher,
    ) {
        $this->taps = new SplPriorityQueue();
    }

    #[\Override]
    public function addListener(string $name, callable $listener, int $priority = 0): void {
        if ($name === '*') { // ← wildcard stored in the taps queue
            $this->taps->insert($listener, $priority);
            return;
        }
        $this->dispatcher->addListener($name, $listener, $priority);
    }

    /**
     * @param callable(object): void $listener
     */
    #[\Override]
    public function wiretap(callable $listener, int $priority = 0): void {
        $this->taps->insert($listener, $priority);
    }

    #[\Override]
    public function dispatch(object $event): object {
        $event = $this->dispatcher->dispatch($event); // framework listeners first

        foreach (clone $this->taps as $tap) { // taps always run, honour priority
            /** @var callable $tap */
            $tap($event);
        }

        return $event;
    }

    /**
     * @return iterable<callable(object): void>
     */
    #[\Override]
    public function getListenersForEvent(object $event): iterable {
        /** @var iterable<callable(object): void> $listeners */
        $listeners = $this->dispatcher->getListeners($event::class);
        yield from $listeners;

        foreach (clone $this->taps as $tap) {
            /** @var callable(object): void $tap */
            yield $tap;
        }
    }
}