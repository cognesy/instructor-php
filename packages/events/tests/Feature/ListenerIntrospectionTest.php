<?php

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Events\Event;

class ListenerIntrospectionBaseEvent extends Event {}
class ListenerIntrospectionChildEvent extends ListenerIntrospectionBaseEvent {}
class ListenerIntrospectionOtherEvent extends Event {}

test('hasListenersFor is false when nothing is registered', function () {
    $dispatcher = new EventDispatcher();
    expect($dispatcher->hasListenersFor(ListenerIntrospectionBaseEvent::class))->toBeFalse();
});

test('hasListenersFor matches class-specific listeners only', function () {
    $dispatcher = new EventDispatcher();
    $dispatcher->addListener(ListenerIntrospectionBaseEvent::class, fn() => null);

    expect($dispatcher->hasListenersFor(ListenerIntrospectionBaseEvent::class))->toBeTrue();
    expect($dispatcher->hasListenersFor(ListenerIntrospectionOtherEvent::class))->toBeFalse();
});

test('hasListenersFor matches listeners registered on a parent class', function () {
    $dispatcher = new EventDispatcher();
    $dispatcher->addListener(ListenerIntrospectionBaseEvent::class, fn() => null);

    expect($dispatcher->hasListenersFor(ListenerIntrospectionChildEvent::class))->toBeTrue();
});

test('hasListenersFor is true for any class when a wiretap is registered', function () {
    $dispatcher = new EventDispatcher();
    $dispatcher->wiretap(fn() => null);

    expect($dispatcher->hasListenersFor(ListenerIntrospectionBaseEvent::class))->toBeTrue();
    expect($dispatcher->hasListenersFor(ListenerIntrospectionOtherEvent::class))->toBeTrue();
});

test('hasListenersFor consults the parent dispatcher chain', function () {
    $parent = new EventDispatcher('parent');
    $parent->addListener(ListenerIntrospectionBaseEvent::class, fn() => null);
    $child = new EventDispatcher('child', $parent);

    expect($child->hasListenersFor(ListenerIntrospectionBaseEvent::class))->toBeTrue();
    expect($child->hasListenersFor(ListenerIntrospectionOtherEvent::class))->toBeFalse();
});
