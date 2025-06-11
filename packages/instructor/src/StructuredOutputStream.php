<?php
namespace Cognesy\Instructor;

use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseGenerated;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseUpdated;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Exception;
use Generator;
use Psr\EventDispatcher\EventDispatcherInterface;

class StructuredOutputStream
{
    private Generator $stream;
    private EventDispatcherInterface $events;
    private PartialInferenceResponse|InferenceResponse|null $lastResponse = null;
    private Usage $usage;

    /**
     * @param Generator<PartialInferenceResponse> $stream
     * @param EventDispatcherInterface $events
     */
    public function __construct(
        Generator $stream,
        EventDispatcherInterface $events,
    ) {
        $this->usage = new Usage();
        $this->stream = $stream;
        $this->events = $events;
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
    public function lastUpdate() : mixed {
        return $this->lastResponse->value();
    }

    /**
     * Returns last received LLM response object, which contains
     * detailed information from LLM API response
     */
    public function lastResponse() : InferenceResponse|PartialInferenceResponse {
        return $this->lastResponse;
    }

    /**
     * Returns a stream of partial updates.
     */
    public function partials() : Iterable {
        foreach ($this->streamResponses() as $partialResponse) {
            $result = $partialResponse->value();
            yield $result;
        }
    }

    /**
     * Processes response stream and returns only the final update.
     */
    public function finalValue() : mixed {
        return $this->finalResponse()->value();
    }

    /**
     * Processes response stream and returns only the final response.
     */
    public function finalResponse() : InferenceResponse {
        foreach ($this->streamResponses() as $partialResponse) {
            // Just consume the stream, processStream() handles the updates
        }
        return $this->lastResponse;
    }

    /**
     * Returns single update for each completed item of the sequence.
     * This method is useful when you want to process only fully updated
     * sequence items, e.g. for visualization or further processing.
     */
    public function sequence() : Iterable {
        $lastSequence = null;
        $lastSequenceCount = 1;

        foreach ($this->streamResponses() as $partialResponse) {
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
    }

    /**
     * Returns a generator of partial LLM responses, which contain more detailed
     * information about the response, including usage data.
     *
     * @return Generator<PartialInferenceResponse>
     */
    public function responses() : Generator {
        foreach ($this->streamResponses() as $partialResponse) {
            yield $partialResponse;
        }
    }

    /**
     * Returns raw stream for custom processing.
     * Processing with this method does not trigger any events or dispatch any notifications.
     * It also does not update usage data on the stream object.
     */
    public function getIterator() : Iterable {
        return $this->stream;
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    /**
     * Private method that handles stream iteration, usage accumulation, and last response tracking.
     * This centralizes the common iteration logic used by all stream processing methods.
     *
     * @return Generator<PartialInferenceResponse>
     */
    private function streamResponses(): Generator {
        foreach ($this->stream as $partialResponse) {
            $this->lastResponse = $partialResponse;
            $this->usage->accumulate($partialResponse->usage());
            $this->events->dispatch(new StructuredOutputResponseUpdated(['partial' => json_encode($partialResponse->value())]));
            yield $partialResponse;
        }
        $this->events->dispatch(new StructuredOutputResponseGenerated(['value' => json_encode($this->lastResponse->value())]));
    }
}