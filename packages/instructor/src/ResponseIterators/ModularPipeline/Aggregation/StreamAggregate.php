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
 * - Latest partial snapshot
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
        public PartialInferenceResponse $partial,
    ) {}

    public static function empty(): self {
        return new self(
            content: '',
            latestValue: null,
            finishReason: null,
            usage: Usage::none(),
            frameCount: 0,
            partial: PartialInferenceResponse::empty(),
        );
    }

    /**
     * Merge a new partial response (O(1) operation).
     *
     * Trusts incoming snapshot for content/usage/value.
     */
    public function merge(PartialInferenceResponse $partial): self {
        return new self(
            content: $partial->content(),
            latestValue: $partial->value() ?? $this->latestValue,
            finishReason: $partial->finishReason() ?: $this->finishReason,
            usage: $partial->usage(),
            frameCount: $this->frameCount + 1,
            partial: $partial,
        );
    }

    public function partial(): PartialInferenceResponse {
        return $this->partial;
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
        if ($content === '') {
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
