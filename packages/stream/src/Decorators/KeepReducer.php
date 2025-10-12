<?php declare(strict_types=1);

namespace Cognesy\Stream\Decorators;

use Closure;
use Cognesy\Stream\Contracts\Reducer;

/**
 * Applies a mapping function that returns a transformed value or null, then:
 * - Passes the mapped/transformed value if not null.
 * - Skips the value if null.
 *
 * Example:
 * ['a', 'b', 'c', 'd']
 * Map function: fn($x) => ($x === 'b') ? null : strtoupper($x)
 * Result: ['A', 'C', 'D']
 */
final class KeepReducer implements Reducer
{
    /**
     * @param Closure(mixed): mixed $mapFn
     */
    public function __construct(
        private Closure $mapFn,
        private Reducer $reducer,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->reducer->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        $mapped = ($this->mapFn)($reducible);
        if ($mapped === null) {
            return $accumulator;
        }
        return $this->reducer->step($accumulator, $mapped);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->reducer->complete($accumulator);
    }
}
