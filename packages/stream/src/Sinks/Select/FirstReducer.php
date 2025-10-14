<?php declare(strict_types=1);

namespace Cognesy\Stream\Sinks\Select;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Support\Reduced;

/**
 * A reducer that returns the first value it encounters.
 *
 * If no values are encountered, it returns the provided
 * default value (or null if none is provided).
 *
 * Example:
 * [1, 2, 3] => 1
 * [] => null
 * [] with default 'a' => 'a'
 */
final readonly class FirstReducer implements Reducer
{
    private mixed $default;

    public function __construct(
        mixed $default = null
    ) {
        $this->default = $default;
    }

    #[\Override]
    public function init(): mixed {
        return $this->default;
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        return new Reduced($reducible);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $accumulator;
    }
}
