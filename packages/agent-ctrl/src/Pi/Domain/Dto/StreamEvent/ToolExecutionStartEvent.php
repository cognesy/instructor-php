<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\Common\Value\Normalize;

/**
 * Event emitted when a tool execution begins
 *
 * Example: {"type":"tool_execution_start","toolCallId":"...","toolName":"bash","args":{...}}
 */
final readonly class ToolExecutionStartEvent extends StreamEvent
{
    public function __construct(
        array $rawData,
        public string $toolCallId,
        public string $toolName,
        public array $args,
    ) {
        parent::__construct($rawData);
    }

    #[\Override]
    public function type(): string
    {
        return 'tool_execution_start';
    }

    public static function fromArray(array $data): self
    {
        $args = $data['args'] ?? [];

        return new self(
            rawData: $data,
            toolCallId: Normalize::toString($data['toolCallId'] ?? ''),
            toolName: Normalize::toString($data['toolName'] ?? ''),
            args: is_array($args) ? $args : [],
        );
    }
}
