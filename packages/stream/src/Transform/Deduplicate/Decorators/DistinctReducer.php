<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Deduplicate\Decorators;

use Closure;
use Cognesy\Stream\Contracts\Reducer;

/**
 * A reducer that only allows distinct values to be processed
 * by the underlying reducer.
 *
 * Example:
 * [1, 2, 2, 3] with a DistinctReducer will result in [1, 2, 3]
 * if no key function is provided.
 *
 * If a key function is provided, it will be used to determine
 * the uniqueness of the values.
 */
class DistinctReducer implements Reducer {
    private array $seen = [];

    /**
     * @param Closure(mixed): (string|int)|null $keyFn
     */
    public function __construct(
        private Reducer $inner,
        private ?Closure $keyFn = null,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        $key = $this->keyFn ? ($this->keyFn)($reducible) : $reducible;
        if (in_array($key, $this->seen, true)) {
            return $accumulator;
        }
        $this->seen[] = $key;
        return $this->inner->step($accumulator, $reducible);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }
}