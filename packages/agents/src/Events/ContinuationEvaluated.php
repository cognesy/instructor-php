<?php declare(strict_types=1);

namespace Cognesy\Agents\Events;

use Cognesy\Agents\Core\Stop\ExecutionContinuation;
use Cognesy\Agents\Core\Stop\StopReason;
use Cognesy\Agents\Core\Stop\StopSignal;

final class ContinuationEvaluated extends AgentEvent
{
    public function __construct(
        public readonly string                 $agentId,
        public readonly ?string                $parentAgentId,
        public readonly int                    $stepNumber,
        public readonly ?ExecutionContinuation $continuation = null,
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
        $action = $this->shouldContinue() ? 'CONTINUE' : 'STOP';
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
        return $this->continuation?->explain() ?? 'No continuation';
    }

    public function shouldContinue(): bool {
        return $this->continuation !== null && !$this->continuation->shouldStop();
    }

    public function stopSignal(): ?StopSignal {
        return $this->continuation?->stopSignals()->first();
    }

    public function stopReason(): ?StopReason {
        return $this->stopSignal()?->reason;
    }

    public function resolvedBy(): string {
        return $this->stopSignal()?->source ?? '';
    }
}
