<?php declare(strict_types=1);

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Events\Dispatchers\SymfonyEventDispatcher;
use Cognesy\Events\EventBusResolver;

interface ResolverCoverageMarker {}

class ResolverCoverageBaseEvent {}

class ResolverCoverageChildEvent extends ResolverCoverageBaseEvent implements ResolverCoverageMarker {}

// Guards regression coverage from instructor-icd2 (resolver wrapping semantics).
it('uses CanHandleEvents implementations directly (no extra wrapper)', function () {
    $base = new EventDispatcher();
    $resolver = EventBusResolver::using($base);
    $listener = static fn(object $event): null => null;

    $resolver->addListener(ResolverCoverageChildEvent::class, $listener);

    $listeners = iterator_to_array($base->getListenersForEvent(new ResolverCoverageChildEvent()), false);
    expect($listeners)->toHaveCount(1);
    expect($listeners[0])->toBe($listener);
});

it('wraps plain PSR dispatchers and keeps listener visibility local to resolver provider', function () {
    $parent = new Symfony\Component\EventDispatcher\EventDispatcher();
    $calls = [];

    $parent->addListener(
        ResolverCoverageChildEvent::class,
        static function (object $event) use (&$calls): void { $calls[] = 'parent'; },
    );

    $resolver = EventBusResolver::using($parent);
    $resolver->addListener(
        ResolverCoverageBaseEvent::class,
        static function (object $event) use (&$calls): void { $calls[] = 'base'; },
    );
    $resolver->addListener(
        ResolverCoverageMarker::class,
        static function (object $event) use (&$calls): void { $calls[] = 'interface'; },
    );
    $resolver->wiretap(
        static function (object $event) use (&$calls): void { $calls[] = 'tap'; },
    );

    $visible = iterator_to_array($resolver->getListenersForEvent(new ResolverCoverageChildEvent()), false);
    expect($visible)->toHaveCount(3);

    $resolver->dispatch(new ResolverCoverageChildEvent());
    expect($calls)->toBe(['base', 'interface', 'tap', 'parent']);
});

it('keeps wildcard and wiretap behavior consistent across core and Symfony-backed dispatchers', function (callable $makeDispatcher) {
    /** @var CanHandleEvents $dispatcher */
    $dispatcher = $makeDispatcher();
    $calls = [];

    $dispatcher->addListener('*', static function (object $event) use (&$calls): void {
        $calls[] = 'star';
    }, 20);
    $dispatcher->wiretap(static function (object $event) use (&$calls): void {
        $calls[] = 'tap';
    });

    $dispatcher->dispatch(new ResolverCoverageChildEvent());
    expect($calls)->toBe(['star', 'tap']);
})->with([
    'core-dispatcher' => [static fn(): CanHandleEvents => new EventDispatcher()],
    'symfony-bridge' => [static fn(): CanHandleEvents => new SymfonyEventDispatcher(new Symfony\Component\EventDispatcher\EventDispatcher())],
    'resolver-wrapped-symfony' => [static fn(): CanHandleEvents => EventBusResolver::using(new Symfony\Component\EventDispatcher\EventDispatcher())],
]);

