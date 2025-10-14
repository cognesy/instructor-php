<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Result\Sinks;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Utils\Result\Result;

/**
 * Partitions stream into separate arrays of successful values and errors.
 *
 * @example
 * $partition = Transformation::define()
 *     ->withInput([Result::success(1), Result::failure('e1'), Result::success(2)])
 *     ->withSink(new PartitionResultsReducer())
 *     ->execute();
 * // ['successes' => [1, 2], 'failures' => ['e1']]
 */
final readonly class PartitionResultsReducer implements Reducer
{
    #[\Override]
    public function init(): mixed {
        return ['successes' => [], 'failures' => []];
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        if (!($reducible instanceof Result)) {
            return $accumulator;
        }

        if ($reducible->isSuccess()) {
            $accumulator['successes'][] = $reducible->unwrap();
        } else {
            $accumulator['failures'][] = $reducible->error();
        }

        return $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $accumulator;
    }
}
