<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\RequestHandler\SequenceUpdated;

class SequenceableHandler
{
    // sequenceable support state
    private ?Sequenceable $lastPartialSequence;
    private int $previousSequenceLength = 1;
    private EventDispatcher $events;

    public function __construct(EventDispatcher $events) {
        $this->events = $events;
    }

    public function make(Sequenceable $partialResponse) : void {
        $this->lastPartialSequence = clone $partialResponse;
    }

    public function update(Sequenceable $partialObject) : void {
        $currentLength = count($partialObject);
        if ($currentLength > $this->previousSequenceLength) {
            $this->previousSequenceLength = $currentLength;
            $this->events->dispatch(new SequenceUpdated($this->lastPartialSequence));
        }
        $this->lastPartialSequence = clone $partialObject;
    }

    public function finalize() : void {
        if (isset($this->lastPartialSequence)) {
            $this->events->dispatch(new SequenceUpdated($this->lastPartialSequence));
        }
    }

    public function reset() : void {
        $this->lastPartialSequence = null;
        $this->previousSequenceLength = 1;
    }
}