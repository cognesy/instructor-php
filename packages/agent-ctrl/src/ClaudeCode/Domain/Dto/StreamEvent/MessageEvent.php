<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\ClaudeCode\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\Common\Value\Normalize;

/**
 * Stream event containing a message
 */
final readonly class MessageEvent extends StreamEvent
{
    public function __construct(
        public Message $message,
    ) {}

    #[\Override]
    public function type(): string
    {
        return 'stream_event';
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $messageData = Normalize::toArray($data['message'] ?? []);

        return new self(
            message: Message::fromArray($messageData),
        );
    }
}
