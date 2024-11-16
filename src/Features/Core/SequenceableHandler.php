<?php

namespace Cognesy\Instructor\Features\Core;

use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Request\SequenceUpdated;

class SequenceableHandler
{
    // sequenceable support state
    private ?Sequenceable $lastPartialSequence = null;
    private int $previousSequenceLength = 1;
    private EventDispatcher $events;

    public function __construct(EventDispatcher $events) {
        $this->events = $events;
    }

    public function make(Sequenceable $partialResponse) : void {
        $this->lastPartialSequence = clone $partialResponse;
    }

    public function update(Sequenceable $partialSequence) : void {
        $currentLength = count($partialSequence);
        if ($currentLength > $this->previousSequenceLength) {
            if ($this->lastPartialSequence !== null) {
                $this->events->dispatch(new SequenceUpdated($this->lastPartialSequence));
                //$this->dispatchSequenceEvents($partialSequence, $this->previousSequenceLength);
                $this->previousSequenceLength = $currentLength;
            }
        }
        $this->lastPartialSequence = clone $partialSequence;
    }

    public function finalize() : void {
        if ($this->lastPartialSequence !== null) {
            $this->events->dispatch(new SequenceUpdated($this->lastPartialSequence));
            //$this->dispatchSequenceEvents($this->lastPartialSequence, $this->previousSequenceLength);
        }
    }

    public function reset() : void {
        $this->lastPartialSequence = null;
        $this->previousSequenceLength = 1;
    }

    // INTERNAL /////////////////////////////////////////////////

    private function dispatchSequenceEvents(Sequenceable $sequence, int $lastLength) : void {
        if ($sequence === null) {
            return;
        }
        $cloned = clone $sequence;
        $queue = [];
        while (count($cloned) > $lastLength) {
            $queue[] = $cloned;
            $cloned->pop();
        }
        $queue = array_reverse($queue);

        foreach ($queue as $item) {
            $this->events->dispatch(new SequenceUpdated($item));
        }
    }
}