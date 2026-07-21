<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Events;

use Cognesy\Polyglot\Inference\Data\InferenceUsage;

/**
 * Dispatched when usage is reported for an execution. Owns the canonical usage
 * payload schema (including token counts); telemetry envelope data is supplied
 * via $data.
 */
final class InferenceUsageReported extends InferenceEvent
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
        ?string $model,
        bool $isFinal,
        InferenceUsage $usage,
        array $data = [],
    ): self {
        return new self([
            ...$data,
            'executionId' => $executionId,
            'model' => $model,
            'isFinal' => $isFinal,
            ...$usage->toTokenCounts(),
        ]);
    }

    public function executionId(): ?string {
        return $this->stringValue('executionId');
    }

    public function model(): ?string {
        return $this->stringValue('model');
    }

    public function isFinal(): ?bool {
        return $this->boolValue('isFinal');
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
