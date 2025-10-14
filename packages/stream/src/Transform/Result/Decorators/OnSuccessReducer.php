<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Result\Decorators;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Utils\Result\Result;

/**
 * Wraps a reducer to only process successful Results, unwrapping values.
 * Failures are skipped without affecting the accumulator.
 *
 * @example
 * $sum = Transformation::define()
 *     ->withInput([Result::success(1), Result::failure('e'), Result::success(2)])
 *     ->withSink(new OnSuccessReducer(new SumReducer()))
 *     ->execute();
 * // 3 (failure skipped)
 */
final readonly class OnSuccessReducer implements Reducer
{
    public function __construct(
        private Reducer $inner
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        if ($reducible instanceof Result && $reducible->isSuccess()) {
            return $this->inner->step($accumulator, $reducible->unwrap());
        }
        return $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }
}
