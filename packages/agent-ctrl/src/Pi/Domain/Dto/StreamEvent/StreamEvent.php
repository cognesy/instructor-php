<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\Common\Value\Normalize;

/**
 * Base class for Pi stream events
 *
 * Pi emits JSONL events with types:
 * - session: Session header with version, id, cwd
 * - agent_start: Agent begins processing
 * - agent_end: Agent finished with all messages
 * - turn_start: New turn begins
 * - turn_end: Turn finished with message and tool results
 * - message_start: Message begins (user or assistant)
 * - message_update: Streaming delta (text_start, text_delta, text_end)
 * - message_end: Message complete with final content and usage
 * - tool_execution_start: Tool call begins
 * - tool_execution_end: Tool call finished with result
 */
abstract readonly class StreamEvent
{
    public function __construct(
        public array $rawData,
    ) {}

    /**
     * Get the event type identifier
     */
    abstract public function type(): string;

    /**
     * Factory method to create appropriate event from raw data
     */
    public static function fromArray(array $data): self
    {
        $type = Normalize::toString($data['type'] ?? 'unknown', 'unknown');

        return match ($type) {
            'session' => SessionEvent::fromArray($data),
            'agent_start' => AgentStartEvent::fromArray($data),
            'agent_end' => AgentEndEvent::fromArray($data),
            'turn_start' => TurnStartEvent::fromArray($data),
            'turn_end' => TurnEndEvent::fromArray($data),
            'message_start' => MessageStartEvent::fromArray($data),
            'message_update' => MessageUpdateEvent::fromArray($data),
            'message_end' => MessageEndEvent::fromArray($data),
            'tool_execution_start' => ToolExecutionStartEvent::fromArray($data),
            'tool_execution_end' => ToolExecutionEndEvent::fromArray($data),
            'error' => ErrorEvent::fromArray($data),
            default => UnknownEvent::fromArray($data),
        };
    }
}
