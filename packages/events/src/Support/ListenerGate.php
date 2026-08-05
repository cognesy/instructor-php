<?php declare(strict_types=1);

namespace Cognesy\Events\Support;

use Cognesy\Events\Contracts\CanCheckListeners;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The single definition of "should this emitter build the event at all?".
 *
 * Constructing an event costs roughly 0.9us before any payload is assembled --
 * the base Event generates a UUID via random_bytes() plus a DateTimeImmutable --
 * and payload arrays cost more on top. Emitters that can be reached many times
 * per request resolve this once and skip both when nothing is listening.
 *
 * FAIL-OPEN IS CONTRACTUAL. A dispatcher that does not implement
 * CanCheckListeners cannot report its listeners, so it is assumed to listen and
 * receives every event. A plain PSR-14 dispatcher must never lose an event to
 * this optimisation. See the note on CanCheckListeners.
 *
 * RESOLVE ONCE, NOT PER DISPATCH. Call this in a constructor and store the
 * result in a readonly bool; checking at the dispatch site reintroduces a
 * per-call instanceof. The deliberate consequence is that listeners registered
 * after the emitter was constructed are not observed by that emitter.
 */
final class ListenerGate
{
    /**
     * @param class-string $eventClass
     */
    public static function wants(EventDispatcherInterface $events, string $eventClass): bool {
        return !($events instanceof CanCheckListeners)
            || $events->hasListenersFor($eventClass);
    }

    /**
     * Resolve several event classes at once.
     *
     * @param list<class-string> $eventClasses
     * @return array<class-string, bool>
     */
    public static function wantsAny(EventDispatcherInterface $events, array $eventClasses): array {
        if (!($events instanceof CanCheckListeners)) {
            return array_fill_keys($eventClasses, true);
        }

        $result = [];
        foreach ($eventClasses as $eventClass) {
            $result[$eventClass] = $events->hasListenersFor($eventClass);
        }

        return $result;
    }
}
