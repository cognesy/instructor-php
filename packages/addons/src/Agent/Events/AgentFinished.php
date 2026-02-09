<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Events;

use Cognesy\Addons\Agent\Core\Enums\AgentStatus;
use Cognesy\Polyglot\Inference\Data\Usage;
use DateTimeImmutable;

/**
 * Dispatched when an agent completes execution successfully.
 * Contains final execution metrics and agent state summary.
 */
final class AgentFinished extends AgentEvent
{
    public readonly DateTimeImmutable $finishedAt;

    public function __construct(
        public readonly string $agentId,
        public readonly ?string $parentAgentId,
        public readonly AgentStatus $status,
        public readonly int $totalSteps,
        public readonly Usage $totalUsage,
        public readonly ?string $errors,
    ) {
        $this->finishedAt = new DateTimeImmutable();

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
            'Agent [%s]%s finished - %d steps, %d tokens%s',
            substr($this->agentId, 0, 8),
            $parentInfo,
            $this->totalSteps,
            $this->totalUsage->total(),
            $this->errors ? " (with errors: {$this->errors})" : ''
        );
    }
}

