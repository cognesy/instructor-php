<?php declare(strict_types=1);

namespace Cognesy\Agents\Events;

use Cognesy\Polyglot\Inference\Data\Usage;
use DateTimeImmutable;

/**
 * Dispatched when an inference response is received from the LLM.
 * Use to track LLM latency separate from step processing time.
 */
final class InferenceResponseReceived extends AgentEvent
{
    public readonly DateTimeImmutable $receivedAt;

    public function __construct(
        public readonly string $agentId,
        public readonly ?string $parentAgentId,
        public readonly int $stepNumber,
        public readonly ?Usage $usage,
        public readonly ?string $finishReason,
        public readonly DateTimeImmutable $requestStartedAt,
    ) {
        $this->receivedAt = new DateTimeImmutable();

        parent::__construct([
            'agentId' => $this->agentId,
            'parentAgentId' => $this->parentAgentId,
            'step' => $this->stepNumber,
            'inputTokens' => $this->usage?->inputTokens,
            'outputTokens' => $this->usage?->outputTokens,
            'finishReason' => $this->finishReason,
            'duration_ms' => $this->getDurationMs(),
        ]);
    }

    #[\Override]
    public function __toString(): string {
        $parentInfo = $this->parentAgentId ? sprintf(' [parent=%s]', substr($this->parentAgentId, 0, 8)) : '';
        $tokens = $this->usage?->total() ?? 0;

        return sprintf(
            'Agent [%s]%s inference response received [tokens=%d, duration=%dms]',
            substr($this->agentId, 0, 8),
            $parentInfo,
            $tokens,
            $this->getDurationMs()
        );
    }

    private function getDurationMs(): int {
        $diff = $this->receivedAt->getTimestamp() - $this->requestStartedAt->getTimestamp();
        $microDiff = (int) ($this->receivedAt->format('u')) - (int) ($this->requestStartedAt->format('u'));
        return ($diff * 1000) + (int) ($microDiff / 1000);
    }
}
