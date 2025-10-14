<?php declare(strict_types=1);

namespace Cognesy\Instructor\Partials\Data;

use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;

/**
 * Rolling aggregate that maintains summary state without storing all partials.
 * Enables O(1) memory streaming with full observability.
 *
 * @template TValue
 */
final readonly class AggregatedResponse
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
    ) {}

    public static function empty(): self {
        return new self(
            content: '',
            usage: Usage::none(),
            latestValue: null,
            partialCount: 0,
            finishReason: null,
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
            finishReason: $partial->finishReason ?: $this->finishReason,
        );
    }

    /**
     * Convert to InferenceResponse for validation.
     */
    public function toInferenceResponse(): InferenceResponse {
        return new InferenceResponse(
            content: $this->content,
            finishReason: $this->finishReason ?? 'stop',
            usage: $this->usage,
            value: $this->latestValue,
        );
    }
}
