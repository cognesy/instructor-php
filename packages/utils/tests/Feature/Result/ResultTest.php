<?php

use Cognesy\Utils\Exceptions\CompositeException;
use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Result;
use Cognesy\Utils\Result\Success;

test('it creates a success result', function () {
    $result = Result::success(42);

    expect($result)->toBeInstanceOf(Success::class)
        ->and($result->isSuccess())->toBeTrue()
        ->and($result->isFailure())->toBeFalse()
        ->and($result->unwrap())->toBe(42);
});

test('it creates a failure result', function () {
    $result = Result::failure('An error occurred');

    expect($result)->toBeInstanceOf(Result::class)
        ->and($result->isSuccess())->toBeFalse()
        ->and($result->isFailure())->toBeTrue()
        ->and($result->error())->toBe('An error occurred')
        ->and($result->errorMessage())->toBe('An error occurred');
});

test('failure exposes throwable representation', function () {
    $result = Result::failure('Boom');

    expect($result->exception())->toBeInstanceOf(\RuntimeException::class)
        ->and($result->exception()->getMessage())->toBe('Boom');
});

test('it applies a success function', function () {
    $result = Result::success(21);
    $transformedResult = $result->then(fn ($value) => $value * 2);

    expect($transformedResult)->toBeInstanceOf(Success::class)
        ->and($transformedResult->isSuccess())->toBeTrue()
        ->and($transformedResult->unwrap())->toBe(42);
});

test('it applies a failure function', function () {
    $result = Result::failure('An error occurred');
    $transformedResult = $result->then(fn ($value) => $value * 2);

    expect($transformedResult)->toBeInstanceOf(Failure::class)
        ->and($transformedResult->isFailure())->toBeTrue()
        ->and($transformedResult->error())->toBe('An error occurred');
});

test('it recovers from a success result', function () {
    $result = Result::success(42);
    $transformedResult = $result->recover(fn ($error) => "Recovered from error: $error");

    expect($transformedResult)->toBeInstanceOf(Success::class)
        ->and($transformedResult->isSuccess())->toBeTrue()
        ->and($transformedResult->unwrap())->toBe(42);
});

test('it recovers from a failure result', function () {
    $result = Result::failure('An error occurred');
    $transformedResult = $result->recover(fn ($error) => "Recovered from error: $error");

    expect($transformedResult)->toBeInstanceOf(Success::class)
        ->and($transformedResult->isSuccess())->toBeTrue()
        ->and($transformedResult->unwrap())->toBe('Recovered from error: An error occurred');
});

test('it tries a success function', function () {
    $result = Result::try(fn () => 42);

    expect($result)->toBeInstanceOf(Success::class)
        ->and($result->isSuccess())->toBeTrue()
        ->and($result->unwrap())->toBe(42);
});

test('it tries a failure function', function () {
    $result = Result::try(fn () => throw new \Exception('An error occurred'));

    expect($result)->toBeInstanceOf(Failure::class)
        ->and($result->isFailure())->toBeTrue()
        ->and($result->error())->toBeInstanceOf(\Exception::class)
        ->and($result->errorMessage())->toContain('An error occurred');
});

test('it builds results via from helper', function () {
    $success = Result::from(10);
    $existing = Result::from($success);
    $failure = Result::from(new \RuntimeException('boom'));

    expect($success)->toBeInstanceOf(Success::class)
        ->and($existing)->toBe($success)
        ->and($failure)->toBeInstanceOf(Failure::class)
        ->and($failure->error())->toBeInstanceOf(\RuntimeException::class);
});

test('it reports specific success states', function () {
    expect(Result::success(null)->isSuccessAndNull())->toBeTrue()
        ->and(Result::success(true)->isSuccessAndTrue())->toBeTrue()
        ->and(Result::success(false)->isSuccessAndFalse())->toBeTrue()
        ->and(Result::failure('err')->isSuccessAndNull())->toBeFalse()
        ->and(Result::failure('err')->isSuccessAndTrue())->toBeFalse()
        ->and(Result::failure('err')->isSuccessAndFalse())->toBeFalse();
});

test('it provides fallbacks for value and exception', function () {
    $success = Result::success('value');
    $failure = Result::failure('error');
    $exception = $failure->exceptionOr('fallback');

    expect($success->valueOr('default'))->toBe('value')
        ->and($failure->valueOr('default'))->toBe('default')
        ->and($success->exceptionOr('fallback'))->toBe('fallback');

    expect($exception)->toBeInstanceOf(\Throwable::class)
        ->and($exception->getMessage())->toBe('error');
});

test('it maps values and handles thrown exceptions', function () {
    $mapped = Result::success(2)->map(fn (int $value) => $value * 2);
    $flatMapped = Result::success(2)->map(fn (int $value) => Result::success($value + 3));
    $failed = Result::success(2)->map(fn () => throw new \RuntimeException('map failed'));

    expect($mapped->unwrap())->toBe(4)
        ->and($flatMapped->unwrap())->toBe(5)
        ->and($failed)->toBeInstanceOf(Failure::class)
        ->and($failed->error())->toBeInstanceOf(\RuntimeException::class);
});

