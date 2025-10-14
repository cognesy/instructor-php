<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Result\Sinks;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Utils\Result\Result;

/**
 * Counts failed Results.
 *
 * @example
 * $count = Transformation::define()
 *     ->withInput([Result::success(1), Result::failure('e1'), Result::failure('e2')])
 *     ->withSink(new CountFailureReducer())
 *     ->execute();
 * // 2
 */
final readonly class CountFailureReducer implements Reducer
{
    #[\Override]
    public function init(): mixed {
        return 0;
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        if ($reducible instanceof Result && $reducible->isFailure()) {
            return $accumulator + 1;
        }
        return $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $accumulator;
    }
}
