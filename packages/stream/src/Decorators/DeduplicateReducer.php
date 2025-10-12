<?php declare(strict_types=1);

namespace Cognesy\Stream\Decorators;

use Closure;
use Cognesy\Stream\Contracts\Reducer;

/**
 * Removes consecutive duplicate elements from the input sequence.
 *
 * If a key function is provided, it uses the result of that
 * function to determine uniqueness.
 *
 * Example:
 * [1, 1, 2, 2, 3] with no key function becomes [1, 2, 3]
 * ['a', 'A', 'b', 'B'] with key function strtolower() becomes ['a', 'b']
 */
final class DeduplicateReducer implements Reducer
{
    private mixed $lastValue = null;
    private bool $hasValue = false;

    /**
     * @param Closure(mixed): (string|int)|null $keyFn
     */
    public function __construct(
        private Reducer $reducer,
        private ?Closure $keyFn = null,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->reducer->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        $key = $this->keyFn !== null
            ? ($this->keyFn)($reducible)
            : $reducible;

        if ($this->hasValue && $key === $this->lastValue) {
            return $accumulator;
        }

        $this->lastValue = $key;
        $this->hasValue = true;

        return $this->reducer->step($accumulator, $reducible);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->reducer->complete($accumulator);
    }
}
