<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Agents\ClaudeCode\Domain\Dto\StreamEvent;

/**
 * Base class for message content items
 */
abstract readonly class MessageContent
{
    abstract public function type(): string;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $type = $data['type'] ?? 'unknown';

        return match ($type) {
            'text' => TextContent::fromArray($data),
            'tool_use' => ToolUseContent::fromArray($data),
            'tool_result' => ToolResultContent::fromArray($data),
            default => UnknownContent::fromArray($data),
        };
    }
}
