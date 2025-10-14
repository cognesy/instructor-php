<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Result\Sinks;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Utils\Result\Result;

/**
 * Counts successful Results.
 *
 * @example
 * $count = Transformation::define()
 *     ->withInput([Result::success(1), Result::failure('e1'), Result::success(2)])
 *     ->withSink(new CountSuccessReducer())
 *     ->execute();
 * // 2
 */
final readonly class CountSuccessReducer implements Reducer
{
    #[\Override]
    public function init(): mixed {
        return 0;
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        if ($reducible instanceof Result && $reducible->isSuccess()) {
            return $accumulator + 1;
        }
        return $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $accumulator;
    }
}
