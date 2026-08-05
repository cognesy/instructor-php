<?php declare(strict_types=1);

namespace Cognesy\Instructor;

use Cognesy\Instructor\Contracts\CanDriveExecution;
use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputResponse;
use Cognesy\Instructor\Streaming\ResponseCache;
use Cognesy\Instructor\Streaming\Sequence\SequenceTracker;
use Cognesy\Instructor\Telemetry\StructuredOutputEventProjector;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Generator;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;

/**
 * Consumption API over a streaming structured-output execution: partial
 * values, sequence items, response snapshots, and the final result.
 *
 * Event payload construction lives in StructuredOutputEventProjector; opt-in replay
 * retention lives in ResponseCache.
 *
 * @template TResponse
 */
class StructuredOutputStream
{
    private CanDriveExecution $emitter;
    private StructuredOutputEventProjector $projector;
    private ResponseCache $cache;

    private StructuredOutputExecution $execution;
    private ?StructuredOutputResponse $lastResponse = null;
    private ?StructuredOutputResponse $finalizedResponse = null;
    private mixed $lastValue = null;
    private bool $streamCompleted = false;

    /**
     * @param StructuredOutputExecution $execution
     * @param CanDriveExecution $emitter
     * @param EventDispatcherInterface $events
     */
    public function __construct(
        StructuredOutputExecution $execution,
        CanDriveExecution $emitter,
        EventDispatcherInterface $events,
    ) {
        $this->execution = $execution;
        $this->emitter = $emitter;
        $this->projector = new StructuredOutputEventProjector($events);
        $this->cache = new ResponseCache($execution->config()->responseCachePolicy());
        $this->projector->started($execution);
    }

    /**
     * Returns last received parsed value
     *
     * @return TResponse
     */
    public function lastUpdate() : mixed {
        return $this->lastValue;
    }

    /**
     * Returns the last Instructor response snapshot emitted by the stream.
     */
    public function lastResponse() : StructuredOutputResponse {
        $response = $this->currentResponse();
        if ($response === null) {
            throw new \RuntimeException('No response available yet');
        }
        return $response;
    }

    /**
     * Returns a stream of partial parsed values.
     *
     * @return Generator<TResponse>
     */
    public function partials() : Generator {
        foreach ($this->streamResponses() as $partialResponse) {
            $value = $partialResponse->value();
            if ($value === null) {
                continue;
            }

            yield $value;
        }
    }

    /**
     * Yields individual completed items from a streaming sequence.
     *
     * Each yielded value is a single deserialized item (not a Sequence snapshot).
     * The full Sequence is available after streaming via finalValue().
     *
     * @return Generator<mixed> Individual completed items.
     */
    public function sequence(): Generator {
        $tracker = SequenceTracker::empty();
        $lastSequence = null;

        foreach ($this->streamResponses() as $partialResponse) {
            $value = $partialResponse->value();
            if ($value === null) {
                continue;
            }

            if (!($value instanceof Sequenceable)) {
                if ($partialResponse->isPartial()) {
                    continue;
                }

                $type = get_debug_type($value);
                throw new RuntimeException("Expected Sequenceable value in sequence() stream, got {$type}.");
            }

            $lastSequence = $value;
            $result = $tracker->consume($value);
            foreach ($result->updates as $completedItem) {
                yield $completedItem;
            }
            $tracker = $result->tracker;
        }

        // Finalize - yield remaining items (including the last held-back one)
        if ($lastSequence !== null) {
            foreach ($tracker->finalize($lastSequence) as $remainingItem) {
                yield $remainingItem;
            }
        }
    }

