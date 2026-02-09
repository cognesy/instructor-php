<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Events;

use DateTimeImmutable;

/**
 * Dispatched when a tool call begins execution.
 * Use for tracking individual tool invocation timing and debugging tool usage.
 */
final class ToolCallStarted extends AgentEvent
{
    public function __construct(
        public readonly string $tool,
        public readonly mixed $args,
        public readonly DateTimeImmutable $startedAt,
    ) {
        parent::__construct([
            'tool' => $this->tool,
            'args' => $this->args,
            'at' => $this->startedAt->format(DATE_ATOM),
        ]);
    }

    #[\Override]
    public function __toString(): string {
        $argsPreview = is_array($this->args)
            ? implode(', ', array_keys($this->args))
            : (is_string($this->args) ? substr($this->args, 0, 50) : 'complex');

        return sprintf(
            'Tool call started: %s(%s)',
            $this->tool,
            $argsPreview
        );
    }
}
