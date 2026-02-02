<?php declare(strict_types=1);

namespace Cognesy\Agents\Events;

use DateTimeImmutable;

/**
 * Dispatched when validation fails for extracted data or decisions.
 * Use to track validation errors and understand extraction quality.
 */
final class ValidationFailed extends AgentEvent
{
    public readonly DateTimeImmutable $failedAt;

    public function __construct(
        public readonly string $agentId,
        public readonly ?string $parentAgentId,
        public readonly int $stepNumber,
        public readonly string $validationType,
        public readonly array $errors,
    ) {
        $this->failedAt = new DateTimeImmutable();

        parent::__construct([
            'agentId' => $this->agentId,
            'parentAgentId' => $this->parentAgentId,
            'step' => $this->stepNumber,
            'validationType' => $this->validationType,
            'errors' => $this->errors,
            'errorCount' => count($this->errors),
        ]);
    }

    #[\Override]
    public function __toString(): string {
        $parentInfo = $this->parentAgentId ? sprintf(' [parent=%s]', substr($this->parentAgentId, 0, 8)) : '';
        $errorCount = count($this->errors);
        $errorSummary = $errorCount > 0 ? implode('; ', array_slice($this->errors, 0, 3)) : 'no details';
        if ($errorCount > 3) {
            $errorSummary .= sprintf(' (+%d more)', $errorCount - 3);
        }

        return sprintf(
            'Agent [%s]%s validation failed (%s): %s',
            substr($this->agentId, 0, 8),
            $parentInfo,
            $this->validationType,
            $errorSummary
        );
    }
}
