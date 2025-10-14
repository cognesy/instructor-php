<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Result\Transducers;

use Closure;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Support\CallableReducer;
use Cognesy\Utils\Result\Result;

/**
 * Maps over Result success values, preserving failures.
 *
 * @example
 * // Transform successful results
 * new MapResult(fn(User $user) => $user->email)
 * // Result<User> → Result<string>
 *
 * // Failures pass through unchanged
 * Result::failure('error') → Result::failure('error')
 */
final readonly class MapResult implements Transducer
{
    /**
     * @param Closure(mixed): mixed $mapFn Function to apply to success values
     */
    public function __construct(private Closure $mapFn) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new CallableReducer(
            stepFn: function(mixed $accumulator, mixed $reducible) use ($reducer): mixed {
                if ($reducible instanceof Result) {
                    $mapped = $reducible->map($this->mapFn);
                    return $reducer->step($accumulator, $mapped);
                }
                // Not a Result - apply function directly
                $result = ($this->mapFn)($reducible);
                return $reducer->step($accumulator, $result);
            },
            completeFn: $reducer->complete(...),
            initFn: $reducer->init(...),
        );
    }
}
