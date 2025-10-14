<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Result\Transducers;

use Closure;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Support\CallableReducer;
use Cognesy\Utils\Result\Result;

/**
 * Chains operations that return Results, flattening nested Results.
 *
 * @example
 * // Chain Result-returning operations
 * new ThenResult(fn(int $id) => $repository->findById($id))
 * // Result<int> → Result<User>
 *
 * // Failures short-circuit
 * Result::failure('error')->then(...) → Result::failure('error')
 */
final readonly class ThenResult implements Transducer
{
    /**
     * @param Closure(mixed): Result $thenFn Function returning Result
     */
    public function __construct(private Closure $thenFn) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new CallableReducer(
            stepFn: function(mixed $accumulator, mixed $reducible) use ($reducer): mixed {
                if ($reducible instanceof Result) {
                    $chained = $reducible->then($this->thenFn);
                    return $reducer->step($accumulator, $chained);
                }
                // Not a Result - wrap in Result and chain
                $result = Result::from($reducible)->then($this->thenFn);
                return $reducer->step($accumulator, $result);
            },
            completeFn: $reducer->complete(...),
            initFn: $reducer->init(...),
        );
    }
}
