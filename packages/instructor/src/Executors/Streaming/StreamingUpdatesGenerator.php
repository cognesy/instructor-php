<?php declare(strict_types=1);

namespace Cognesy\Instructor\Executors\Streaming;

use Cognesy\Instructor\Contracts\CanStreamStructuredOutputUpdates;
use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Data\StructuredOutputAttemptState;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Enums\AttemptPhase;
use Cognesy\Instructor\Executors\Streaming\Contracts\CanGeneratePartials;
use Cognesy\Polyglot\Inference\Creation\InferenceResponseFactory;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;

/**
 * Iterates over chunks from a single streaming attempt using the legacy pipeline.
 *
 * Scope: Single attempt only (does NOT handle validation or retries)
 * State: Stored in execution->attemptState() (ephemeral, non-serializable)
 * Composable: Designed to be wrapped by AttemptIterator for retry logic
 *
 * Responsibility:
 * - Initialize stream on first call
 * - Process partial responses one at a time
 * - Build aggregate inference from accumulated partials
 * - Update execution with partial responses
 * - Signal when stream is exhausted
 */
final readonly class StreamingUpdatesGenerator implements CanStreamStructuredOutputUpdates
{
    public function __construct(
        private InferenceProvider $inferenceProvider,
        private CanGeneratePartials $partialsGenerator,
    ) {}

    #[\Override]
    public function hasNext(StructuredOutputExecution $execution): bool {
        $state = $execution->attemptState();

        // Not started yet - can initialize
        if ($state === null) {
            return true;
        }

        // Has more chunks to process
        return $state->hasMoreChunks();
    }

    #[\Override]
    public function nextChunk(StructuredOutputExecution $execution): StructuredOutputExecution {
        $state = $execution->attemptState();

        // Initialize stream on first call or if stream not initialized
        if ($state === null || !$state->isStreamInitialized()) {
            return $this->initializeStream($execution);
        }

        // Process next chunk
        return $this->processNextChunk($execution, $state);
    }

    /**
     * Initialize a new streaming session.
     * Creates fresh inference stream and wraps it with partials generator.
     */
    private function initializeStream(StructuredOutputExecution $execution): StructuredOutputExecution {
        $responseModel = $execution->responseModel();
        assert($responseModel !== null, 'Response model cannot be null');

        // Start fresh inference stream (non-deterministic, cannot be replayed)
        $inferenceStream = $this->inferenceProvider
            ->getInference($execution)
            ->stream()
            ->responses();

        // Wrap with partials generator
        $partialStream = $this->partialsGenerator->makePartialResponses(
            $inferenceStream,
            $responseModel
        );

        // Create streaming state with initialized stream
        $attemptState = StructuredOutputAttemptState::empty()
            ->withPhase(AttemptPhase::Streaming)
            ->withStream($partialStream);

        return $execution->withAttemptState($attemptState);
    }

    /**
     * Process the next partial response from the active stream.
     * Accumulates partials and builds aggregate inference response.
     */
    private function processNextChunk(
        StructuredOutputExecution $execution,
        StructuredOutputAttemptState $state,
    ): StructuredOutputExecution {
        $stream = $state->stream();
        assert($stream !== null, 'Stream must be initialized');

        // Stream should be valid (checked by hasNext)
        if (!$stream->valid()) {
            // Stream exhausted - mark as such
            return $execution->withAttemptState($state->withExhausted());
        }

        // Get current partial response
        /** @var PartialInferenceResponse $partial */
        $partial = $stream->current();
        $stream->next();

        // Check if stream is exhausted AFTER advancing (on same iterator instance)
        $isExhausted = !$stream->valid();

        // Accumulate partials
        $accumulatedPartials = $state->accumulatedPartials()
            ->withNewPartialResponse($partial);

        // Build aggregate inference from all partials so far
        $inference = InferenceResponseFactory::fromPartialResponses($accumulatedPartials)
            ->withValue($partial->value());

        // Update streaming state with processed chunk
        $newState = $state->withNextChunk($inference, $accumulatedPartials, $isExhausted);

        // Update execution with current attempt data
        return $execution
            ->withAttemptState($newState)
            ->withCurrentAttempt(
                inferenceResponse: $inference,
                partialInferenceResponses: $accumulatedPartials,
                errors: $execution->currentErrors(), // Preserve existing errors
            );
    }
}
