<?php declare(strict_types=1);

namespace Cognesy\Agents\Events;

use Cognesy\Polyglot\Inference\Data\Usage;
use DateTimeImmutable;

/**
 * Dispatched after any token-consuming operation (LLM call, tool call).
 * Enables tracking token usage across agent hierarchies.
 */
final class TokenUsageReported extends AgentEvent
{
    public readonly DateTimeImmutable $reportedAt;

    public function __construct(
        public readonly string $agentId,
        public readonly ?string $parentAgentId,
        public readonly string $operation, // 'llm_call', 'tool_call', 'step'
        public readonly Usage $usage,
        public readonly array $context = [], // Additional context (step number, tool name, etc.)
    ) {
        $this->reportedAt = new DateTimeImmutable();

        parent::__construct([
            'agentId' => $this->agentId,
            'parentAgentId' => $this->parentAgentId,
            'operation' => $this->operation,
            'usage' => $this->usage->toArray(),
            'context' => $this->context,
        ]);
    }

    #[\Override]
    public function __toString(): string {
        $parentInfo = $this->parentAgentId ? " (parent={$this->parentAgentId})" : '';
        $contextInfo = $this->context !== [] ? ' ' . json_encode($this->context) : '';

        return sprintf(
            'Token usage [%s]: %s - %d tokens%s%s',
            substr($this->agentId, 0, 8),
            $this->operation,
            $this->usage->total(),
            $parentInfo,
            $contextInfo
        );
    }
}
