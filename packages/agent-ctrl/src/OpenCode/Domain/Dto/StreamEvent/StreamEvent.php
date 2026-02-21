<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenCode\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\OpenCode\Domain\ValueObject\OpenCodeSessionId;

/**
 * Base class for OpenCode stream events
 *
 * OpenCode emits nd-JSON events with types:
 * - step_start: Turn begins
 * - text: Text content from assistant
 * - tool_use: Tool invocation and result
 * - step_finish: Turn ends with usage stats
 * - error: Error occurred
 */
abstract readonly class StreamEvent
{
    public ?OpenCodeSessionId $sessionIdValue;

    public function __construct(
        public int $timestamp,
        public string $sessionId,
    ) {
        $this->sessionIdValue = $sessionId !== ''
            ? OpenCodeSessionId::fromString($sessionId)
            : null;
    }

    /**
     * Get the event type identifier
     */
    abstract public function type(): string;

    /**
     * Factory method to create appropriate event from raw data
     */
    public static function fromArray(array $data): self
    {
        $type = $data['type'] ?? 'unknown';
        $timestamp = $data['timestamp'] ?? 0;
        $sessionId = $data['sessionID'] ?? '';

        return match ($type) {
            'step_start' => StepStartEvent::fromArray($data),
            'text' => TextEvent::fromArray($data),
            'tool_use' => ToolUseEvent::fromArray($data),
            'step_finish' => StepFinishEvent::fromArray($data),
            'error' => ErrorEvent::fromArray($data),
            default => UnknownEvent::fromArray($data),
        };
    }
}
