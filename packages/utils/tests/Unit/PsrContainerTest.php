<?php declare(strict_types=1);

use Cognesy\Utils\Context\Context;
use Cognesy\Utils\Context\Key;
use Cognesy\Utils\Context\Psr\ContextContainer;
use Cognesy\Utils\Context\Psr\TypedContainer;
use Cognesy\Utils\Time\ClockInterface;
use Cognesy\Utils\Time\SystemClock;
use Psr\Container\ContainerInterface;

test('ContextContainer exposes class-string bindings via PSR', function () {
    $ctx = Context::empty()->with(ClockInterface::class, new SystemClock());
    $psr = new ContextContainer($ctx);

    expect($psr->has(ClockInterface::class))->toBeTrue();
    $value = $psr->get(ClockInterface::class);
    expect($value)->toBeInstanceOf(SystemClock::class);
});

test('ContextContainer exposes keyed bindings via PSR using types map', function () {
    $primary = Key::of('clock.primary', ClockInterface::class);
    $ctx = Context::empty()->withKey($primary, new SystemClock());

    $psr = new ContextContainer($ctx, [$primary->id => $primary->type]);
    expect($psr->has($primary->id))->toBeTrue();
    expect($psr->get($primary->id))->toBeInstanceOf(SystemClock::class);
});

test('TypedContainer enforces types for class-string IDs', function () {
    $ctx = Context::empty()->with(ClockInterface::class, new SystemClock());
    $psr = new ContextContainer($ctx);
    $typed = new TypedContainer($psr);

    $clock = $typed->get(ClockInterface::class);
    expect($clock)->toBeInstanceOf(SystemClock::class);
});

test('TypedContainer enforces types for keyed IDs', function () {
    $key = Key::of('clock.primary', ClockInterface::class);
    $ctx = Context::empty()->withKey($key, new SystemClock());
    $psr = new ContextContainer($ctx, [$key->id => $key->type]);
    $typed = new TypedContainer($psr);

    $clock = $typed->getKey($key);
    expect($clock)->toBeInstanceOf(SystemClock::class);
});

test('TypedContainer detects mismatched types from arbitrary PSR container', function () {
    // PSR stub that returns wrong type
    $bad = new class implements ContainerInterface {
        public function get(string $id): mixed { return new stdClass(); }
        public function has(string $id): bool { return true; }
    };

    $typed = new TypedContainer($bad);
    expect(fn() => $typed->get(ClockInterface::class))->toThrow(TypeError::class);
});

