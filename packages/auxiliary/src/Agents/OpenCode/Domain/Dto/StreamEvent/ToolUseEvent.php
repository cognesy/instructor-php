<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Agents\OpenCode\Domain\Dto\StreamEvent;

/**
 * Event emitted when a tool is invoked (includes result)
 *
 * OpenCode combines tool invocation and result in a single event.
 */
final readonly class ToolUseEvent extends StreamEvent
{
    public function __construct(
        int $timestamp,
        string $sessionId,
        public string $messageId,
        public string $partId,
        public string $callId,
        public string $tool,
        public string $status,
        public array $input,
        public string $output,
        public ?string $title = null,
        public ?int $startTime = null,
        public ?int $endTime = null,
    ) {
        parent::__construct($timestamp, $sessionId);
    }

    #[\Override]
    public function type(): string
    {
        return 'tool_use';
    }

    /**
     * Check if tool execution completed successfully
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if tool execution failed
     */
    public function isError(): bool
    {
        return $this->status === 'error';
    }

    public static function fromArray(array $data): self
    {
        $part = $data['part'] ?? [];
        $state = $part['state'] ?? [];
        $time = $state['time'] ?? [];
        $metadata = $state['metadata'] ?? [];

        return new self(
            timestamp: $data['timestamp'] ?? 0,
            sessionId: $data['sessionID'] ?? '',
            messageId: $part['messageID'] ?? '',
            partId: $part['id'] ?? '',
            callId: $part['callID'] ?? '',
            tool: $part['tool'] ?? '',
            status: $state['status'] ?? 'unknown',
            input: $state['input'] ?? [],
            output: $state['output'] ?? '',
            title: $state['title'] ?? null,
            startTime: $time['start'] ?? null,
            endTime: $time['end'] ?? null,
        );
    }
}
