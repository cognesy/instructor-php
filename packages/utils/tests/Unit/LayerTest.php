<?php declare(strict_types=1);

use Cognesy\Utils\Context\Context;
use Cognesy\Utils\Context\Layer;
use Cognesy\Utils\Time\ClockInterface;
use Cognesy\Utils\Time\SystemClock;

test('Layer::provides adds service', function () {
    $clock = new SystemClock();
    $layer = Layer::provides(ClockInterface::class, $clock);
    $ctx = $layer->applyTo(Context::empty());

    expect($ctx->get(ClockInterface::class))->toBe($clock);
});

test('Layer::providesFrom builds service via factory', function () {
    $layer = Layer::providesFrom(ClockInterface::class, fn(Context $c): ClockInterface => new SystemClock());
    $ctx = $layer->applyTo(Context::empty());

    expect($ctx->get(ClockInterface::class))->toBeInstanceOf(SystemClock::class);
});

test('Layer::merge uses right-bias for duplicates', function () {
    $leftClock = new SystemClock();
    $rightClock = new SystemClock();

    $left = Layer::provides(ClockInterface::class, $leftClock);
    $right = Layer::provides(ClockInterface::class, $rightClock);

    $merged = $left->merge($right);
    $ctx = $merged->applyTo(Context::empty());

    expect($ctx->get(ClockInterface::class))->toBe($rightClock);
});

test('Layer::dependsOn composes sequentially with context dependency', function () {
    $provider = Layer::provides(ClockInterface::class, new SystemClock());
    $dependent = Layer::providesFrom(SystemClock::class, function (Context $ctx): SystemClock {
        // reuse the provided ClockInterface as the SystemClock binding
        return $ctx->get(ClockInterface::class);
    });

    $composed = $dependent->dependsOn($provider);
    $ctx = $composed->applyTo(Context::empty());

    $asInterface = $ctx->get(ClockInterface::class);
    $asConcrete  = $ctx->get(SystemClock::class);

    expect($asConcrete)->toBe($asInterface);
});

