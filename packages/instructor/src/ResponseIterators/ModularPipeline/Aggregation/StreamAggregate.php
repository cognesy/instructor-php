<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\ModularPipeline\Aggregation;

use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Utils\Json\Json;

/**
 * Rolling aggregate for streaming phase with O(1) memory.
 *
 * Maintains:
 * - Latest content/value (not full history)
 * - Cumulative usage counters
 * - Optional partials list (if tracking enabled)
 *
 * Replaces AggregationState with clearer semantics:
 * - Focused on stream aggregation only
 * - Distinct from StructuredOutputAttemptState (execution-level)
 * - Generic over value type
 *
 * @template TValue
 */
final readonly class StreamAggregate
{
    /**
     * @param TValue|null $latestValue
     */
    public function __construct(
        public string $content,
        public mixed $latestValue,
        public ?string $finishReason,
        public Usage $usage,
        public int $frameCount,
        public ?PartialInferenceResponse $partial,
    ) {}

    public static function empty(bool $trackPartials = false): self {
        return new self(
            content: '',
            latestValue: null,
            finishReason: null,
            usage: Usage::none(),
            frameCount: 0,
            partial: $trackPartials ? PartialInferenceResponse::empty() : null,
        );
    }

    /**
     * Merge a new partial response (O(1) operation).
     *
     * Updates latest content/value and cumulative counters.
     */
    public function merge(PartialInferenceResponse $partial): self {
        // Accumulate partial if tracking is enabled
        $newPartial = null;
        if ($this->partial !== null) {
            $newPartial = $partial->withAccumulatedContent($this->partial);
        }

        // Prefer accumulated content from partial; if missing or not tracking, accumulate using contentDelta
        $nextContent = $newPartial?->content() !== '' && $newPartial !== null
            ? $newPartial->content()
            : ($this->content . ($partial->contentDelta ?? ''));

        // Usage: if tracking partials, withAccumulatedContent already accumulated usage in $newPartial,
        // so use that directly. Otherwise, accumulate from $partial.
        $nextUsage = $newPartial !== null
            ? $newPartial->usage()
            : $this->usage->withAccumulated($partial->usage());

        return new self(
            content: $nextContent,
            latestValue: $partial->value() ?? $this->latestValue,
            finishReason: $partial->finishReason() ?: $this->finishReason,
            usage: $nextUsage,
            frameCount: $this->frameCount + 1,
            partial: $newPartial,
        );
    }

    /**
     * Get partials list (empty if not tracking).
     */
    public function partial(): PartialInferenceResponse {
        return $this->partial ?? PartialInferenceResponse::empty();
    }

    public function finishReason(): ?string {
        return $this->finishReason;
    }

    /**
     * Convert to InferenceResponse for validation/processing.
     */
    public function toInferenceResponse(): InferenceResponse {
        $content = $this->content;

        // Reconstruct from partials if still empty
        if ($content === '' && ($this->partial !== null)) {
            $content = $this->partial->content();
        }

        // As a last resort, synthesize from value
        if ($content === '' && $this->latestValue !== null) {
            $content = Json::encode($this->latestValue);
        }

        // Use latestValue as-is - it's already properly deserialized with correct types
        // (enums, DateTime, nested objects, etc.) by the deserialization pipeline
        return new InferenceResponse(
            content: $content,
            finishReason: $this->finishReason ?? 'stop',
            usage: $this->usage,
            value: $this->latestValue,
        );
    }
}
