<?php declare(strict_types=1);

namespace Cognesy\Stream\Sinks;

use Closure;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Support\Reduced;

/**
 * A reducer that checks if any element matches a predicate.
 * Returns true if at least one element matches, false otherwise.
 * Short-circuits (stops early) on first match.
 *
 * Example:
 * [1, 2, 3, 4, 5] with fn($x) => $x > 3 => true
 * [1, 2, 3] with fn($x) => $x > 10 => false
 * [] with any predicate => false
 */
final readonly class MatchesAnyReducer implements Reducer
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
        return false;
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        if (($this->predicate)($reducible)) {
            return new Reduced(true);
        }
        return false;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $accumulator;
    }
}
