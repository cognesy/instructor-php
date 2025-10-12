<?php declare(strict_types=1);

namespace Cognesy\Utils\Stream\Support;

use Cognesy\Utils\Transducer\Contracts\Reducer;
use SplQueue;

/**
 * Reducer that enqueues each reduced value into a queue
 * to be yielded by a driving iterator.
 */
final class QueueYieldReducer implements Reducer
{
    public function __construct(private readonly SplQueue $queue) {}

    public function init(): mixed {
        return null;
    }

    public function step(mixed $accumulator, mixed $reducible): mixed {
        $this->queue->enqueue($reducible);
        return $accumulator;
    }

    public function complete(mixed $accumulator): mixed {
        return $accumulator;
    }
}

