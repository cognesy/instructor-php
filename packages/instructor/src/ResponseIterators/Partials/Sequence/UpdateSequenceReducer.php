<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\Partials\Sequence;

use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\ResponseIterators\Partials\DeltaExtraction\PartialProcessingState;
use Cognesy\Stream\Contracts\Reducer;
use Psr\EventDispatcher\EventDispatcherInterface;

class UpdateSequenceReducer implements Reducer
{
    private SequenceEmitter $emitter;
    private int $index;

    public function __construct(
        private Reducer $inner,
        private EventDispatcherInterface $events,
    ) {
        $this->emitter = new SequenceEmitter($this->events);
        $this->index = 0;
    }

    #[\Override]
    public function init(): mixed {
        $this->emitter = new SequenceEmitter($this->events);
        $this->index = 0;
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        assert($reducible instanceof PartialProcessingState);

        // Only process if object is Sequenceable
        if (!$reducible->hasObject() || !($reducible->object instanceof Sequenceable)) {
            return $this->inner->step($accumulator, $reducible);
        }

        // Update sequence and emit events
        $this->emitter->update($reducible->object);

        // Increment index for bookkeeping; we don't wrap core state.
        $this->index++;

        return $this->inner->step($accumulator, $reducible);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        // Finalize sequence
        $this->emitter->finalize();

        return $this->inner->complete($accumulator);
    }
}
