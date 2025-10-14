<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Group\Decorators;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Utils\Buffer\BoundedBufferInterface;
use Cognesy\Utils\Buffer\SimpleRingBuffer;

final class SlidingWindowReducer implements Reducer
{
    private BoundedBufferInterface $buffer;
    private Reducer $inner;

    public function __construct(
        Reducer $inner,
        int $size,
    ) {
        if ($size <= 0) {
            throw new \InvalidArgumentException('Window size must be greater than 0.');
        }
        $this->inner = $inner;
        $this->buffer = new SimpleRingBuffer(capacity: $size);
    }

    #[\Override]
    public function init(): mixed {
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        $this->buffer->push($reducible);
        if ($this->buffer->isFull()) {
            $accumulator = $this->inner->step($accumulator, $this->buffer->toArray());
        }
        return $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }
}