    /**
     * Returns streamed Instructor response snapshots, including partials and the final response.
     *
     * @return Generator<StructuredOutputResponse>
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
        $this->finalResponse();
        return $this->execution->output();
    }

    /**
     * Processes response stream and returns the final Instructor response.
     * Memoized: drains the stream on first call, returns cached result on subsequent calls.
     */
    public function finalResponse() : StructuredOutputResponse {
        if ($this->finalizedResponse !== null) {
            return $this->finalizedResponse;
        }

        if (!$this->streamCompleted) {
            foreach ($this->streamResponses() as $_) {
                // Just consume the stream, streamResponses() handles the updates
            }
        }

        $response = $this->resolveFinalResponse();
        if ($response === null) {
            throw new RuntimeException(
                'Final response is unavailable: stream completed without finalized inference response.'
            );
        }

        $this->finalizedResponse = $response;
        $this->lastValue = $this->execution->output();
        $this->projector->generated($response, $this->executionForEvent($response));

        return $this->finalizedResponse;
    }

    /**
     * Returns raw stream of Instructor response emissions for custom processing.
     * StructuredOutputStarted is dispatched when the stream is created.
     * Processing with this method does not emit response update events or usage updates.
     *
     * @return Generator<StructuredOutputResponse>
     */
    public function getIterator() : Generator {
        while ($this->emitter->hasNextEmission()) {
            $response = $this->emitter->nextEmission();
            if ($response !== null) {
                yield $response;
            }
        }
        $this->execution = $this->emitter->execution();
        $this->streamCompleted = true;
    }

    /**
     * Convenience: aggregated usage for the last response seen on the stream.
     */
    public function usage() : InferenceUsage {
        return $this->currentResponse()?->usage() ?? $this->execution->usage();
    }

    public function finalInferenceResponse() : InferenceResponse {
        return $this->finalResponse()->inferenceResponse();
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    /**
     * Handles stream iteration, replay, and last response tracking.
     * Dispatches per-item StructuredOutputResponseUpdated events.
     *
     * @return Generator<StructuredOutputResponse> Yields partial and final responses.
     */
    private function streamResponses(): Generator {
        if ($this->streamCompleted) {
            if (!$this->cache->canReplay()) {
                throw new RuntimeException(
                    'Stream is exhausted and cannot be replayed. Enable response stream caching to iterate again.'
                );
            }
            foreach ($this->cache->replay() as $response) {
                $this->rememberResponse($response);
                if ($this->projector->wantsUpdates()) {
                    $this->projector->updated($response, $this->executionForEvent($response));
                }
                yield $response;
            }
            return;
        }

        while ($this->emitter->hasNextEmission()) {
            $response = $this->emitter->nextEmission();
            if ($response === null) {
                continue;
            }

            $this->cache->remember($response);
            $this->rememberResponse($response);
            $this->syncExecutionState($response);
            if ($this->projector->wantsUpdates()) {
                $this->projector->updated($response, $this->executionForEvent($response));
            }
            yield $response;
        }

        $this->execution = $this->emitter->execution();
        $this->streamCompleted = true;
    }

    private function resolveFinalResponse(): ?StructuredOutputResponse {
        if ($this->lastResponse !== null && !$this->lastResponse->isPartial()) {
            return $this->lastResponse;
        }

        if ($this->execution->isFinalized()) {
            $rawResponse = $this->execution->inferenceResponse();
            if ($rawResponse === null) {
                return null;
            }

            return StructuredOutputResponse::final(
                value: $this->execution->output(),
                inferenceResponse: $rawResponse,
            );
        }

        return null;
    }

    private function currentResponse(): ?StructuredOutputResponse {
        return $this->lastResponse;
    }

    private function rememberResponse(StructuredOutputResponse $response): void {
        $this->lastResponse = $response;
        $this->lastValue = $response->value();
    }

    private function syncExecutionState(StructuredOutputResponse $response): void
    {
        if ($response->isPartial()) {
            return;
        }

        $this->execution = $this->emitter->execution();
        $this->lastValue = $this->execution->output();
    }

    private function executionForEvent(StructuredOutputResponse $response) : StructuredOutputExecution
    {
        if ($response->isPartial()) {
            return $this->emitter->execution();
        }

        return $this->execution;
    }
}
