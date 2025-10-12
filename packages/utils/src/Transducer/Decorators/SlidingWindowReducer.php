<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Decorators;

use Cognesy\Utils\Buffer\BoundedBufferInterface;
use Cognesy\Utils\Buffer\SimpleRingBuffer;
use Cognesy\Utils\Transducer\Contracts\Reducer;

final class SlidingWindowReducer implements Reducer
{
    private BoundedBufferInterface $buffer;
    private Reducer $reducer;

    public function __construct(
        int $size,
        Reducer $reducer,
    ) {
        if ($size <= 0) {
            throw new \InvalidArgumentException('Window size must be greater than 0.');
        }
        $this->reducer = $reducer;
        $this->buffer = new SimpleRingBuffer(capacity: $size);
    }

    #[\Override]
    public function init(): mixed {
        return $this->reducer->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        $this->buffer->push($reducible);
        if ($this->buffer->isFull()) {
            $accumulator = $this->reducer->step($accumulator, $this->buffer->toArray());
        }
        return $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->reducer->complete($accumulator);
    }
}
