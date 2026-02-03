<?php declare(strict_types=1);

namespace Cognesy\Agents\Events;

use Cognesy\Agents\Core\Enums\ExecutionStatus;
use Cognesy\Polyglot\Inference\Data\Usage;
use DateTimeImmutable;

/**
 * Dispatched when an agent completes execution.
 * Contains final execution metrics and agent state summary.
 */
final class AgentExecutionCompleted extends AgentEvent
{
    public readonly DateTimeImmutable $completedAt;

    public function __construct(
        public readonly string          $agentId,
        public readonly ?string         $parentAgentId,
        public readonly ExecutionStatus $status,
        public readonly int             $totalSteps,
        public readonly Usage           $totalUsage,
        public readonly ?string         $errors,
    ) {
        $this->completedAt = new DateTimeImmutable();

        parent::__construct([
            'agentId' => $this->agentId,
            'parentAgentId' => $this->parentAgentId,
            'status' => $this->status->value,
            'steps' => $this->totalSteps,
            'usage' => $this->totalUsage->toArray(),
            'errors' => $this->errors,
        ]);
    }

    #[\Override]
    public function __toString(): string {
        $parentInfo = $this->parentAgentId ? sprintf(' [parent=%s]', substr($this->parentAgentId, 0, 8)) : '';

        return sprintf(
            'Agent [%s]%s completed - %d steps, %d tokens%s',
            substr($this->agentId, 0, 8),
            $parentInfo,
            $this->totalSteps,
            $this->totalUsage->total(),
            $this->errors ? " (with errors: {$this->errors})" : ''
        );
    }
}

