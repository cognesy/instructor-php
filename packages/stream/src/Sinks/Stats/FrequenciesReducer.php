<?php declare(strict_types=1);

namespace Cognesy\Stream\Sinks\Stats;

use Closure;
use Cognesy\Stream\Contracts\Reducer;

/**
 * A reducer that counts the frequencies of elements in a collection.
 *
 * If a key function is provided, it will be used to determine the key for each element.
 * Otherwise, the element itself will be used as the key.
 *
 * Example:
 * [1, 2, 2, 3] becomes [1 => 1, 2 => 2, 3 => 1]
 * ['apple', 'banana', 'apple'] becomes ['apple' => 2, 'banana' => 1]
 */
final readonly class FrequenciesReducer implements Reducer
{
    /** @var Closure(mixed): (string|int)|null */
    private ?Closure $keyFn;

    /**
     * @param Closure(mixed): (string|int)|null $keyFn
     */
    public function __construct(?Closure $keyFn = null) {
        $this->keyFn = $keyFn;
    }

    #[\Override]
    public function init(): mixed {
        return [];
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        $key = $this->keyFn !== null
            ? ($this->keyFn)($reducible)
            : $reducible;

        if (!isset($accumulator[$key])) {
            $accumulator[$key] = 0;
        }

        $accumulator[$key]++;

        return $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $accumulator;
    }
}
