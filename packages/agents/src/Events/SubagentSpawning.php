<?php declare(strict_types=1);

namespace Cognesy\Agents\Events;

use DateTimeImmutable;

/**
 * Dispatched when a parent agent is about to spawn a subagent.
 * Use to track subagent orchestration and nesting depth.
 */
final class SubagentSpawning extends AgentEvent
{
    public readonly DateTimeImmutable $startedAt;

    public function __construct(
        public readonly string $parentAgentId,
        public readonly string $subagentName,
        public readonly string $prompt,
        public readonly int $depth,
        public readonly int $maxDepth,
        public readonly ?string $parentExecutionId = null,
        public readonly ?int $parentStepNumber = null,
        public readonly ?string $toolCallId = null,
    ) {
        $this->startedAt = new DateTimeImmutable();

        parent::__construct([
            'parentAgentId' => $this->parentAgentId,
            'parentExecutionId' => $this->parentExecutionId,
            'parentStepNumber' => $this->parentStepNumber,
            'toolCallId' => $this->toolCallId,
            'subagent' => $this->subagentName,
            'prompt' => mb_substr($this->prompt, 0, 100) . (mb_strlen($this->prompt) > 100 ? '...' : ''),
            'depth' => $this->depth,
            'maxDepth' => $this->maxDepth,
        ]);
    }

    #[\Override]
    public function __toString(): string {
        return sprintf(
            'Agent [%s] spawning subagent "%s" [depth=%d/%d]',
            substr($this->parentAgentId, 0, 8),
            $this->subagentName,
            $this->depth + 1,
            $this->maxDepth
        );
    }
}
