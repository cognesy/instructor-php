<?php

namespace Cognesy\Instructor\Features\Core;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Instructor\InstructorDone;
use Cognesy\Instructor\Events\Instructor\ResponseGenerated;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Features\LLM\Data\PartialLLMResponse;
use Exception;

class Stream
{
    private PartialLLMResponse|LLMResponse|null $lastUpdate = null;

    public function __construct(
        private Iterable $stream,
        private EventDispatcher $events,
    ) {}

    /**
     * Returns last received response object, can be used to retrieve
     * current or final response from the stream
     */
    public function getLastUpdate() : mixed {
        return $this->lastUpdate->data();
    }

    /**
     * Returns raw stream for custom processing
     */
    public function getIterator() : Iterable {
        return $this->stream;
    }

    /**
     * Returns a stream of partial updates
     */
    public function partials() : Iterable {
        foreach ($this->stream as $partialResponse) {
            $this->lastUpdate = $partialResponse;
            $result = $partialResponse->value();
            yield $result;
        }
        $this->events->dispatch(new ResponseGenerated($result));
        $this->events->dispatch(new InstructorDone(['result' => $result]));
    }

    /**
     * Processes response stream and returns the final update
     */
    public function final() : mixed {
        foreach ($this->stream as $partialResponse) {
            $this->lastUpdate = $partialResponse;
        }
        $result = $this->lastUpdate->data();
        $this->events->dispatch(new ResponseGenerated($result));
        $this->events->dispatch(new InstructorDone(['result' => $result]));
        return $result;
    }

    /**
     * Processes response stream, returning updated sequence for each completed item
     */
    public function sequence() : Iterable {
        $lastSequence = null;
        $lastSequenceCount = 1;
        foreach ($this->stream as $partialResponse) {
            $this->lastUpdate = $partialResponse;
            $update = $partialResponse->value();
            if (!($update instanceof Sequence)) {
                throw new Exception('Expected a sequence update, got ' . get_class($update));
            }
            // only yield if there's new element in sequence
            if ($update->count() > $lastSequenceCount) {
                $lastSequenceCount = $update->count();
                yield $lastSequence;
            }
            $lastSequence = $update;
        }
        // yield last, fully updated sequence instance
        yield $lastSequence;
        $this->events->dispatch(new ResponseGenerated($lastSequence));
        $this->events->dispatch(new InstructorDone(['result' => $lastSequence]));
    }

    // INTERNAL //////////////////////////////////////////////////////////////

    // NOT YET AVAILABLE ////////////////////////////////////////////////////

    /**
     * Processes response stream and calls a callback for each update
     */
    private function each(callable $callback) : void {
        foreach ($this->stream as $partialResponse) {
            $this->lastUpdate = $partialResponse;
            $callback($partialResponse->value());
        }
        $this->events->dispatch(new ResponseGenerated($this->lastUpdate));
        $this->events->dispatch(new InstructorDone(['result' => $this->lastUpdate]));
    }

    /**
     * Processes response stream and maps each update via provided function
     */
    private function map(callable $callback) : array {
        $result = [];
        foreach ($this->stream as $partialResponse) {
            $this->lastUpdate = $partialResponse;
            $result[] = $callback($partialResponse->value());
        }
        $this->events->dispatch(new ResponseGenerated($this->lastUpdate));
        $this->events->dispatch(new InstructorDone(['result' => $result]));
        return $result;
    }

    /**
     * Processes response stream and flat maps each update via provided function
     */
    private function flatMap(callable $callback, mixed $initial) : mixed {
        $result = $initial;
        foreach ($this->stream as $partialResponse) {
            $this->lastUpdate = $partialResponse;
            $result = $callback($partialResponse->value(), $result);
        }
        $this->events->dispatch(new ResponseGenerated($result));
        $this->events->dispatch(new InstructorDone($result));
        return $result;
    }
}
