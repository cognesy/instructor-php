<?php declare(strict_types=1);

namespace Cognesy\Instructor\Partials\Reducers;

use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Partials\Data\PartialContext;
use Cognesy\Instructor\Partials\Data\SequenceEvent;
use Cognesy\Instructor\Partials\Data\SequenceEventType;
use Cognesy\Instructor\Streaming\SequenceGen\SequenceableEmitter;
use Cognesy\Stream\Contracts\Reducer;
use Psr\EventDispatcher\EventDispatcherInterface;

class SequenceUpdatesReducer implements Reducer
{
    private SequenceableEmitter $emitter;
    private int $index;

    public function __construct(
        private Reducer $inner,
        private EventDispatcherInterface $events,
    ) {
        $this->emitter = new SequenceableEmitter($this->events);
        $this->index = 0;
    }

    #[\Override]
    public function init(): mixed {
        $this->emitter = new SequenceableEmitter($this->events);
        $this->index = 0;
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        assert($reducible instanceof PartialContext);

        // Only process if object is Sequenceable
        if (!$reducible->hasObject() || !($reducible->object instanceof Sequenceable)) {
            return $this->inner->step($accumulator, $reducible);
        }

        // Update sequence
        $this->emitter->update($reducible->object);

        // Create sequence event
        $sequenceEvent = new SequenceEvent(
            type: SequenceEventType::ItemUpdated,
            item: $reducible->object,
            index: $this->index++,
        );

        // Forward with sequence event attached
        return $this->inner->step($accumulator, $reducible->withSequenceEvent($sequenceEvent));
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        // Finalize sequence
        $this->emitter->finalize();

        return $this->inner->complete($accumulator);
    }
}