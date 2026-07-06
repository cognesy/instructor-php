<?php declare(strict_types=1);

namespace Cognesy\Events\Contracts;

/**
 * Optional capability of event dispatchers: report whether any listener
 * (class-specific, inherited, wiretap, or parent dispatcher) would receive
 * an event of the given class.
 *
 * Hot-path emitters use this to skip constructing event objects nobody
 * consumes. Callers must degrade gracefully: when a dispatcher does not
 * implement this interface, assume listeners exist and dispatch normally.
 */
interface CanCheckListeners
{
    /**
     * @param class-string $eventClass
     */
    public function hasListenersFor(string $eventClass): bool;
}
