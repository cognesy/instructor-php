<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Sinks;

use Closure;
use Cognesy\Utils\Transducer\Contracts\Reducer;

/**
 * Groups elements by a key function.
 *
 * Example:
 * ['apple', 'apricot', 'banana', 'blueberry'] grouped by first letter
 * becomes:
 * [
 *   'a' => ['apple', 'apricot'],
 *   'b' => ['banana', 'blueberry']
 * ]
 */
final readonly class GroupByReducer implements Reducer
{
    /** @var Closure(mixed): (string|int) */
    private Closure $keyFn;

    /**
     * @param Closure(mixed): (string|int) $keyFn
     */
    public function __construct(Closure $keyFn) {
        $this->keyFn = $keyFn;
    }

    #[\Override]
    public function init(): mixed {
        return [];
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        $key = ($this->keyFn)($reducible);

        if (!isset($accumulator[$key])) {
            $accumulator[$key] = [];
        }

        $accumulator[$key][] = $reducible;

        return $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $accumulator;
    }
}
