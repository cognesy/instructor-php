<?php declare(strict_types=1);

use Cognesy\Utils\Context\Context;
use Cognesy\Utils\Context\Exceptions\MissingServiceException;
use Cognesy\Utils\Time\ClockInterface;
use Cognesy\Utils\Time\SystemClock;

test('Context with/get returns typed instance', function () {
    $ctx = Context::empty()->with(ClockInterface::class, new SystemClock());
    $clock = $ctx->get(ClockInterface::class);
    expect($clock)->toBeInstanceOf(SystemClock::class);
});

test('Context tryGet returns Success when present', function () {
    $ctx = Context::empty()->with(ClockInterface::class, new SystemClock());
    $res = $ctx->tryGet(ClockInterface::class);
    expect($res->isSuccess())->toBeTrue();
    // valueOr for portability across Result implementations
    $val = $res->valueOr(null);
    expect($val)->toBeInstanceOf(SystemClock::class);
});

test('Context tryGet returns Failure(MissingService) when absent', function () {
    $ctx = Context::empty();
    $res = $ctx->tryGet(ClockInterface::class);
    expect($res->isFailure())->toBeTrue();
    $ex = $res->exceptionOr(null);
    expect($ex)->toBeInstanceOf(MissingServiceException::class);
    /** @var MissingServiceException $ex */
    expect($ex->class())->toBe(ClockInterface::class);
});

test('Context with() throws on type mismatch', function () {
    $ctx = Context::empty();
    expect(fn() => $ctx->with(Psr\Log\LoggerInterface::class, new SystemClock()))
        ->toThrow(TypeError::class);
});

test('Context get() throws MissingService when absent', function () {
    $ctx = Context::empty();
    expect(fn() => $ctx->get(ClockInterface::class))->toThrow(\Cognesy\Utils\Context\Exceptions\MissingServiceException::class);
});
