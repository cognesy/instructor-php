<?php declare(strict_types=1);

namespace Cognesy\Stream\Sinks;

use Closure;
use Cognesy\Stream\Contracts\Reducer;

/**
 * A reducer that executes side effects for each element without accumulating.
 * Returns the count of processed elements.
 *
 * Example:
 * $count = transduce(
 *     new Map(fn($x) => $x * 2),
 *     new ForEachReducer(fn($x) => echo "$x\n"),
 *     [1, 2, 3]
 * );
 * // Outputs: 2, 4, 6
 * // Returns: 3
 */
final readonly class ForEachReducer implements Reducer
{
    /** @var Closure(mixed): void */
    private Closure $sideEffect;

    /**
     * @param Closure(mixed): void $sideEffect
     */
    public function __construct(Closure $sideEffect) {
        $this->sideEffect = $sideEffect;
    }

    #[\Override]
    public function init(): mixed {
        return 0;
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        ($this->sideEffect)($reducible);
        return $accumulator + 1;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $accumulator;
    }
}
