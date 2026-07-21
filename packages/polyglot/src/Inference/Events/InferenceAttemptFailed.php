<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Events;

use Psr\Log\LogLevel;

/**
 * Dispatched when a single inference attempt fails.
 *
 * This event is the canonical owner of the failed-attempt schema. The
 * sanitized failure text lives under the `errorMessage` key; telemetry
 * consumers must read that key (not `exception`). Supplementary fields such as
 * partial usage counters and telemetry correlation are supplied via `$data`
 * and merged into the legacy payload array for backward compatibility.
 */
final class InferenceAttemptFailed extends InferenceEvent
{
    public string $logLevel = LogLevel::WARNING;

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
        string $errorMessage,
        string $errorType,
        ?int $httpStatusCode,
        bool $willRetry,
        float $durationMs,
        array $data = [],
    ): self {
        return new self([
            ...$data,
            'executionId' => $executionId,
            'attemptId' => $attemptId,
            'attemptNumber' => $attemptNumber,
            'errorMessage' => $errorMessage,
            'errorType' => $errorType,
            'httpStatusCode' => $httpStatusCode,
            'willRetry' => $willRetry,
            'durationMs' => $durationMs,
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

    public function errorMessage(): ?string {
        return $this->stringValue('errorMessage');
    }

    public function errorType(): ?string {
        return $this->stringValue('errorType');
    }

    public function httpStatusCode(): ?int {
        return $this->intValue('httpStatusCode');
    }

    public function willRetry(): ?bool {
        return $this->boolValue('willRetry');
    }

    public function durationMs(): ?float {
        return $this->floatValue('durationMs');
    }
}
