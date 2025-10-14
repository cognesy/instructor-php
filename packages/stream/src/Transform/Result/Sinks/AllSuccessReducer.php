<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Result\Sinks;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Support\Reduced;
use Cognesy\Utils\Result\Result;

/**
 * Returns Success if all Results are successful, otherwise first Failure.
 * Early terminates on first failure for efficiency.
 *
 * @example
 * $result = Transformation::define()
 *     ->withInput([Result::success(1), Result::failure('error'), Result::success(3)])
 *     ->withSink(new AllSuccessReducer())
 *     ->execute();
 * // Result::failure('error') - early termination
 */
final readonly class AllSuccessReducer implements Reducer
{
    #[\Override]
    public function init(): mixed {
        return Result::success(true);
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        if ($reducible instanceof Result && $reducible->isFailure()) {
            return new Reduced($reducible); // Early termination on first failure
        }
        return $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $accumulator;
    }
}
