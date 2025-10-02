<?php declare(strict_types=1);

namespace Cognesy\Events\Dispatchers;

use Cognesy\Events\Contracts\CanHandleEvents;
use SplPriorityQueue;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class SymfonyEventDispatcher implements CanHandleEvents
{
    /** @var SplPriorityQueue<callable, int> */
    private SplPriorityQueue $taps;

    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
        $this->taps = new SplPriorityQueue();
    }

    #[\Override]
    public function addListener(string $name, callable $listener, int $priority = 0): void {
        if ($name === '*') { // ← wildcard stored in the taps queue
            /** @psalm-suppress InvalidArgument - SplPriorityQueue::insert($value, $priority) accepts mixed for both params */
            $this->taps->insert($listener, $priority);
            return;
        }
        $this->dispatcher->addListener($name, $listener, $priority);
    }

    #[\Override]
    public function wiretap(callable $listener, int $priority = 0): void {
        /** @psalm-suppress InvalidArgument - SplPriorityQueue::insert($value, $priority) accepts mixed for both params */
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

    #[\Override]
    public function getListenersForEvent(object $event): iterable {
        yield from $this->dispatcher->getListeners($event::class);

        foreach (clone $this->taps as $tap) {
            yield $tap;
        }
    }
}