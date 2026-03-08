<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Streaming;

/**
 * Tracks whether the stream state has changed visibly since the last emission.
 * Encapsulates the deduplication logic that prevents emitting invisible deltas.
 */
final class VisibilityTracker
{
    private int $lastContentLength = 0;
    private int $lastReasoningLength = 0;
    private string $lastFinishReason = '';
    private int $lastToolMutationCount = 0;
    private int $lastValueRevision = 0;

    public function hasVisibleChange(InferenceStreamState $state): bool
    {
        return match (true) {
            $state->contentLength() !== $this->lastContentLength => true,
            $state->reasoningContentLength() !== $this->lastReasoningLength => true,
            $state->finishReason() !== $this->lastFinishReason => true,
            $state->toolMutationCount() !== $this->lastToolMutationCount => true,
            $state->valueRevision() !== $this->lastValueRevision => true,
            default => false,
        };
    }

    public function remember(InferenceStreamState $state): void
    {
        $this->lastContentLength = $state->contentLength();
        $this->lastReasoningLength = $state->reasoningContentLength();
        $this->lastFinishReason = $state->finishReason();
        $this->lastToolMutationCount = $state->toolMutationCount();
        $this->lastValueRevision = $state->valueRevision();
    }
}
