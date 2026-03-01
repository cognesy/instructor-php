<?php declare(strict_types=1);

namespace Cognesy\Events\Dispatchers;

use Cognesy\Events\Contracts\CanHandleEvents;
use Psr\EventDispatcher\StoppableEventInterface;
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
        foreach ($this->eventTypes($event) as $eventType) {
            $event = $this->dispatcher->dispatch($event, $eventType);
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                break;
            }
        }

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
        yield from $this->classListeners($event);

        foreach (clone $this->taps as $tap) {
            /** @var callable(object): void $tap */
            yield $tap;
        }
    }

    /**
     * @return iterable<callable(object): void>
     */
    private function classListeners(object $event): iterable {
        foreach ($this->eventTypes($event) as $type) {
            /** @var iterable<callable(object): void> $listeners */
            $listeners = $this->dispatcher->getListeners($type);
            yield from $listeners;
        }
    }

    /** @return list<string> */
    private function eventTypes(object $event): array {
        return array_values(array_unique(array_merge(
            [get_class($event)],
            class_parents($event),
            class_implements($event),
        )));
    }
}
