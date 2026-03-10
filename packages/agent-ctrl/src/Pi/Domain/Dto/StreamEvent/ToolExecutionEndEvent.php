<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\Common\Value\Normalize;

/**
 * Event emitted when a tool execution finishes
 *
 * Example: {"type":"tool_execution_end","toolCallId":"...","toolName":"bash","result":{...},"isError":false}
 */
final readonly class ToolExecutionEndEvent extends StreamEvent
{
    public function __construct(
        array $rawData,
        public string $toolCallId,
        public string $toolName,
        public mixed $result,
        public bool $isError,
    ) {
        parent::__construct($rawData);
    }

    #[\Override]
    public function type(): string
    {
        return 'tool_execution_end';
    }

    /**
     * Get result as string
     */
    public function resultAsString(): string
    {
        if (is_string($this->result)) {
            return $this->result;
        }
        if (is_array($this->result) || is_object($this->result)) {
            $encoded = json_encode($this->result);
            return is_string($encoded) ? $encoded : '';
        }
        return Normalize::toString($this->result);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            rawData: $data,
            toolCallId: Normalize::toString($data['toolCallId'] ?? ''),
            toolName: Normalize::toString($data['toolName'] ?? ''),
            result: $data['result'] ?? null,
            isError: Normalize::toBool($data['isError'] ?? false),
        );
    }
}
