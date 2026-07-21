<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Events;

use Cognesy\Polyglot\Inference\Data\InferenceUsage;

/**
 * Dispatched when a single inference attempt succeeds. Owns the canonical
 * successful-attempt payload schema (including usage token counts); telemetry
 * envelope data is supplied via $data.
 */
final class InferenceAttemptSucceeded extends InferenceEvent
{
    /**
     * @param array<string,mixed> $data
     */
    public function __construct(array $data = []) {
        parent::__construct($data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function fromLifecycle(
        string $executionId,
        string $attemptId,
        int $attemptNumber,
        string $finishReason,
        float $durationMs,
        InferenceUsage $usage,
        array $data = [],
    ): self {
        return new self([
            ...$data,
            'executionId' => $executionId,
            'attemptId' => $attemptId,
            'attemptNumber' => $attemptNumber,
            'finishReason' => $finishReason,
            'durationMs' => $durationMs,
            ...$usage->toTokenCounts(),
        ]);
    }

    public function executionId(): ?string {
        return $this->stringValue('executionId');
    }

    public function attemptId(): ?string {
        return $this->stringValue('attemptId');
    }

    public function attemptNumber(): ?int {
        return $this->intValue('attemptNumber');
    }

    public function finishReason(): ?string {
        return $this->stringValue('finishReason');
    }

    public function durationMs(): ?float {
        return $this->floatValue('durationMs');
    }

    public function inputTokens(): ?int {
        return $this->intValue('inputTokens');
    }

    public function outputTokens(): ?int {
        return $this->intValue('outputTokens');
    }

    public function totalTokens(): ?int {
        return $this->intValue('totalTokens');
    }
}
