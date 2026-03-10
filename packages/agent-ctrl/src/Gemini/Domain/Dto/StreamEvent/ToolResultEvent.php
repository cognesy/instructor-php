<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Gemini\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\Common\Value\Normalize;

/**
 * Tool result event — tool execution result
 *
 * Example: {"type":"tool_result","timestamp":"...","tool_id":"call_123","status":"success","output":"file contents"}
 */
final readonly class ToolResultEvent extends StreamEvent
{
    public function __construct(
        array $rawData,
        public string $toolId,
        public string $status,
        public ?string $output,
        public ?array $error,
        public string $timestamp,
    ) {
        parent::__construct($rawData);
    }

    #[\Override]
    public function type(): string
    {
        return 'tool_result';
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isError(): bool
    {
        return $this->status === 'error';
    }

    public function resultAsString(): string
    {
        if ($this->output !== null) {
            return $this->output;
        }
        if ($this->error !== null) {
            return Normalize::toString($this->error['message'] ?? 'Unknown error');
        }
        return '';
    }

    public static function fromArray(array $data): self
    {
        $error = $data['error'] ?? null;

        return new self(
            rawData: $data,
            toolId: Normalize::toString($data['tool_id'] ?? ''),
            status: Normalize::toString($data['status'] ?? ''),
            output: Normalize::toNullableString($data['output'] ?? null),
            error: is_array($error) ? $error : null,
            timestamp: Normalize::toString($data['timestamp'] ?? ''),
        );
    }
}
