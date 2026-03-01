<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Result\Transducers;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Support\CallableReducer;
use Cognesy\Utils\Result\Result;

/**
 * Partitions Results into [successes, failures] tuple.
 *
 * @example
 * new PartitionResults()
 * // [Result::success(1), Result::failure('e1'), Result::success(2)]
 * // → Accumulates to: [successes: [1, 2], failures: ['e1']]
 */
final readonly class PartitionResults implements Transducer
{
    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new CallableReducer(
            stepFn: function(mixed $accumulator, mixed $reducible): mixed {
                if (!is_array($accumulator)) {
                    $accumulator = ['successes' => [], 'failures' => []];
                }
                if (!isset($accumulator['successes']) || !is_array($accumulator['successes'])) {
                    $accumulator['successes'] = [];
                }
                if (!isset($accumulator['failures']) || !is_array($accumulator['failures'])) {
                    $accumulator['failures'] = [];
                }

                if (!($reducible instanceof Result)) {
                    return $accumulator;
                }

                // Accumulator format: ['successes' => [...], 'failures' => [...]]
                if ($reducible->isSuccess()) {
                    $accumulator['successes'][] = $reducible->unwrap();
                } else {
                    $accumulator['failures'][] = $reducible->error();
                }

                return $accumulator;
            },
            completeFn: $reducer->complete(...),
            initFn: fn() => ['successes' => [], 'failures' => []],
        );
    }
}
