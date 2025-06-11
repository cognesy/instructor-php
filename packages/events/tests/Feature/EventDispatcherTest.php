<?php

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Events\Event;
use Psr\EventDispatcher\StoppableEventInterface;

// Basic Dispatcher Tests
test('dispatcher has a name', function () {
    $dispatcher = new EventDispatcher('test-dispatcher');
    expect($dispatcher->name())->toBe('test-dispatcher');
});

test('dispatcher can be initialized with default name', function () {
    $dispatcher = new EventDispatcher();
    expect($dispatcher->name())->toBe('default');
});

// Event Listener Tests
test('dispatcher can register listeners for events', function () {
    $dispatcher = new EventDispatcher();
    $eventClass = TestEvent::class;
    $listener = fn() => null;

    $result = $dispatcher->addListener($eventClass, $listener);

    // Test method chaining
    expect($result)->toBe($dispatcher);

    // Test getListenersForEvent
    $event = new TestEvent();
    $listeners = iterator_to_array($dispatcher->getListenersForEvent($event));

    expect($listeners)->toHaveCount(1);
    expect($listeners[0])->toBe($listener);
});

test('dispatcher returns listeners for parent event classes', function () {
    $dispatcher = new EventDispatcher();
    $baseListener = fn() => null;
    $childListener = fn() => null;

    // Register listeners
    $dispatcher->addListener(TestEvent::class, $baseListener);
    $dispatcher->addListener(ChildTestEvent::class, $childListener);

    // Base event should only get base listeners
    $baseEvent = new TestEvent();
    $baseListeners = iterator_to_array($dispatcher->getListenersForEvent($baseEvent));
    expect($baseListeners)->toHaveCount(1);
    expect($baseListeners[0])->toBe($baseListener);

    // Child event should get both listeners
    $childEvent = new ChildTestEvent();
    $childListeners = iterator_to_array($dispatcher->getListenersForEvent($childEvent));
    expect($childListeners)->toHaveCount(2);

    // This is how we can verify both listeners are in the array
    $listenerFound = [false, false];
    foreach ($childListeners as $listener) {
        if ($listener === $baseListener) $listenerFound[0] = true;
        if ($listener === $childListener) $listenerFound[1] = true;
    }
    expect($listenerFound[0])->toBeTrue('Base listener not found');
    expect($listenerFound[1])->toBeTrue('Child listener not found');
});

// Event Dispatching Tests
test('dispatcher calls listeners with the event', function () {
    $dispatcher = new EventDispatcher();
    $event = new TestEvent(['key' => 'value']);
    $called = false;

    $dispatcher->addListener(TestEvent::class, function (TestEvent $e) use (&$called, $event) {
        $called = true;
        expect($e)->toBe($event);
        expect($e->data)->toBe(['key' => 'value']);
    });

    $dispatcher->dispatch($event);
    expect($called)->toBeTrue();
});

test('dispatcher stops propagation when event is stoppable and stopped', function () {
    $dispatcher = new EventDispatcher();
    $event = new StoppableTestEvent();

    $firstCalled = false;
    $secondCalled = false;

    $dispatcher->addListener(StoppableTestEvent::class, function (StoppableTestEvent $e) use (&$firstCalled) {
        $firstCalled = true;
        $e->stopPropagation();
    });

    $dispatcher->addListener(StoppableTestEvent::class, function () use (&$secondCalled) {
        $secondCalled = true;
    });

    $dispatcher->dispatch($event);

    expect($firstCalled)->toBeTrue();
    expect($secondCalled)->toBeFalse();
    expect($event->isPropagationStopped())->toBeTrue();
});

// Parent Dispatcher Tests
test('dispatcher forwards events to parent dispatcher', function () {
    $parentDispatcher = new EventDispatcher('parent');
    $childDispatcher = new EventDispatcher('child', $parentDispatcher);

    $event = new TestEvent();
    $parentCalled = false;

    $parentDispatcher->addListener(TestEvent::class, function () use (&$parentCalled) {
        $parentCalled = true;
    });

    $childDispatcher->dispatch($event);
    expect($parentCalled)->toBeTrue();
});

test('parent dispatcher is not affected by child event stopping propagation', function () {
    $parentDispatcher = new EventDispatcher('parent');
    $childDispatcher = new EventDispatcher('child', $parentDispatcher);

    $event = new StoppableTestEvent();
    $childCalled = false;
    $parentCalled = false;

    $childDispatcher->addListener(StoppableTestEvent::class, function (StoppableTestEvent $e) use (&$childCalled) {
        $childCalled = true;
        $e->stopPropagation();
    });

    $parentDispatcher->addListener(StoppableTestEvent::class, function () use (&$parentCalled) {
        $parentCalled = true;
    });

    $childDispatcher->dispatch($event);

    expect($childCalled)->toBeTrue();
    expect($parentCalled)->toBeTrue();
});

// Wiretap Tests
test('wiretap receives all dispatched events', function () {
    $dispatcher = new EventDispatcher();
    $event1 = new TestEvent(['id' => 1]);
    $event2 = new ChildTestEvent(['id' => 2]);

    $wiretapEvents = [];
    $dispatcher->addListener('*', function ($event) use (&$wiretapEvents) {
        $wiretapEvents[] = $event;
    });

    $dispatcher->dispatch($event1);
    $dispatcher->dispatch($event2);

    expect($wiretapEvents)->toHaveCount(2);
    expect($wiretapEvents[0])->toBe($event1);
    expect($wiretapEvents[1])->toBe($event2);
});

test('multiple wiretaps all receive the same events', function () {
    $dispatcher = new EventDispatcher();
    $event = new TestEvent();

    $firstWiretapCalled = false;
    $secondWiretapCalled = false;

    $dispatcher->addListener('*', function ($e) use (&$firstWiretapCalled, $event) {
        $firstWiretapCalled = true;
        expect($e)->toBe($event);
    });

    $dispatcher->addListener('*', function ($e) use (&$secondWiretapCalled, $event) {
        $secondWiretapCalled = true;
        expect($e)->toBe($event);
    });

    $dispatcher->dispatch($event);

    expect($firstWiretapCalled)->toBeTrue();
    expect($secondWiretapCalled)->toBeTrue();
});

test('wiretap gets events even if there are no regular listeners', function () {
    $dispatcher = new EventDispatcher();
    $event = new TestEvent();

    $wiretapCalled = false;
    $dispatcher->addListener('*', function () use (&$wiretapCalled) {
        $wiretapCalled = true;
    });

    $dispatcher->dispatch($event);
    expect($wiretapCalled)->toBeTrue();
});

// Test Helper Classes
class TestEvent extends Event {}

class ChildTestEvent extends TestEvent {}

class StoppableTestEvent extends Event implements StoppableEventInterface
{
    private bool $propagationStopped = false;

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}