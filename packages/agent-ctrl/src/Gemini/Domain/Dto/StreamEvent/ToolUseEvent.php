<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Gemini\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\Common\Value\Normalize;

/**
 * Tool use event — tool invocation request
 *
 * Example: {"type":"tool_use","timestamp":"...","tool_name":"read_file","tool_id":"call_123","parameters":{"path":"file.txt"}}
 */
final readonly class ToolUseEvent extends StreamEvent
{
    public function __construct(
        array $rawData,
        public string $toolName,
        public string $toolId,
        public array $parameters,
        public string $timestamp,
    ) {
        parent::__construct($rawData);
    }

    #[\Override]
    public function type(): string
    {
        return 'tool_use';
    }

    public static function fromArray(array $data): self
    {
        return new self(
            rawData: $data,
            toolName: Normalize::toString($data['tool_name'] ?? ''),
            toolId: Normalize::toString($data['tool_id'] ?? ''),
            parameters: Normalize::toArray($data['parameters'] ?? []),
            timestamp: Normalize::toString($data['timestamp'] ?? ''),
        );
    }
}
