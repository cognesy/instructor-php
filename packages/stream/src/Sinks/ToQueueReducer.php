<?php declare(strict_types=1);

namespace Cognesy\Stream\Sinks;

use Cognesy\Stream\Contracts\Reducer;
use SplQueue;

/**
 * Reducer that enqueues each reduced value into a queue
 * to be yielded by a driving iterator.
 */
final class ToQueueReducer implements Reducer
{
    public function __construct(private readonly SplQueue $queue) {}

    #[\Override]
    public function init(): mixed {
        return null;
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        $this->queue->enqueue($reducible);
        return $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $accumulator;
    }
}

