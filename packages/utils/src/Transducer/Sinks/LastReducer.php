<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Sinks;

use Cognesy\Utils\Transducer\Contracts\Reducer;

/**
 * A reducer that returns the last value seen, or
 * a default value if no values were seen.
 *
 * Example:
 * [1, 2, 3] => 3
 * [] => null
 * [] with default 'a' => 'a'
 */
final readonly class LastReducer implements Reducer
{
    private mixed $default;

    public function __construct(mixed $default = null) {
        $this->default = $default;
    }

    #[\Override]
    public function init(): mixed {
        return $this->default;
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        return $reducible;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $accumulator;
    }
}
