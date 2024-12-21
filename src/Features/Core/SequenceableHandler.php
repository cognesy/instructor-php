<?php

namespace Cognesy\Instructor\Features\Core;

use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Request\SequenceUpdated;

/**
 * Handles dispatching SequenceUpdate events in the streaming mode
 * for Sequenceable response models.
 */
class SequenceableHandler
{
    private ?Sequenceable $lastPartialSequence = null;
    private int $previousSequenceLength = 0;
    private EventDispatcher $events;

    public function __construct(EventDispatcher $events) {
        $this->events = $events;
    }

    /**
     * Updates the state of the sequence by processing changes to the partial sequence.
     *
     * @param Sequenceable $partialSequence The partial sequence to be processed and updated.
     * @return void
     */
    public function update(Sequenceable $partialSequence) : void {
        $currentLength = count($partialSequence);
        // We only process up to currentLength - 1 because that's the last item we're sure is complete
        if ($currentLength > $this->previousSequenceLength + 1) {
            $this->dispatchSequenceEvents($partialSequence, $this->previousSequenceLength, $currentLength - 1);
            $this->previousSequenceLength = $currentLength - 1;
        }
        $this->lastPartialSequence = clone $partialSequence;
    }

    /**
     * Finalizes the processing of the current sequence if there is a partial sequence available.
     * It compares the length of the current sequence with the previous sequence length and dispatches
     * sequence events if the current sequence is longer. Updates the previous sequence length accordingly.
     *
     * @return void
     */
    public function finalize() : void {
        if ($this->lastPartialSequence !== null) {
            $currentLength = count($this->lastPartialSequence);
            if ($currentLength > $this->previousSequenceLength) {
                $this->dispatchSequenceEvents($this->lastPartialSequence, $this->previousSequenceLength, $currentLength);
                $this->previousSequenceLength = $currentLength;
            }
        }
    }

    /**
     * Resets the internal state of the object by clearing any stored partial sequences
     * and resetting the sequence length counter.
     *
     * @return void
     */
    public function reset() : void {
        $this->lastPartialSequence = null;
        $this->previousSequenceLength = 0;
    }

    // INTERNAL /////////////////////////////////////////////////

    /**
     * Dispatches sequence events for the given sequence between specified lengths.
     * This method trims the sequence to the target length, collects incremental states
     * within the range from the last length to the target length, and dispatches events
     * in the order they occurred.
     *
     * @param Sequenceable $sequence The sequence object to process for state changes.
     * @param int $lastLength The previous length of the sequence to track changes from.
     * @param int $targetLength The target length of the sequence to process up to.
     *
     * @return void
     */
    private function dispatchSequenceEvents(Sequenceable $sequence, int $lastLength, int $targetLength) : void {
        $itemsInOrder = [];
        $current = clone $sequence;

        // Remove items after targetLength to ensure we only process complete items
        while (count($current) > $targetLength) {
            $current->pop();
        }

        // Collect states for each new complete item
        while (count($current) > $lastLength) {
            $itemsInOrder[] = clone $current;
            $current->pop();
        }

        // Dispatch events in stream order
        foreach (array_reverse($itemsInOrder) as $state) {
            $this->events->dispatch(new SequenceUpdated($state));
        }
    }
}
