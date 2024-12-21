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

    public function update(Sequenceable $partialSequence) : void {
        $currentLength = count($partialSequence);
        // We only process up to currentLength - 1 because that's the last item we're sure is complete
        if ($currentLength > $this->previousSequenceLength + 1) {
            $this->dispatchSequenceEvents($partialSequence, $this->previousSequenceLength, $currentLength - 1);
            $this->previousSequenceLength = $currentLength - 1;
        }
        $this->lastPartialSequence = clone $partialSequence;
    }

    public function finalize() : void {
        if ($this->lastPartialSequence !== null) {
            $currentLength = count($this->lastPartialSequence);
            if ($currentLength > $this->previousSequenceLength) {
                $this->dispatchSequenceEvents($this->lastPartialSequence, $this->previousSequenceLength, $currentLength);
                $this->previousSequenceLength = $currentLength;
            }
        }
    }

    public function reset() : void {
        $this->lastPartialSequence = null;
        $this->previousSequenceLength = 0;
    }

    // INTERNAL /////////////////////////////////////////////////

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

//    // sequenceable support state
//    private ?Sequenceable $lastPartialSequence = null;
//    private int $previousSequenceLength = 1;
//    private EventDispatcher $events;
//
//    public function __construct(EventDispatcher $events) {
//        $this->events = $events;
//    }
//
//    public function make(Sequenceable $partialResponse) : void {
//        $this->lastPartialSequence = clone $partialResponse;
//    }
//
//    public function update(Sequenceable $partialSequence) : void {
//        $currentLength = count($partialSequence);
//        if ($currentLength > $this->previousSequenceLength) {
//            if ($this->lastPartialSequence !== null) {
//                $this->events->dispatch(new SequenceUpdated($this->lastPartialSequence));
//                //$this->dispatchSequenceEvents($partialSequence, $this->previousSequenceLength);
//                $this->previousSequenceLength = $currentLength;
//            }
//        }
//        $this->lastPartialSequence = clone $partialSequence;
//    }
//
//    public function finalize() : void {
//        if ($this->lastPartialSequence !== null) {
//            $this->events->dispatch(new SequenceUpdated($this->lastPartialSequence));
//            //$this->dispatchSequenceEvents($this->lastPartialSequence, $this->previousSequenceLength);
//        }
//    }
//
//    public function reset() : void {
//        $this->lastPartialSequence = null;
//        $this->previousSequenceLength = 1;
//    }
