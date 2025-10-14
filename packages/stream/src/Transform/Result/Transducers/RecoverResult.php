<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Result\Transducers;

use Closure;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Support\CallableReducer;
use Cognesy\Utils\Result\Result;

/**
 * Recovers from Result failures with fallback values.
 *
 * @example
 * // Provide default on failure
 * new RecoverResult(fn($error) => defaultUser())
 * // Result::failure('not found') → Result::success(defaultUser())
 */
final readonly class RecoverResult implements Transducer
{
    /**
     * @param Closure(\Throwable): mixed $recoverFn
     */
    public function __construct(private Closure $recoverFn) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new CallableReducer(
            stepFn: function(mixed $accumulator, mixed $reducible) use ($reducer): mixed {
                if ($reducible instanceof Result) {
                    $recovered = $reducible->recover($this->recoverFn);
                    return $reducer->step($accumulator, $recovered);
                }
                // Not a Result - pass through
                return $reducer->step($accumulator, $reducible);
            },
            completeFn: $reducer->complete(...),
            initFn: $reducer->init(...),
        );
    }
}
