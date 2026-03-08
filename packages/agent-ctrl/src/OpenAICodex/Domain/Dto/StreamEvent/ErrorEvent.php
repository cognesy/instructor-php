<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\Common\Value\Normalize;

/**
 * Event emitted when an error occurs
 *
 * Example: {"type":"error","message":"Authentication failed"}
 */
final readonly class ErrorEvent extends StreamEvent
{
    public function __construct(
        public string $message,
        public ?string $code = null,
    ) {}

    public function type(): string
    {
        return 'error';
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $message = $data['message'] ?? $data['error'] ?? 'Unknown error';
        $messageData = Normalize::toArray($message);

        return new self(
            message: is_string($message)
                ? $message
                : Normalize::toString($messageData['message'] ?? $message),
            code: Normalize::toNullableString($data['code'] ?? null),
        );
    }
}
