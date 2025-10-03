<?php declare(strict_types=1);
namespace Cognesy\Instructor;

use Cognesy\Instructor\Core\RequestHandler;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseGenerated;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseUpdated;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputStarted;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Exception;
use Generator;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @template TResponse
 */
class StructuredOutputStream
{
    private RequestHandler $requestHandler;
    private EventDispatcherInterface $events;

    private Generator $stream;
    /** @var array<StructuredOutputExecution> */
    private array $cachedResponseStream = [];
    private readonly bool $cacheProcessedResponse;

    private StructuredOutputExecution $execution;
    private PartialInferenceResponse|InferenceResponse|null $lastResponse = null;

    /**
     * @param Generator<StructuredOutputExecution> $stream
     * @param EventDispatcherInterface $events
     */
    public function __construct(
        StructuredOutputExecution $execution,
        RequestHandler $requestHandler,
        EventDispatcherInterface $events,
    ) {
        $this->cacheProcessedResponse = true;

        $this->execution = $execution;
        $this->requestHandler = $requestHandler;
        $this->events = $events;
        $this->stream = $this->getStream($execution);
    }

    /**
     * Returns last received parsed value
     *
     * @return TResponse
     */
    public function lastUpdate() : mixed {
        return $this->lastResponse?->value();
    }

    /**
     * Returns last received LLM response object, which contains
     * detailed information from LLM API response
     */
    public function lastResponse() : InferenceResponse|PartialInferenceResponse {
        if ($this->lastResponse === null) {
            throw new \RuntimeException('No response available yet');
        }
        return $this->lastResponse;
    }

    /**
     * Returns a stream of partial parsed values.
     *
     * @return \Generator<TResponse>
     */
    public function partials() : \Generator {
        foreach ($this->streamResponses() as $partialResponse) {
            $result = $partialResponse->value();
            yield $result;
        }
    }

    /**
     * Processes response stream and returns the final parsed value.
     *
     * @return TResponse
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
        if (!$this->lastResponse instanceof InferenceResponse) {
            $type = $this->lastResponse === null ? 'null' : get_class($this->lastResponse);
            throw new Exception('Expected final InferenceResponse, got ' . $type);
        }
        return $this->lastResponse;
    }

    /**
     * Returns single update for each completed item of the sequence.
     * This method is useful when you want to process only fully updated
     * sequence items, e.g. for visualization or further processing.
     *
     * @return \Generator<Sequence>
     */
    public function sequence() : \Generator {
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
                // yield snapshot of the previous state if available
                if (!is_null($lastSequence)) {
                    /** @phpstan-ignore-next-line */
                    yield clone $lastSequence;
                }
            }
            // keep a snapshot to avoid later mutations affecting yielded values
            $lastSequence = clone $update;
        }
        // yield last, fully updated sequence instance if available
        if (!is_null($lastSequence)) {
            /** @phpstan-ignore-next-line */
            yield clone $lastSequence;
        }
    }

    /**
     * Returns a generator of streamed LLM responses (partials) and final response,
     * which contain more detailed information, including usage data.
     *
     * @return Generator<PartialInferenceResponse|InferenceResponse>
     */
    public function responses() : Generator {
        foreach ($this->streamResponses() as $partialResponse) {
            yield $partialResponse;
        }
    }

    /**
     * Convenience: aggregated usage for the last response seen on the stream.
     */
    public function usage() : Usage {
        return $this->execution->usage();
    }

    /**
     * Returns raw stream for custom processing.
     * Processing with this method does not trigger any events or dispatch any notifications.
     * It also does not update usage data on the stream object.
     *
     * @return Generator<StructuredOutputExecution>
     */
    public function getIterator() : Generator {
        return $this->stream;
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    /**
     * Private method that handles stream iteration, usage accumulation, and last response tracking.
     * This centralizes the common iteration logic used by all stream processing methods.
     *
     * @return Generator<PartialInferenceResponse|InferenceResponse> Yields inference responses (partials and final).
     */
    private function streamResponses(): Generator {
        /** @var StructuredOutputExecution $execution */
        foreach ($this->stream as $execution) {
            $response = $execution->inferenceResponse();
            if ($response === null) {
                continue;
            }
            $this->lastResponse = $response;
            $this->events->dispatch(new StructuredOutputResponseUpdated(['partial' => json_encode($response->value())]));
            yield $response;
        }
        if ($this->lastResponse !== null) {
            $this->events->dispatch(new StructuredOutputResponseGenerated(['value' => json_encode($this->lastResponse->value())]));
        }
    }

    /**
     * Gets a stream of partial responses for the given execution.
     *
     * @param StructuredOutputExecution $execution The execution for which to get the response stream.
     * @return Generator<StructuredOutputExecution> A generator yielding structured output execution updates.
     */
    private function getStream(StructuredOutputExecution $execution) : Generator {
        $this->events->dispatch(new StructuredOutputStarted(['request' => $execution->request()->toArray()]));

        // RESPONSE CACHING = IS DISABLED
        if (!$this->cacheProcessedResponse) {
            $executionUpdates = $this->requestHandler->streamUpdatesFor($execution);
            $last = null;
            foreach ($executionUpdates as $chunk) {
                $last = $chunk;
                yield $chunk;
            }
            if ($last !== null) {
                $this->execution = $last;
            }
            return;
        }

        // RESPONSE CACHING = IS ENABLED
        if (empty($this->cachedResponseStream)) {
            $this->cachedResponseStream = [];
            $executionUpdates = $this->requestHandler->streamUpdatesFor($execution);
            $last = null;
            foreach ($executionUpdates as $chunk) {
                $this->cachedResponseStream[] = $chunk;
                $last = $chunk;
                yield $chunk;
            }
            if ($last !== null) {
                $this->execution = $last;
            }
            return;
        }

        yield from $this->cachedResponseStream;
    }
}
