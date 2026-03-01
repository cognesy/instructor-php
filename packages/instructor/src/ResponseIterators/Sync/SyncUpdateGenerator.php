<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\Sync;

use Cognesy\Instructor\Contracts\CanStreamStructuredOutputUpdates;
use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Data\StructuredOutputAttemptState;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Enums\AttemptPhase;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Json\Json;

/**
 * Stream iterator for synchronous (non-streaming) execution.
 *
 * Scope: Single attempt only (does NOT handle validation or retries)
 * Pattern: Makes one inference request, yields one update
 *
 * Responsibility:
 * - Make non-streaming inference request
 * - Return single update (no actual streaming)
 * - Signal exhaustion immediately
 *
 * Design note: Sync execution is modeled as streaming with a single chunk.
 * This allows using the same AttemptIterator orchestrator for both sync and streaming.
 */
final readonly class SyncUpdateGenerator implements CanStreamStructuredOutputUpdates
{
    public function __construct(
        private InferenceProvider $inferenceProvider,
    ) {}

    #[\Override]
    public function hasNext(StructuredOutputExecution $execution): bool {
        $state = $execution->attemptState();

        // Not started yet - can make request
        if ($state === null) {
            return true;
        }

        // Already made request - no more updates (sync = single chunk)
        return $state->hasMoreChunks();
    }

    #[\Override]
    public function nextChunk(StructuredOutputExecution $execution): StructuredOutputExecution {
        $state = $execution->attemptState();
        if ($state !== null && !$state->hasMoreChunks()) {
            return $execution;
        }
        $inference = $this->inferenceProvider->getInference($execution)->response();
        $inference = $this->normalizeContent($inference, $execution->outputMode());
        $attemptState = StructuredOutputAttemptState::fromSingleChunk(
            $inference,
            PartialInferenceResponse::empty(),
            AttemptPhase::Done,
        );
        return $execution
            ->withAttemptState($attemptState)
            ->withCurrentAttempt(
                inferenceResponse: $inference,
                partialInferenceResponse: PartialInferenceResponse::empty(),
                errors: $execution->currentErrors(),
            );
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    private function normalizeContent(InferenceResponse $response, OutputMode $mode): InferenceResponse {
        return $response->withContent(match ($mode) {
            OutputMode::Text => $response->content(),
            OutputMode::Tools => $response->toolCalls()->first()?->argsAsJson()
                ?: $response->content() // fallback if no tool calls - some LLMs return just a string
                    ?: '',
            // for OutputMode::MdJson, OutputMode::Json, OutputMode::JsonSchema try extracting JSON from content
            // and replacing original content with it
            default => Json::fromString($response->content())->toString(),
        });
    }
}
