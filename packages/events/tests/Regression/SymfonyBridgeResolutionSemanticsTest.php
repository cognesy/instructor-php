<?php declare(strict_types=1);

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Events\Dispatchers\SymfonyEventDispatcher;

interface BridgeResolutionMarker {}

class BridgeResolutionBaseEvent {}

class BridgeResolutionChildEvent extends BridgeResolutionBaseEvent implements BridgeResolutionMarker {}

/**
 * Guards regression from instructor-vp8v:
 * Core and Symfony bridge must resolve parent/interface listeners consistently.
 */
it('resolves parent and interface listeners consistently in getListenersForEvent', function (CanHandleEvents $dispatcher) {
    $onParent = static fn(object $event): null => null;
    $onInterface = static fn(object $event): null => null;

    $dispatcher->addListener(BridgeResolutionBaseEvent::class, $onParent);
    $dispatcher->addListener(BridgeResolutionMarker::class, $onInterface);

    $listeners = iterator_to_array($dispatcher->getListenersForEvent(new BridgeResolutionChildEvent()), false);

    expect($listeners)->toHaveCount(2);
    expect($listeners[0])->toBe($onParent);
    expect($listeners[1])->toBe($onInterface);
})->with([
    'core-dispatcher' => [new EventDispatcher()],
    'symfony-bridge' => [new SymfonyEventDispatcher(new Symfony\Component\EventDispatcher\EventDispatcher())],
]);

it('dispatches to parent and interface listeners consistently', function (CanHandleEvents $dispatcher) {
    $calls = [];

    $dispatcher->addListener(
        BridgeResolutionBaseEvent::class,
        static function (object $event) use (&$calls): void { $calls[] = 'parent'; },
    );
    $dispatcher->addListener(
        BridgeResolutionMarker::class,
        static function (object $event) use (&$calls): void { $calls[] = 'interface'; },
    );

    $dispatcher->dispatch(new BridgeResolutionChildEvent());

    expect($calls)->toBe(['parent', 'interface']);
})->with([
    'core-dispatcher' => [new EventDispatcher()],
    'symfony-bridge' => [new SymfonyEventDispatcher(new Symfony\Component\EventDispatcher\EventDispatcher())],
]);
