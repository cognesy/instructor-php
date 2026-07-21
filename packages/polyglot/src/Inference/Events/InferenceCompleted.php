<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Events;

use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Psr\Log\LogLevel;

/**
 * Dispatched once when an inference execution completes (success or failure).
 * Owns the canonical completion payload schema (including usage token counts);
 * telemetry envelope data is supplied via $data.
 */
final class InferenceCompleted extends InferenceEvent
{
    public string $logLevel = LogLevel::INFO;

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
        bool $isSuccess,
        string $finishReason,
        float $durationMs,
        int $attemptCount,
        InferenceUsage $usage,
        array $data = [],
    ): self {
        return new self([
            ...$data,
            'executionId' => $executionId,
            'isSuccess' => $isSuccess,
            'finishReason' => $finishReason,
            'durationMs' => $durationMs,
            'attemptCount' => $attemptCount,
            ...$usage->toTokenCounts(),
        ]);
    }

    public function executionId(): ?string {
        return $this->stringValue('executionId');
    }

    public function isSuccess(): ?bool {
        return $this->boolValue('isSuccess');
    }

    public function finishReason(): ?string {
        return $this->stringValue('finishReason');
    }

    public function durationMs(): ?float {
        return $this->floatValue('durationMs');
    }

    public function attemptCount(): ?int {
        return $this->intValue('attemptCount');
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
