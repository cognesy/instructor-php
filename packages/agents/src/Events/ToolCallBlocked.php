<?php declare(strict_types=1);

namespace Cognesy\Agents\Events;

use DateTimeImmutable;
use Psr\Log\LogLevel;

/**
 * Dispatched when a tool call is blocked by a hook.
 * Use to track security enforcement and permission denials.
 */
final class ToolCallBlocked extends AgentEvent
{
    public string $logLevel = LogLevel::WARNING;
    public readonly DateTimeImmutable $blockedAt;

    public function __construct(
        public readonly string $agentId,
        public readonly string $executionId,
        public readonly ?string $parentAgentId,
        public readonly int $stepNumber,
        public readonly string $tool,
        public readonly array $args,
        public readonly string $reason,
        public readonly ?string $hookName = null,
    ) {
        $this->blockedAt = new DateTimeImmutable();

        parent::__construct([
            'agentId' => $this->agentId,
            'executionId' => $this->executionId,
            'parentAgentId' => $this->parentAgentId,
            'step' => $this->stepNumber,
            'tool' => $this->tool,
            'args' => $this->args,
            'reason' => $this->reason,
            'hook' => $this->hookName,
        ]);
    }

    #[\Override]
    public function __toString(): string {
        $hookInfo = $this->hookName ? " by hook '{$this->hookName}'" : '';

        return sprintf(
            'Tool call blocked: %s%s - %s',
            $this->tool,
            $hookInfo,
            $this->reason
        );
    }
}
