<?php

namespace Cognesy\Instructor;

use Cognesy\Instructor\Extras\Sequences\Sequence;
use Exception;

class Stream
{
    private mixed $lastUpdate = null;

    public function __construct(
        private Iterable $stream,
    ) {}

    /**
     * Returns last received response object, can be used to retrieve
     * current or final response from the stream
     */
    public function getLastUpdate() : mixed {
        return $this->lastUpdate;
    }

    /**
     * Returns raw stream for custom processing
     */
    public function getIterable() : Iterable {
        return $this->stream;
    }

    /**
     * Returns a stream of partial updates
     */
    public function partials() : Iterable {
        foreach ($this->stream as $update) {
            $this->lastUpdate = $update;
            yield $update;
        }
    }

    /**
     * Processes response stream and returns the final update
     */
    public function final() : mixed {
        $result = null;
        foreach ($this->stream as $update) {
            $this->lastUpdate = $update;
            $result = $update;
        }
        return $result;
    }

    /**
     * Processes response stream and calls a callback for each update
     */
    public function each(callable $callback) : void {
        foreach ($this->stream as $update) {
            $this->lastUpdate = $update;
            $callback($update);
        }
    }

    /**
     * Processes response stream and maps each update via provided function
     */
    public function map(callable $callback) : array {
        $result = [];
        foreach ($this->stream as $update) {
            $this->lastUpdate = $update;
            $result[] = $callback($update);
        }
        return $result;
    }

    /**
     * Processes response stream and flat maps each update via provided function
     */
    public function flatMap(callable $callback, mixed $initial) : mixed {
        $result = $initial;
        foreach ($this->stream as $update) {
            $this->lastUpdate = $update;
            $result = $callback($update, $result);
        }
        return $result;
    }

    /**
     * Processes response stream, returning updated sequence for each completed item
     */
    public function sequence() : Iterable {
        $lastSequence = null;
        $lastSequenceCount = 1;
        foreach ($this->stream as $update) {
            $this->lastUpdate = $update;
            if (!($update instanceof Sequence)) {
                throw new Exception('Expected a sequence update, got ' . get_class($update));
            }
            if ($update->count() > $lastSequenceCount) {
                $lastSequenceCount = $update->count();
                yield $lastSequence;
            }
            $lastSequence = $update;
        }
        yield $lastSequence;
    }
}