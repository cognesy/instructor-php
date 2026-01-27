<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Events;

use Cognesy\Agents\Core\Continuation\Data\ContinuationOutcome;

final class ContinuationEvaluated extends AgentEvent
{
    public function __construct(
        public readonly string $agentId,
        public readonly ?string $parentAgentId,
        public readonly int $stepNumber,
        public readonly ContinuationOutcome $outcome,
    ) {
        parent::__construct([
            'agentId' => $this->agentId,
            'parentAgentId' => $this->parentAgentId,
            'step' => $this->stepNumber,
            'shouldContinue' => $this->outcome->shouldContinue,
            'stopReason' => $this->outcome->stopReason()->value,
            'resolvedBy' => $this->outcome->resolvedBy(),
        ]);
    }

    #[\Override]
    public function __toString(): string {
        $action = 'STOP';
        $reason = $this->outcome->stopReason()->value;

        if ($this->outcome->shouldContinue) {
            $action = 'CONTINUE';
            $reason = "requested by {$this->outcome->resolvedBy()}";
        }

        return sprintf(
            'Agent [%s] step %d: %s (%s)',
            substr($this->agentId, 0, 8),
            $this->stepNumber,
            $action,
            $reason
        );
    }
}
