<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenCode\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\Common\Value\Normalize;
use Cognesy\AgentCtrl\OpenCode\Domain\ValueObject\OpenCodeCallId;
use Cognesy\AgentCtrl\OpenCode\Domain\ValueObject\OpenCodeMessageId;
use Cognesy\AgentCtrl\OpenCode\Domain\ValueObject\OpenCodePartId;
use Cognesy\AgentCtrl\OpenCode\Domain\ValueObject\OpenCodeSessionId;

/**
 * Event emitted when a tool is invoked (includes result)
 *
 * OpenCode combines tool invocation and result in a single event.
 */
final readonly class ToolUseEvent extends StreamEvent
{
    private ?OpenCodeMessageId $messageId;
    private ?OpenCodePartId $partId;
    private ?OpenCodeCallId $callId;

    public function __construct(
        int $timestamp,
        OpenCodeSessionId|string|null $sessionId,
        OpenCodeMessageId|string|null $messageId,
        OpenCodePartId|string|null $partId,
        OpenCodeCallId|string|null $callId,
        public string $tool,
        public string $status,
        public array $input,
        public string $output,
        public ?string $title = null,
        public ?int $startTime = null,
        public ?int $endTime = null,
    ) {
        parent::__construct($timestamp, $sessionId);
        $this->messageId = match (true) {
            $messageId instanceof OpenCodeMessageId => $messageId,
            is_string($messageId) && $messageId !== '' => OpenCodeMessageId::fromString($messageId),
            default => null,
        };
        $this->partId = match (true) {
            $partId instanceof OpenCodePartId => $partId,
            is_string($partId) && $partId !== '' => OpenCodePartId::fromString($partId),
            default => null,
        };
        $this->callId = match (true) {
            $callId instanceof OpenCodeCallId => $callId,
            is_string($callId) && $callId !== '' => OpenCodeCallId::fromString($callId),
            default => null,
        };
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

    public function messageId(): ?OpenCodeMessageId
    {
        return $this->messageId;
    }

    public function partId(): ?OpenCodePartId
    {
        return $this->partId;
    }

    public function callId(): ?OpenCodeCallId
    {
        return $this->callId;
    }

    public static function fromArray(array $data): self
    {
        $part = Normalize::toArray($data['part'] ?? []);
        $state = Normalize::toArray($part['state'] ?? []);
        $time = Normalize::toArray($state['time'] ?? []);

        return new self(
            timestamp: Normalize::toInt($data['timestamp'] ?? 0),
            sessionId: Normalize::toString($data['sessionID'] ?? ''),
            messageId: Normalize::toString($part['messageID'] ?? ''),
            partId: Normalize::toString($part['id'] ?? ''),
            callId: Normalize::toString($part['callID'] ?? ''),
            tool: Normalize::toString($part['tool'] ?? ''),
            status: Normalize::toString($state['status'] ?? 'unknown', 'unknown'),
            input: Normalize::toArray($state['input'] ?? []),
            output: Normalize::toString($state['output'] ?? ''),
            title: Normalize::toNullableString($state['title'] ?? null),
            startTime: Normalize::toNullableInt($time['start'] ?? null),
            endTime: Normalize::toNullableInt($time['end'] ?? null),
        );
    }
}
