<?php declare(strict_types=1);

namespace Cognesy\Stream\Sinks\Stats;

use Closure;
use Cognesy\Stream\Contracts\Reducer;

/**
 * Reducer that computes the maximum value from a series of inputs.
 * Optionally, a key function can be provided to extract a comparable value from each input.
 *
 * Example:
 * [1, 3, 2] with no key function will yield 3.
 * [['a' => 1], ['a' => 3], ['a' => 2]] with key function fn($x) => $x['a'] will yield 3.
 */
final class MaxReducer implements Reducer
{
    /** @var Closure(mixed): mixed|null */
    private ?Closure $keyFn;
    private bool $hasValue = false;

    /**
     * @param Closure(mixed): mixed|null $keyFn
     */
    public function __construct(?Closure $keyFn = null) {
        $this->keyFn = $keyFn;
    }

    #[\Override]
    public function init(): mixed {
        $this->hasValue = false;
        return null;
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        $value = $this->keyFn !== null
            ? ($this->keyFn)($reducible)
            : $reducible;

        if (!$this->hasValue) {
            $this->hasValue = true;
            return $value;
        }

        return $value > $accumulator ? $value : $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $accumulator;
    }
}
