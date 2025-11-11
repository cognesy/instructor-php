<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\DecoratedPipeline\Sequence;

use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Events\Request\SequenceUpdated;
use Psr\EventDispatcher\EventDispatcherInterface;

class SequenceEmitter
{
    private EventDispatcherInterface $events;
    private SequenceState $state;

    public function __construct(EventDispatcherInterface $events) {
        $this->events = $events;
        $this->state = SequenceState::initial();
    }

    public function update(Sequenceable $partialSequence) : void {
        $this->state = $this->state->updateSequence($partialSequence);

        $updates = $this->state->updates();
        foreach ($updates as $sequenceState) {
            $this->events->dispatch(new SequenceUpdated($sequenceState));
        }

        $this->state = $this->state->confirmUpdates();
    }

    public function finalize() : void {
        $sequence = $this->state->sequence();
        if ($sequence !== null) {
            $this->state = $this->state->completeSequence($sequence);
            $this->events->dispatch(new SequenceUpdated($sequence));
        }
    }
}

