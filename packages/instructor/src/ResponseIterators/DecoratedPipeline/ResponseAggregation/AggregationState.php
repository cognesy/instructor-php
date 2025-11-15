<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\DecoratedPipeline\ResponseAggregation;

use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Utils\Json\Json;

/**
 * Rolling aggregate that maintains summary state without storing all partials.
 * Enables O(1) memory streaming with full observability.
 *
 * @template TValue
 */
final readonly class AggregationState
{
    /**
     * @param TValue|null $latestValue
     */
    public function __construct(
        public string $content,           // Latest assembled content (JSON or text)
        public Usage $usage,              // Token counts (cumulative)
        public mixed $latestValue,        // Most recent deserialized value
        public int $partialCount,         // Number of partials processed
        public ?string $finishReason,     // LLM finish reason (if completed)
        public PartialInferenceResponse $partial, // Optional accumulated partials
    ) {}

    public static function empty(): self {
        return new self(
            content: '',
            usage: Usage::none(),
            latestValue: null,
            partialCount: 0,
            finishReason: null,
            partial: PartialInferenceResponse::empty(),
        );
    }

    /**
     * Merge a new partial response into rolling aggregate.
     * O(1) operation - only updates counters and latest value.
     */
    public function merge(PartialInferenceResponse $partial): self {
        return new self(
            content: $partial->content() !== '' ? $partial->content() : $this->content,
            usage: $this->usage->withAccumulated($partial->usage()),
            latestValue: $partial->value() ?? $this->latestValue,
            partialCount: $this->partialCount + 1,
            finishReason: $partial->finishReason() ?: $this->finishReason,
            partial: $this->partial,
        );
    }

    public function withPartialAppended(PartialInferenceResponse $partial): self {
        return new self(
            content: $this->content,
            usage: $this->usage,
            latestValue: $this->latestValue,
            partialCount: $this->partialCount,
            finishReason: $this->finishReason,
            partial: $this->partial->withAccumulatedContent($partial),
        );
    }

    public function partial(): PartialInferenceResponse {
        return $this->partial;
    }

    public function finishReason(): ?string {
        return $this->finishReason;
    }

    /**
     * Convert to InferenceResponse for validation.
     */
    public function toInferenceResponse(): InferenceResponse {
        // If no textual content was assembled but we have a latest value
        // (e.g., driver provided structured value directly), synthesize
        // JSON content from the value to allow downstream processors that
        // operate on content to succeed.
        $content = $this->content;
        if ($content === '' && $this->latestValue !== null) {
            $content = Json::encode($this->latestValue);
        }

        return new InferenceResponse(
            content: $content,
            finishReason: $this->finishReason ?? 'stop',
            usage: $this->usage,
            value: $this->latestValue,
        );
    }
}
