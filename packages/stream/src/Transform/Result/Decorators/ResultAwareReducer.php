<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Result\Decorators;

use Closure;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Utils\Result\Result;

/**
 * Wraps a reducer with separate handling for success and failure cases.
 * Provides maximum flexibility for Result-aware processing.
 *
 * @example
 * $stats = Transformation::define()
 *     ->withInput([Result::success(1), Result::failure('e'), Result::success(2)])
 *     ->withSink(new ResultAwareReducer(
 *         onSuccess: fn($acc, $val) => ['count' => $acc['count'] + 1, 'sum' => $acc['sum'] + $val, 'errors' => $acc['errors']],
 *         onFailure: fn($acc, $err) => ['count' => $acc['count'], 'sum' => $acc['sum'], 'errors' => $acc['errors'] + 1],
 *         init: fn() => ['count' => 0, 'sum' => 0, 'errors' => 0]
 *     ))
 *     ->execute();
 * // ['count' => 2, 'sum' => 3, 'errors' => 1]
 */
final readonly class ResultAwareReducer implements Reducer
{
    /**
     * @param Closure(mixed, mixed): mixed $onSuccess Handler for successful Results
     * @param Closure(mixed, mixed): mixed $onFailure Handler for failed Results
     * @param Closure(): mixed $init Initial accumulator factory
     * @param Closure(mixed): mixed|null $complete Optional completion handler
     */
    public function __construct(
        private Closure $onSuccess,
        private Closure $onFailure,
        private Closure $init,
        private ?Closure $complete = null,
    ) {}

    #[\Override]
    public function init(): mixed {
        return ($this->init)();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        if (!($reducible instanceof Result)) {
            return $accumulator;
        }

        if ($reducible->isSuccess()) {
            return ($this->onSuccess)($accumulator, $reducible->unwrap());
        }

        return ($this->onFailure)($accumulator, $reducible->error());
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        if ($this->complete !== null) {
            return ($this->complete)($accumulator);
        }
        return $accumulator;
    }
}
