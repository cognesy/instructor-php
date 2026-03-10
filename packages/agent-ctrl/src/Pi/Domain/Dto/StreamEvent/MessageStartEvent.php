<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\Common\Value\Normalize;

/**
 * Event emitted when a message begins (user or assistant)
 *
 * Example: {"type":"message_start","message":{"role":"assistant","content":[],...}}
 */
final readonly class MessageStartEvent extends StreamEvent
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
        return 'message_start';
    }

    public function isAssistant(): bool
    {
        return $this->role === 'assistant';
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
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
