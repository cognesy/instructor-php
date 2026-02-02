<?php declare(strict_types=1);

namespace Cognesy\Agents\Events;

use DateTimeImmutable;

/**
 * Dispatched when decision/structured output extraction fails.
 * Use to track extraction errors and retry attempts.
 */
final class DecisionExtractionFailed extends AgentEvent
{
    public readonly DateTimeImmutable $failedAt;

    public function __construct(
        public readonly string $agentId,
        public readonly ?string $parentAgentId,
        public readonly int $stepNumber,
        public readonly string $errorMessage,
        public readonly string $errorType,
        public readonly int $attemptNumber = 1,
        public readonly int $maxAttempts = 1,
    ) {
        $this->failedAt = new DateTimeImmutable();

        parent::__construct([
            'agentId' => $this->agentId,
            'parentAgentId' => $this->parentAgentId,
            'step' => $this->stepNumber,
            'error' => $this->errorMessage,
            'errorType' => $this->errorType,
            'attempt' => $this->attemptNumber,
            'maxAttempts' => $this->maxAttempts,
        ]);
    }

    #[\Override]
    public function __toString(): string {
        $parentInfo = $this->parentAgentId ? sprintf(' [parent=%s]', substr($this->parentAgentId, 0, 8)) : '';
        $attemptInfo = $this->maxAttempts > 1 ? " (attempt {$this->attemptNumber}/{$this->maxAttempts})" : '';

        return sprintf(
            'Agent [%s]%s decision extraction failed%s: %s',
            substr($this->agentId, 0, 8),
            $parentInfo,
            $attemptInfo,
            $this->errorMessage
        );
    }
}
