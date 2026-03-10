<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Gemini\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\Common\Value\Normalize;

/**
 * Base class for Gemini stream-json events
 *
 * Gemini emits JSONL events with types:
 * - init: Session start with session_id, model
 * - message: User or assistant text (delta=true for streaming chunks)
 * - tool_use: Tool invocation request
 * - tool_result: Tool execution result
 * - error: Warning or error during execution
 * - result: Final event with stats and status
 */
abstract readonly class StreamEvent
{
    public function __construct(
        public array $rawData,
    ) {}

    abstract public function type(): string;

    public static function fromArray(array $data): self
    {
        $type = Normalize::toString($data['type'] ?? 'unknown', 'unknown');

        return match ($type) {
            'init' => InitEvent::fromArray($data),
            'message' => MessageEvent::fromArray($data),
            'tool_use' => ToolUseEvent::fromArray($data),
            'tool_result' => ToolResultEvent::fromArray($data),
            'error' => ErrorEvent::fromArray($data),
            'result' => ResultEvent::fromArray($data),
            default => UnknownEvent::fromArray($data),
        };
    }
}
