<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Result\Transducers;

use Closure;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Support\CallableReducer;
use Cognesy\Utils\Result\Result;

/**
 * Maps over Result failure values, preserving successes.
 *
 * @example
 * // Enrich error messages
 * new MapErrorResult(fn($err) => "API Error: $err")
 */
final readonly class MapErrorResult implements Transducer
{
    /**
     * @param Closure(mixed): mixed $mapErrorFn
     */
    public function __construct(private Closure $mapErrorFn) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new CallableReducer(
            stepFn: function(mixed $accumulator, mixed $reducible) use ($reducer): mixed {
                if ($reducible instanceof Result) {
                    $mapped = $reducible->mapError($this->mapErrorFn);
                    return $reducer->step($accumulator, $mapped);
                }
                return $reducer->step($accumulator, $reducible);
            },
            completeFn: $reducer->complete(...),
            initFn: $reducer->init(...),
        );
    }
}
