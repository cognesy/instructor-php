<?php declare(strict_types=1);

namespace Cognesy\Stream\Decorators;

use Cognesy\Stream\Contracts\Reducer;

/**
 * Cycles through the input a specified number of times,
 * or infinitely if no number is provided.
 *
 * Example:
 * [1, 2] with times=2 => [1, 2, 1, 2]
 * [1, 2] with times=null => [1, 2, 1, 2, 1, 2, ...] (infinite)
 */
final class CycleReducer implements Reducer
{
    private array $buffer = [];

    public function __construct(
        private ?int $times,
        private Reducer $reducer,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->reducer->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        $this->buffer[] = $reducible;
        return $this->reducer->step($accumulator, $reducible);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        // If times is null, cycle infinitely is not practical in complete phase
        // If times is specified, replay the buffer (times - 1) more times
        if ($this->times !== null && count($this->buffer) > 0) {
            for ($cycle = 1; $cycle < $this->times; $cycle++) {
                foreach ($this->buffer as $value) {
                    $accumulator = $this->reducer->step($accumulator, $value);
                }
            }
        }
        return $this->reducer->complete($accumulator);
    }
}
