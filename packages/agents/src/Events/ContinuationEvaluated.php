<?php declare(strict_types=1);

namespace Cognesy\Agents\Events;

use Cognesy\Agents\Core\Data\ExecutionState;
use Cognesy\Agents\Core\Stop\StopReason;
use Cognesy\Agents\Core\Stop\StopSignal;

final class ContinuationEvaluated extends AgentEvent
{
    public function __construct(
        public readonly string          $agentId,
        public readonly ?string         $parentAgentId,
        public readonly int             $stepNumber,
        public readonly ?ExecutionState $executionState = null,
    ) {
        parent::__construct([
            'agentId' => $this->agentId,
            'parentAgentId' => $this->parentAgentId,
            'step' => $this->stepNumber,
            'status' => $this->explain(),
            'resolvedBy' => $this->resolvedBy(),
        ]);
    }

    #[\Override]
    public function __toString(): string {
        $action = $this->shouldStop() ? 'STOP' : 'CONTINUE';
        $reason = $this->explain();

        return sprintf(
            'Agent [%s] step %d: %s (%s)',
            substr($this->agentId, 0, 8),
            $this->stepNumber,
            $action,
            $reason
        );
    }

    public function explain(): string {
        return $this->executionState?->continuation()->explain() ?? 'No continuation';
    }

    public function shouldStop(): bool {
        return $this->executionState?->shouldStop() ?? true;
    }

    public function stopSignal(): ?StopSignal {
        return $this->executionState?->continuation()->stopSignals()->first();
    }

    public function stopReason(): ?StopReason {
        return $this->stopSignal()?->reason;
    }

    public function resolvedBy(): string {
        return $this->stopSignal()?->source ?? '';
    }
}
