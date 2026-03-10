<?php declare(strict_types=1);

use Cognesy\Events\Event;
use Cognesy\Events\Traits\HandlesEvents;

final class UnwiredHandlesEventsProbe
{
    use HandlesEvents;
}

it('throws a clear exception when dispatch is used before wiring an event handler', function () {
    $probe = new UnwiredHandlesEventsProbe();

    expect(fn() => $probe->dispatch(new Event()))
        ->toThrow(LogicException::class, 'Event handler not configured. Call withEventHandler() before dispatching or registering listeners.');
});

it('throws a clear exception when wiretap is used before wiring an event handler', function () {
    $probe = new UnwiredHandlesEventsProbe();

    expect(fn() => $probe->wiretap(fn(object $event) => null))
        ->toThrow(LogicException::class, 'Event handler not configured. Call withEventHandler() before dispatching or registering listeners.');
});

it('throws a clear exception when onEvent is used before wiring an event handler', function () {
    $probe = new UnwiredHandlesEventsProbe();

    expect(fn() => $probe->onEvent(Event::class, fn(object $event) => null))
        ->toThrow(LogicException::class, 'Event handler not configured. Call withEventHandler() before dispatching or registering listeners.');
});
