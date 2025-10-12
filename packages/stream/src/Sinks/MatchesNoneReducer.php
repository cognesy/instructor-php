<?php declare(strict_types=1);

namespace Cognesy\Stream\Sinks;

use Closure;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Support\Reduced;

/**
 * A reducer that checks if no elements match a predicate.
 * Returns true if no elements match, false otherwise.
 * Short-circuits (stops early) on first match.
 *
 * Example:
 * [1, 3, 5] with fn($x) => $x % 2 === 0 => true
 * [1, 2, 3] with fn($x) => $x % 2 === 0 => false
 * [] with any predicate => true (vacuous truth)
 */
final readonly class MatchesNoneReducer implements Reducer
{
    /** @var Closure(mixed): bool */
    private Closure $predicate;

    /**
     * @param Closure(mixed): bool $predicate
     */
    public function __construct(Closure $predicate) {
        $this->predicate = $predicate;
    }

    #[\Override]
    public function init(): mixed {
        return true;
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        if (($this->predicate)($reducible)) {
            return new Reduced(false);
        }
        return true;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $accumulator;
    }
}
