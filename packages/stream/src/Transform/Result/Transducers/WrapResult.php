<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Result\Transducers;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Support\CallableReducer;
use Cognesy\Utils\Result\Result;

/**
 * Wraps non-Result values in Success Results.
 *
 * @example
 * new WrapResult()
 * // [1, 2, 3] → [Result::success(1), Result::success(2), Result::success(3)]
 */
final readonly class WrapResult implements Transducer
{
    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new CallableReducer(
            stepFn: function(mixed $accumulator, mixed $reducible) use ($reducer): mixed {
                $result = $reducible instanceof Result
                    ? $reducible
                    : Result::success($reducible);
                return $reducer->step($accumulator, $result);
            },
            completeFn: $reducer->complete(...),
            initFn: $reducer->init(...),
        );
    }
}
