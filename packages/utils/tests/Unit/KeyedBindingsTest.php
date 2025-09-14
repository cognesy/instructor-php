<?php declare(strict_types=1);

use Cognesy\Utils\Context\Context;
use Cognesy\Utils\Context\Key;
use Cognesy\Utils\Context\Layer;
use Cognesy\Utils\Time\ClockInterface;
use Cognesy\Utils\Time\SystemClock;

test('Context withKey/getKey stores and retrieves qualified binding', function () {
    $primary = Key::of('clock.primary', ClockInterface::class);
    $backup  = Key::of('clock.backup', ClockInterface::class);

    $ctx = Context::empty()
        ->withKey($primary, new SystemClock())
        ->withKey($backup, new SystemClock());

    $p = $ctx->getKey($primary);
    $b = $ctx->getKey($backup);

    expect($p)->toBeInstanceOf(SystemClock::class);
    expect($b)->toBeInstanceOf(SystemClock::class);
    expect($p)->not->toBe($b);
});

test('Context withKey enforces type via Key', function () {
    $key = Key::of('clock.primary', ClockInterface::class);
    $ctx = Context::empty();
    expect(fn() => $ctx->withKey($key, new stdClass()))->toThrow(TypeError::class);
});

test('Layer providesKey/providesFromKey compose qualified bindings', function () {
    $primary = Key::of('clock.primary', ClockInterface::class);
    $backup  = Key::of('clock.backup', ClockInterface::class);

    $l1 = Layer::providesKey($primary, new SystemClock());
    $l2 = Layer::providesFromKey($backup, fn(Context $c): ClockInterface => new SystemClock());

    $ctx = $l1->merge($l2)->applyTo(Context::empty());

    expect($ctx->getKey($primary))->toBeInstanceOf(SystemClock::class);
    expect($ctx->getKey($backup))->toBeInstanceOf(SystemClock::class);
});

