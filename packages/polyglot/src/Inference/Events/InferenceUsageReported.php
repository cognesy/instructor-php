<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Events;

use Cognesy\Polyglot\Inference\Data\Usage;

/**
 * Dispatched when token usage information is available.
 * Provides detailed token breakdown for cost tracking and analytics.
 */
final class InferenceUsageReported extends InferenceEvent
{
    public function __construct(
        public readonly string $executionId,
        public readonly Usage $usage,
        public readonly ?string $model = null,
        public readonly bool $isFinal = true,
    ) {
        parent::__construct([
            'executionId' => $this->executionId,
            'model' => $this->model,
            'isFinal' => $this->isFinal,
            'inputTokens' => $this->usage->inputTokens,
            'outputTokens' => $this->usage->outputTokens,
            'cacheWriteTokens' => $this->usage->cacheWriteTokens,
            'cacheReadTokens' => $this->usage->cacheReadTokens,
            'reasoningTokens' => $this->usage->reasoningTokens,
            'totalTokens' => $this->usage->total(),
        ]);
    }

    #[\Override]
    public function __toString(): string {
        return sprintf(
            'Usage [%s] %s%s',
            $this->executionId,
            $this->usage->toString(),
            $this->isFinal ? '' : ' (partial)'
        );
    }
}
