<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\Common\Value\Normalize;

/**
 * Event emitted when a message is being streamed
 *
 * Contains assistantMessageEvent with sub-types:
 * - text_start: Text block begins
 * - text_delta: Incremental text content
 * - text_end: Text block complete with full content
 *
 * Example: {"type":"message_update","assistantMessageEvent":{"type":"text_delta","delta":"Hello"},"message":{...}}
 */
final readonly class MessageUpdateEvent extends StreamEvent
{
    public function __construct(
        array $rawData,
        public string $eventType,
        public ?string $delta,
        public ?string $content,
        public ?int $contentIndex,
        public array $message,
        public array $assistantMessageEvent,
    ) {
        parent::__construct($rawData);
    }

    #[\Override]
    public function type(): string
    {
        return 'message_update';
    }

    /**
     * Get the text delta (for text_delta events)
     */
    public function textDelta(): ?string
    {
        return $this->delta;
    }

    /**
     * Check if this is a text delta event
     */
    public function isTextDelta(): bool
    {
        return $this->eventType === 'text_delta';
    }

    /**
     * Check if this is a text start event
     */
    public function isTextStart(): bool
    {
        return $this->eventType === 'text_start';
    }

    /**
     * Check if this is a text end event
     */
    public function isTextEnd(): bool
    {
        return $this->eventType === 'text_end';
    }

    public static function fromArray(array $data): self
    {
        $ame = Normalize::toArray($data['assistantMessageEvent'] ?? []);

        return new self(
            rawData: $data,
            eventType: Normalize::toString($ame['type'] ?? ''),
            delta: Normalize::toNullableString($ame['delta'] ?? null),
            content: Normalize::toNullableString($ame['content'] ?? null),
            contentIndex: Normalize::toNullableInt($ame['contentIndex'] ?? null),
            message: Normalize::toArray($data['message'] ?? []),
            assistantMessageEvent: $ame,
        );
    }
}
