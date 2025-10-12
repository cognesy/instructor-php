<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Sinks;

use Closure;
use Cognesy\Utils\Transducer\Contracts\Reducer;

/**
 * Reducer that computes the maximum value from a series of inputs.
 * An optional key function can be provided to extract a comparable value from each input.
 * If no key function is provided, the inputs themselves are compared directly.
 *
 * Example:
 * [1, 3, 2] with no key function will yield 3.
 * ['apple', 'banana', 'pear']
 * with keyFn($str) = strlen($str) will yield
 * 'banana'
 */
final class MinReducer implements Reducer
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

        return $value < $accumulator ? $value : $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $accumulator;
    }
}
