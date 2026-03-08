<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\ClaudeCode\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\Common\Value\Normalize;

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
        $type = Normalize::toString($data['type'] ?? 'unknown', 'unknown');

        return match ($type) {
            'text' => TextContent::fromArray($data),
            'tool_use' => ToolUseContent::fromArray($data),
            'tool_result' => ToolResultContent::fromArray($data),
            default => UnknownContent::fromArray($data),
        };
    }
}
