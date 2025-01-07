<?php

namespace Cognesy\Instructor\Features\Core;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Instructor\InstructorDone;
use Cognesy\Instructor\Events\Instructor\ResponseGenerated;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Features\LLM\Data\PartialLLMResponse;
use Cognesy\Instructor\Features\LLM\Data\Usage;
use Exception;
use Generator;

class StructuredOutputStream
{
    private PartialLLMResponse|LLMResponse|null $lastResponse = null;
    private Usage $usage;

    /**
     * @param Generator<PartialLLMResponse> $stream
     * @param EventDispatcher $events
     */
    public function __construct(
        private Generator $stream,
        private EventDispatcher $events,
    ) {
        $this->usage = new Usage();
    }

    /**
     * Returns current token usage for the stream
     */
    public function usage() : Usage {
        return $this->usage;
    }

    /**
     * Returns last received response object, can be used to retrieve
     * current or final response from the stream
     */
    public function getLastUpdate() : mixed {
        return $this->lastResponse->value();
    }

    /**
     * Returns last received LLM response object, which contains
     * detailed information from LLM API response
     */
    public function getLastResponse() : LLMResponse|PartialLLMResponse {
        return $this->lastResponse;
    }

    /**
     * Returns a stream of partial updates.
     */
    public function partials() : Iterable {
        foreach ($this->stream as $partialResponse) {
            $this->lastResponse = $partialResponse;
            $result = $partialResponse->value();
            $this->usage->accumulate($partialResponse->usage());
            yield $result;
        }
        $this->events->dispatch(new ResponseGenerated($result));
        $this->events->dispatch(new InstructorDone(['result' => $result]));
    }

    /**
     * Processes response stream and returns only the final update.
     */
    public function final() : mixed {
        foreach ($this->stream as $partialResponse) {
            $this->lastResponse = $partialResponse;
            $this->usage->accumulate($partialResponse->usage());
        }
        $result = $this->lastResponse->value();
        $this->events->dispatch(new ResponseGenerated($result));
        $this->events->dispatch(new InstructorDone(['result' => $result]));
        return $result;
    }

    /**
     * Returns single update for each completed item of the sequence.
     * This method is useful when you want to process only fully updated
     * sequence items, e.g. for visualization or further processing.
     */
    public function sequence() : Iterable {
        $lastSequence = null;
        $lastSequenceCount = 1;
        foreach ($this->stream as $partialResponse) {
            $this->lastResponse = $partialResponse;
            $this->usage->accumulate($partialResponse->usage());
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

    /**
     * Returns a generator of partial LLM responses, which contain more detailed
     * information about the response, including usage data.
     * @return Generator<PartialLLMResponse>
     */
    public function responses() : Generator {
        foreach ($this->stream as $partialResponse) {
            $this->usage->accumulate($partialResponse->usage());
            $this->lastResponse = $partialResponse;
            yield $partialResponse;
        }
        $this->events->dispatch(new ResponseGenerated($this->lastResponse->value()));
        $this->events->dispatch(new InstructorDone(['result' => $this->lastResponse->value()]));
    }

    /**
     * Returns raw stream for custom processing.
     * Processing with this method does not trigger any events or dispatch any notifications.
     * It also does not update usage data on the stream object.
     */
    public function getIterator() : Iterable {
        return $this->stream;
    }

    // INTERNAL //////////////////////////////////////////////////////////////

    // NOT YET AVAILABLE ////////////////////////////////////////////////////

    /**
     * Processes response stream and calls a callback for each update
     */
    private function each(callable $callback) : void {
        foreach ($this->stream as $partialResponse) {
            $this->lastResponse = $partialResponse;
            $callback($partialResponse->value());
        }
        $this->events->dispatch(new ResponseGenerated($this->lastResponse));
        $this->events->dispatch(new InstructorDone(['result' => $this->lastResponse]));
    }

    /**
     * Processes response stream and maps each update via provided function
     */
    private function map(callable $callback) : array {
        $result = [];
        foreach ($this->stream as $partialResponse) {
            $this->lastResponse = $partialResponse;
            $result[] = $callback($partialResponse->value());
        }
        $this->events->dispatch(new ResponseGenerated($this->lastResponse));
        $this->events->dispatch(new InstructorDone(['result' => $result]));
        return $result;
    }

    /**
     * Processes response stream and flat maps each update via provided function
     */
    private function flatMap(callable $callback, mixed $initial) : mixed {
        $result = $initial;
        foreach ($this->stream as $partialResponse) {
            $this->lastResponse = $partialResponse;
            $result = $callback($partialResponse->value(), $result);
        }
        $this->events->dispatch(new ResponseGenerated($result));
        $this->events->dispatch(new InstructorDone($result));
        return $result;
    }
}
