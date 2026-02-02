<?php declare(strict_types=1);

namespace Cognesy\Agents\Events;

use DateTimeImmutable;

/**
 * Dispatched when a tool call is blocked by a hook.
 * Use to track security enforcement and permission denials.
 */
final class ToolCallBlocked extends AgentEvent
{
    public readonly DateTimeImmutable $blockedAt;

    public function __construct(
        public readonly string $tool,
        public readonly array $args,
        public readonly string $reason,
        public readonly ?string $hookName = null,
    ) {
        $this->blockedAt = new DateTimeImmutable();

        parent::__construct([
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
