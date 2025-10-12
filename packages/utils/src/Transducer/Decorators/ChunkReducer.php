<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Decorators;

use Cognesy\Utils\Transducer\Contracts\Reducer;

/**
 * Chunks the input into arrays of a specified size.
 * The last chunk may be smaller if there are not enough elements.
 *
 * Example:
 * [1, 2, 3, 4, 5] with size 2 becomes [[1, 2], [3, 4], [5]]
 */
final class ChunkReducer implements Reducer
{
    private array $buffer = [];
    private int $count = 0;

    public function __construct(
        private int $size,
        private Reducer $reducer,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->reducer->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        $this->buffer[] = $reducible;
        $this->count++;

        if ($this->count >= $this->size) {
            $accumulator = $this->reducer->step($accumulator, $this->buffer);
            $this->buffer = [];
            $this->count = 0;
        }

        return $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        if ($this->count > 0) {
            $accumulator = $this->reducer->step($accumulator, $this->buffer);
            $this->buffer = [];
            $this->count = 0;
        }
        return $this->reducer->complete($accumulator);
    }
}