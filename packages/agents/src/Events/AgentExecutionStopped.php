<?php declare(strict_types=1);

namespace Cognesy\Agents\Events;

use Cognesy\Agents\Core\Stop\StopReason;
use DateTimeImmutable;

/**
 * Dispatched when an agent loop is stopped due to stop signals before normal completion.
 */
final class AgentExecutionStopped extends AgentEvent
{
    public readonly DateTimeImmutable $stoppedAt;

    public function __construct(
        public readonly string      $agentId,
        public readonly ?string     $parentAgentId,
        public readonly StopReason  $stopReason,
        public readonly string      $stopMessage,
        public readonly ?string     $source,
        public readonly int         $totalSteps,
    ) {
        $this->stoppedAt = new DateTimeImmutable();

        parent::__construct([
            'agentId' => $this->agentId,
            'parentAgentId' => $this->parentAgentId,
            'stopReason' => $this->stopReason->value,
            'stopMessage' => $this->stopMessage,
            'source' => $this->source,
            'steps' => $this->totalSteps,
        ]);
    }

    #[\Override]
    public function __toString(): string {
        $parentInfo = $this->parentAgentId ? sprintf(' [parent=%s]', substr($this->parentAgentId, 0, 8)) : '';
        $sourceInfo = $this->source ? " by {$this->source}" : '';

        return sprintf(
            'Agent [%s]%s stopped after %d steps: %s%s%s',
            substr($this->agentId, 0, 8),
            $parentInfo,
            $this->totalSteps,
            $this->stopReason->value,
            $this->stopMessage !== '' ? " ({$this->stopMessage})" : '',
            $sourceInfo,
        );
    }
}
