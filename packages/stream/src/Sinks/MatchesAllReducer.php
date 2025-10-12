<?php declare(strict_types=1);

namespace Cognesy\Stream\Sinks;

use Closure;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Support\Reduced;

/**
 * A reducer that checks if all elements match a predicate.
 * Returns true if all elements match, false otherwise.
 * Short-circuits (stops early) on first non-match.
 *
 * Example:
 * [2, 4, 6] with fn($x) => $x % 2 === 0 => true
 * [2, 3, 4] with fn($x) => $x % 2 === 0 => false
 * [] with any predicate => true (vacuous truth)
 */
final readonly class MatchesAllReducer implements Reducer
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
        if (!($this->predicate)($reducible)) {
            return new Reduced(false);
        }
        return true;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $accumulator;
    }
}
