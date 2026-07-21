<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Events;

/**
 * Dispatched once when an inference execution begins. Owns the canonical
 * started-event payload schema; telemetry envelope data is supplied via $data.
 */
final class InferenceStarted extends InferenceEvent
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
        string $requestId,
        bool $isStreamed,
        ?string $model,
        int $messageCount,
        array $data = [],
    ): self {
        return new self([
            ...$data,
            'executionId' => $executionId,
            'requestId' => $requestId,
            'isStreamed' => $isStreamed,
            'model' => $model,
            'messageCount' => $messageCount,
        ]);
    }

    public function executionId(): ?string {
        return $this->stringValue('executionId');
    }

    public function requestId(): ?string {
        return $this->stringValue('requestId');
    }

    public function isStreamed(): ?bool {
        return $this->boolValue('isStreamed');
    }

    public function model(): ?string {
        return $this->stringValue('model');
    }

    public function messageCount(): ?int {
        return $this->intValue('messageCount');
    }
}
