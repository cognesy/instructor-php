<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Result\Transducers;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Support\CallableReducer;
use Cognesy\Utils\Result\Result;

/**
 * Filters stream to only successful Results.
 *
 * @example
 * // Keep only successes
 * new FilterSuccess()
 * // [Result::success(1), Result::failure('err'), Result::success(2)]
 * // → [Result::success(1), Result::success(2)]
 */
final readonly class FilterSuccess implements Transducer
{
    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new CallableReducer(
            stepFn: function(mixed $accumulator, mixed $reducible) use ($reducer): mixed {
                if ($reducible instanceof Result && $reducible->isSuccess()) {
                    return $reducer->step($accumulator, $reducible);
                }
                return $accumulator;
            },
            completeFn: $reducer->complete(...),
            initFn: $reducer->init(...),
        );
    }
}
