<?php declare(strict_types=1);

namespace Cognesy\Instructor;

use Cognesy\Instructor\Contracts\CanHandleStructuredOutputAttempts;
use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseGenerated;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseUpdated;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputStarted;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Domain\SequenceTracker;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;
use Cognesy\Utils\Collection\ArrayList;
use Exception;
use Generator;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;

/**
 * @template TResponse
 */
class StructuredOutputStream
{
    private CanHandleStructuredOutputAttempts $attemptHandler;
    private EventDispatcherInterface $events;

    private Generator $stream;
    /** @var ArrayList<StructuredOutputExecution> */
    private ArrayList $cachedResponseStream;

    private StructuredOutputExecution $execution;
    private InferenceResponse|null $lastResponse = null;
    private ResponseCachePolicy $cachePolicy;

    /**
     * @param Generator<StructuredOutputExecution> $stream
     * @param EventDispatcherInterface $events
     */
    public function __construct(
        StructuredOutputExecution $execution,
        CanHandleStructuredOutputAttempts $attemptHandler,
        EventDispatcherInterface $events,
    ) {
        $this->execution = $execution;
        $this->attemptHandler = $attemptHandler;
        $this->events = $events;
        $this->cachePolicy = $execution->config()->responseCachePolicy();
        $this->cachedResponseStream = ArrayList::empty();
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
    public function lastResponse() : InferenceResponse {
        if ($this->lastResponse === null) {
            throw new \RuntimeException('No response available yet');
        }
        return $this->lastResponse;
    }

    /**
     * Returns a stream of partial parsed values.
     *
     * @return Generator<TResponse>
     */
    public function partials() : Generator {
        foreach ($this->streamResponses() as $partialResponse) {
            $result = $partialResponse->value();
            yield $result;
        }
    }

    /**
     * Returns single update for each completed item of the sequence.
     * This method is useful when you want to process only fully updated
     * sequence items, e.g. for visualization or further processing.
     *
     * @return Generator<Sequenceable>
     */
    public function sequence(): Generator {
        $tracker = SequenceTracker::empty();

        foreach ($this->streamResponses() as $partialResponse) {
            $value = $partialResponse->value();
            if (!($value instanceof Sequenceable)) {
                throw new Exception('Expected Sequenceable, got ' . get_class($value));
            }

            $tracker = $tracker->update($value);
            $pending = $tracker->pending();
            foreach ($pending as $completedSequence) {
                yield $completedSequence;
            }
            $tracker = $tracker->advance();
        }

        // Finalize - yield remaining completed items
        $finalUpdates = $tracker->finalize();
        foreach ($finalUpdates as $nextFinalItem) {
            yield $nextFinalItem;
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
            $tmp = $partialResponse;
        }
        if (is_null($this->lastResponse)) {
            throw new RuntimeException('Expected final InferenceResponse, got null');
        }
        return $this->lastResponse;
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

    /**
     * Convenience: aggregated usage for the last response seen on the stream.
     */
    public function usage() : Usage {
        return $this->execution->usage();
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    /**
     * Private method that handles stream iteration, usage accumulation, and last response tracking.
     * This centralizes the common iteration logic used by all stream processing methods.
     *
     * @return Generator<InferenceResponse> Yields inference responses (partials and final).
     */
    private function streamResponses(): Generator {
        /** @var StructuredOutputExecution $execution */
        foreach ($this->getStream($this->execution) as $execution) {
            $response = $execution->inferenceResponse();
            if ($response === null) {
                continue;
            }
            $this->lastResponse = $execution->inferenceResponse();
            $this->events->dispatch(new StructuredOutputResponseUpdated(['response' => $response]));
            yield $response;
        }
        if ($this->lastResponse !== null) {
            $this->events->dispatch(new StructuredOutputResponseGenerated(['response' => $this->lastResponse]));
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

        return match($this->shouldCache()) {
            false => $this->streamWithoutCaching($execution),
            true => $this->streamWithCaching($execution),
        };
    }

    /**
     * Streams execution updates without caching.
     *
     * @return Generator<StructuredOutputExecution>
     */
    private function streamWithoutCaching(StructuredOutputExecution $execution): Generator {
        while ($this->attemptHandler->hasNext($execution)) {
            $execution = $this->attemptHandler->nextUpdate($execution);
            yield $execution;
        }
        $this->execution = $execution;
    }

    /**
     * Streams execution updates with caching - builds cache on first call, replays from cache on subsequent calls.
     *
     * @return Generator<StructuredOutputExecution>
     */
    private function streamWithCaching(StructuredOutputExecution $execution): Generator {
        if ($this->cachedResponseStream->isEmpty()) {
            yield from $this->buildAndCacheStream($execution);
            return;
        }
        foreach ($this->cachedResponseStream as $item) {
            yield $item;
        }
    }

    /**
     * Builds the response stream and populates the cache.
     *
     * @return Generator<StructuredOutputExecution>
     */
    private function buildAndCacheStream(StructuredOutputExecution $execution): Generator {
        $this->cachedResponseStream = ArrayList::empty();
        while ($this->attemptHandler->hasNext($execution)) {
            $execution = $this->attemptHandler->nextUpdate($execution);
            $this->cachedResponseStream = $this->cachedResponseStream->withAppended($execution);
            yield $execution;
        }
        $this->execution = $execution;
    }

    private function shouldCache(): bool {
        return $this->cachePolicy->shouldCache();
    }
}
