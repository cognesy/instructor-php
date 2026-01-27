<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Events;

use Cognesy\Agents\Core\Enums\AgentStatus;
use DateTimeImmutable;

/**
 * Dispatched when agent state is updated after applying a step.
 * Contains state snapshot and current step information for observability.
 */
final class AgentStateUpdated extends AgentEvent
{
    public readonly DateTimeImmutable $updatedAt;

    public function __construct(
        public readonly string $agentId,
        public readonly ?string $parentAgentId,
        public readonly AgentStatus $status,
        public readonly int $stepCount,
        public readonly array $stateSnapshot,
        public readonly array $currentStepSnapshot,
    ) {
        $this->updatedAt = new DateTimeImmutable();

        parent::__construct([
            'agentId' => $this->agentId,
            'parentAgentId' => $this->parentAgentId,
            'status' => $this->status->value,
            'stepCount' => $this->stepCount,
            'state' => $this->stateSnapshot,
            'step' => $this->currentStepSnapshot,
        ]);
    }

    #[\Override]
    public function __toString(): string {
        $parentInfo = $this->parentAgentId ? sprintf(' [parent=%s]', substr($this->parentAgentId, 0, 8)) : '';

        return sprintf(
            'Agent [%s]%s state updated - step %d, status=%s',
            substr($this->agentId, 0, 8),
            $parentInfo,
            $this->stepCount,
            $this->status->value
        );
    }
}