test('ensure validates successful values', function () {
    $ok = Result::success(4)->ensure(fn (int $value) => $value > 0, fn () => 'negative');
    $invalid = Result::success(-1)->ensure(fn (int $value) => $value > 0, fn (int $value) => "invalid: $value");
    $bubbled = Result::success(-1)->ensure(fn () => false, fn () => Result::failure('from result'));
    $skipped = Result::failure('fail')->ensure(fn () => true, fn () => 'never');

    expect($ok)->toBeInstanceOf(Success::class)
        ->and($invalid)->toBeInstanceOf(Failure::class)
        ->and($invalid->error())->toBe('invalid: -1')
        ->and($bubbled)->toBeInstanceOf(Failure::class)
        ->and($bubbled->error())->toBe('from result')
        ->and($skipped)->toBeInstanceOf(Failure::class)
        ->and($skipped->error())->toBe('fail');
});

test('tap observes successful values', function () {
    $observed = null;
    $result = Result::success('ok')->tap(function (string $value) use (&$observed) {
        $observed = $value;
    });
    $failure = Result::failure('err')->tap(function () use (&$observed) {
        $observed = 'should not change';
    });
    $exception = Result::success('boom')->tap(fn () => throw new \RuntimeException('tap failed'));

    expect($observed)->toBe('ok')
        ->and($result)->toBeInstanceOf(Success::class)
        ->and($failure)->toBeInstanceOf(Failure::class)
        ->and($failure->error())->toBe('err')
        ->and($exception)->toBeInstanceOf(Failure::class)
        ->and($exception->error())->toBeInstanceOf(\RuntimeException::class);
});

test('mapError transforms failure payloads', function () {
    $transformed = Result::failure('bad')->mapError(fn (string $error) => strtoupper($error));
    $unaffected = Result::success('ok')->mapError(fn () => 'unused');
    $replacement = Result::failure('bad')->mapError(fn () => Result::success('recovered'));

    expect($transformed->error())->toBe('BAD')
        ->and($unaffected->unwrap())->toBe('ok')
        ->and($replacement)->toBeInstanceOf(Success::class)
        ->and($replacement->unwrap())->toBe('recovered');
});

test('tryAll aggregates results and errors', function () {
    $success = Result::tryAll(['a'],
        fn (string $value) => strtoupper($value),
        fn (string $value) => $value . $value,
    );

    $failure = Result::tryAll(['boom'],
        fn () => throw new \RuntimeException('fail1'),
        fn () => throw new \RuntimeException('fail2'),
    );

    expect($success)->toBeInstanceOf(Success::class)
        ->and($success->unwrap())->toBe(['A', 'aa'])
        ->and($failure)->toBeInstanceOf(Failure::class)
        ->and($failure->error())->toBeInstanceOf(CompositeException::class);
});

test('tryUntil stops when condition met', function () {
    $result = Result::tryUntil(fn ($value) => $value === 'target', [],
        fn () => 'miss',
        fn () => 'target',
        fn () => 'after',
    );

    $failure = Result::tryUntil(fn ($value) => $value === 'target', [],
        fn () => throw new \RuntimeException('fail1'),
        fn () => 'miss',
    );

    expect($result)->toBeInstanceOf(Success::class)
        ->and($result->unwrap())->toBe('target')
        ->and($failure)->toBeInstanceOf(Failure::class)
        ->and($failure->error())->toBeInstanceOf(CompositeException::class);
});

test('it inspects types and instances', function () {
    $object = new \stdClass();

    expect(Result::success(5)->isType('integer'))->toBeTrue()
        ->and(Result::success($object)->isInstanceOf(\stdClass::class))->toBeTrue()
        ->and(Result::failure('err')->isType('integer'))->toBeFalse()
        ->and(Result::failure('err')->isInstanceOf(\stdClass::class))->toBeFalse();
});

test('it evaluates predicates with matches', function () {
    $success = Result::success(10);
    $failure = Result::failure('err');

    expect($success->matches(fn (int $value) => $value > 5))->toBeTrue()
        ->and($success->matches(fn (int $value) => $value > 10))->toBeFalse()
        ->and($failure->matches(fn () => true))->toBeFalse();
});

test('ifSuccess and ifFailure trigger callbacks appropriately', function () {
    $log = [];

    Result::success('ok')->ifSuccess(function ($value) use (&$log) {
        $log[] = $value;
    })->ifFailure(function () use (&$log) {
        $log[] = 'failure';
    });

    Result::failure('err')->ifSuccess(function () use (&$log) {
        $log[] = 'success';
    })->ifFailure(function ($error) use (&$log) {
        $log[] = $error->getMessage();
    });

    expect($log)->toBe(['ok', 'err']);
});
