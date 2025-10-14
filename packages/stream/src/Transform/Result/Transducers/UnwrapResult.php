<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Result\Transducers;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Support\CallableReducer;
use Cognesy\Utils\Result\Result;

/**
 * Unwraps successful Results to their values, filtering out failures.
 *
 * @example
 * new UnwrapResult()
 * // [Result::success(1), Result::failure('err'), Result::success(2)]
 * // → [1, 2]
 */
final readonly class UnwrapResult implements Transducer
{
    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new CallableReducer(
            stepFn: function(mixed $accumulator, mixed $reducible) use ($reducer): mixed {
                if ($reducible instanceof Result) {
                    if ($reducible->isSuccess()) {
                        return $reducer->step($accumulator, $reducible->unwrap());
                    }
                    return $accumulator; // Skip failures
                }
                // Not a Result - pass through
                return $reducer->step($accumulator, $reducible);
            },
            completeFn: $reducer->complete(...),
            initFn: $reducer->init(...),
        );
    }
}
