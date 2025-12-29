<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\StreamEvent;

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
        return new self(
            message: (string)($data['message'] ?? $data['error'] ?? 'Unknown error'),
            code: isset($data['code']) ? (string)$data['code'] : null,
        );
    }
}
