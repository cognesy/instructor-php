<?php declare(strict_types=1);

namespace Cognesy\Instructor\Executors\Streaming\SequenceGen;

use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Events\Request\SequenceUpdated;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Handles dispatching SequenceUpdate events in the streaming mode
 * for Sequenceable response models.
 */
class SequenceableEmitter
{
    private EventDispatcherInterface $events;

    private SequenceState $state;

    public function __construct(EventDispatcherInterface $events) {
        $this->events = $events;
        $this->state = SequenceState::initial();
    }

    /**
     * Updates the state of the sequence by processing changes to the partial sequence.
     *
     * @param Sequenceable $partialSequence The partial sequence to be processed and updated.
     * @return void
     */
    public function update(Sequenceable $partialSequence) : void {
        $this->state = $this->state->updateSequence($partialSequence);

        // Dispatch events for each complete item update
        $updates = $this->state->updates();
        foreach ($updates as $sequenceState) {
            $this->events->dispatch(new SequenceUpdated($sequenceState));
        }

        // Mark updates as confirmed
        $this->state = $this->state->confirmUpdates();
    }

    /**
     * Finalizes the processing of the current sequence if there is a partial sequence available.
     * It compares the length of the current sequence with the previous sequence length and dispatches
     * sequence events if the current sequence is longer. Updates the previous sequence length accordingly.
     *
     * @return void
     */
    public function finalize() : void {
        $sequence = $this->state->sequence();
        if ($sequence !== null) {
            $this->state = $this->state->completeSequence($sequence);

            // Dispatch final event with complete sequence
            $this->events->dispatch(new SequenceUpdated($sequence));
        }
    }

}
