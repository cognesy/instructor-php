<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Result\Transducers;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Support\CallableReducer;
use Cognesy\Utils\Result\Result;

/**
 * Filters stream to only failed Results (useful for error aggregation).
 *
 * @example
 * new FilterFailure()
 * // [Result::success(1), Result::failure('err1'), Result::failure('err2')]
 * // → [Result::failure('err1'), Result::failure('err2')]
 */
final readonly class FilterFailure implements Transducer
{
    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new CallableReducer(
            stepFn: function(mixed $accumulator, mixed $reducible) use ($reducer): mixed {
                if ($reducible instanceof Result && $reducible->isFailure()) {
                    return $reducer->step($accumulator, $reducible);
                }
                return $accumulator;
            },
            completeFn: $reducer->complete(...),
            initFn: $reducer->init(...),
        );
    }
}
