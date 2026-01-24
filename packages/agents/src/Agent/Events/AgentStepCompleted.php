<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Events;

use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use DateTimeImmutable;

/**
 * Dispatched when an agent step completes execution.
 * Contains step results, tool usage, errors, and timing information.
 */
final class AgentStepCompleted extends AgentEvent
{
    public readonly DateTimeImmutable $completedAt;
    public readonly float $durationMs;

    public function __construct(
        public readonly string $agentId,
        public readonly ?string $parentAgentId,
        public readonly int $stepNumber,
        public readonly bool $hasToolCalls,
        public readonly int $errorCount,
        public readonly string $errorMessages,
        public readonly Usage $usage,
        public readonly ?InferenceFinishReason $finishReason,
        DateTimeImmutable $startedAt,
    ) {
        $this->completedAt = new DateTimeImmutable();
        $this->durationMs = $this->calculateDurationMs($startedAt);

        parent::__construct([
            'agentId' => $this->agentId,
            'parentAgentId' => $this->parentAgentId,
            'step' => $this->stepNumber,
            'hasToolCalls' => $this->hasToolCalls,
            'errors' => $this->errorCount,
            'errorMessages' => $this->errorMessages,
            'usage' => $this->usage->toArray(),
            'finishReason' => $this->finishReason?->value,
            'durationMs' => $this->durationMs,
        ]);
    }

    private function calculateDurationMs(DateTimeImmutable $start): float {
        $interval = $start->diff($this->completedAt);
        return ($interval->s * 1000) + ($interval->f * 1000);
    }

    #[\Override]
    public function __toString(): string {
        $parentInfo = $this->parentAgentId ? sprintf(' [parent=%s]', substr($this->parentAgentId, 0, 8)) : '';

        return sprintf(
            'Agent [%s]%s step %d completed (tools=%s, errors=%d, tokens=%d, reason=%s, %.2fms)',
            substr($this->agentId, 0, 8),
            $parentInfo,
            $this->stepNumber,
            $this->hasToolCalls ? 'yes' : 'no',
            $this->errorCount,
            $this->usage->total(),
            $this->finishReason?->value ?? 'unknown',
            $this->durationMs
        );
    }
}

