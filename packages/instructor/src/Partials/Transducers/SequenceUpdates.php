<?php declare(strict_types=1);

namespace Cognesy\Instructor\Partials\Transducers;

use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Partials\Reducers\SequenceUpdatesReducer;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Track sequence updates and emit typed events.
 *
 * Input:  PartialContext (with object)
 * Output: PartialContext (with sequenceEvent if applicable)
 * State:  SequenceableEmitter (tracks sequence state)
 *
 * @template TSequence of Sequenceable
 */
final readonly class SequenceUpdates implements Transducer
{
    public function __construct(
        private EventDispatcherInterface $events,
    ) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new SequenceUpdatesReducer(
            inner: $reducer,
            events: $this->events
        );
    }
}
