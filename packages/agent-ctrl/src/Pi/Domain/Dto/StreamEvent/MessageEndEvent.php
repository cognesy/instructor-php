<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\Common\Value\Normalize;

/**
 * Event emitted when a message is complete
 *
 * Contains the final message with full content and usage stats.
 *
 * Example: {"type":"message_end","message":{"role":"assistant","content":[...],"usage":{...}}}
 */
final readonly class MessageEndEvent extends StreamEvent
{
    public function __construct(
        array $rawData,
        public string $role,
        public array $message,
    ) {
        parent::__construct($rawData);
    }

    #[\Override]
    public function type(): string
    {
        return 'message_end';
    }

    public function isAssistant(): bool
    {
        return $this->role === 'assistant';
    }

    /**
     * Extract text content from the message
     */
    public function text(): string
    {
        $text = '';
        foreach (Normalize::toArray($this->message['content'] ?? []) as $part) {
            if (($part['type'] ?? '') === 'text') {
                $text .= Normalize::toString($part['text'] ?? '');
            }
        }
        return $text;
    }

    /**
     * Extract usage data from the message
     */
    public function usage(): ?array
    {
        $usage = $this->message['usage'] ?? null;
        return is_array($usage) ? $usage : null;
    }

    public static function fromArray(array $data): self
    {
        $message = Normalize::toArray($data['message'] ?? []);

        return new self(
            rawData: $data,
            role: Normalize::toString($message['role'] ?? ''),
            message: $message,
        );
    }
}
