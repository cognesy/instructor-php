<?php declare(strict_types=1);

namespace Cognesy\Agents\Events;

use Cognesy\Agents\Enums\ExecutionStatus;
use DateTimeImmutable;
use Override;

/**
 * Dispatched when an agent loop is torn down without reaching completion, because
 * the caller abandoned the iterate() generator or threw into it.
 */
final class AgentExecutionAbandoned extends AgentEvent
{
    public readonly DateTimeImmutable $abandonedAt;

    public function __construct(
        public readonly string $agentId,
        public readonly string $executionId,
        public readonly ?string $parentAgentId,
        public readonly ?ExecutionStatus $status,
        public readonly int $totalSteps,
        public readonly string $teardownError = '',
    ) {
        $this->abandonedAt = new DateTimeImmutable();

        parent::__construct([
            'agentId' => $this->agentId,
            'executionId' => $this->executionId,
            'parentAgentId' => $this->parentAgentId,
            'status' => $this->status?->value,
            'steps' => $this->totalSteps,
            'teardownError' => $this->teardownError,
        ]);
    }

    #[Override]
    public function __toString(): string {
        $parentInfo = $this->parentAgentId ? sprintf(' [parent=%s]', substr($this->parentAgentId, 0, 8)) : '';

        return sprintf(
            'Agent [%s]%s abandoned after %d steps with status %s%s',
            substr($this->agentId, 0, 8),
            $parentInfo,
            $this->totalSteps,
            $this->status?->value ?? 'unknown',
            $this->teardownError !== '' ? " (teardown error: {$this->teardownError})" : '',
        );
    }
}
