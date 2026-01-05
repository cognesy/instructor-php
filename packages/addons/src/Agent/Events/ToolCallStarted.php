<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Events;

use DateTimeImmutable;

/**
 * Dispatched when a tool call begins execution.
 * Use for tracking individual tool invocation timing and debugging tool usage.
 */
final class ToolCallStarted extends AgentEvent
{
    public function __construct(
        public readonly string $toolName,
        public readonly mixed $toolArgs,
        public readonly DateTimeImmutable $startedAt,
    ) {
        parent::__construct([
            'tool' => $this->toolName,
            'args' => $this->toolArgs,
            'at' => $this->startedAt->format(DATE_ATOM),
        ]);
    }

    #[\Override]
    public function __toString(): string {
        $argsPreview = is_array($this->toolArgs)
            ? implode(', ', array_keys($this->toolArgs))
            : (is_string($this->toolArgs) ? substr($this->toolArgs, 0, 50) : 'complex');

        return sprintf(
            'Tool call started: %s(%s)',
            $this->toolName,
            $argsPreview
        );
    }
}

