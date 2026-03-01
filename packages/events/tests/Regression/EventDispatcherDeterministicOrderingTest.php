<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;

interface OrderingRegressionMarker {}

class OrderingRegressionBaseEvent {}

class OrderingRegressionChildEvent extends OrderingRegressionBaseEvent implements OrderingRegressionMarker {}

it('prioritizes listeners globally across class hierarchy buckets', function () {
    $dispatcher = new EventDispatcher();
    $calls = [];

    $dispatcher->addListener(
        OrderingRegressionChildEvent::class,
        static function (object $event) use (&$calls): void { $calls[] = 'child-low'; },
        -10,
    );
    $dispatcher->addListener(
        OrderingRegressionMarker::class,
        static function (object $event) use (&$calls): void { $calls[] = 'interface-high'; },
        100,
    );
    $dispatcher->addListener(
        OrderingRegressionBaseEvent::class,
        static function (object $event) use (&$calls): void { $calls[] = 'parent-mid'; },
        0,
    );

    $dispatcher->dispatch(new OrderingRegressionChildEvent());

    expect($calls)->toBe(['interface-high', 'parent-mid', 'child-low']);
});

it('keeps same-type listener ordering priority-first then registration order', function () {
    $dispatcher = new EventDispatcher();
    $calls = [];

    $dispatcher->addListener(
        OrderingRegressionChildEvent::class,
        static function (object $event) use (&$calls): void { $calls[] = 'second'; },
        10,
    );
    $dispatcher->addListener(
        OrderingRegressionChildEvent::class,
        static function (object $event) use (&$calls): void { $calls[] = 'first'; },
        20,
    );
    $dispatcher->addListener(
        OrderingRegressionChildEvent::class,
        static function (object $event) use (&$calls): void { $calls[] = 'third'; },
        10,
    );

    $dispatcher->dispatch(new OrderingRegressionChildEvent());

    expect($calls)->toBe(['first', 'second', 'third']);
});

it('always executes taps after class listeners and with deterministic tap order', function () {
    $dispatcher = new EventDispatcher();
    $calls = [];

    $dispatcher->addListener(
        OrderingRegressionChildEvent::class,
        static function (object $event) use (&$calls): void { $calls[] = 'class'; },
        0,
    );
    $dispatcher->addListener(
        '*',
        static function (object $event) use (&$calls): void { $calls[] = 'tap-low'; },
        0,
    );
    $dispatcher->addListener(
        '*',
        static function (object $event) use (&$calls): void { $calls[] = 'tap-high'; },
        100,
    );

    $dispatcher->dispatch(new OrderingRegressionChildEvent());

    expect($calls)->toBe(['class', 'tap-high', 'tap-low']);
});
