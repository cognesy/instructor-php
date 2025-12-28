<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Agents\OpenAICodex\Domain\Dto\StreamEvent;

/**
 * Event emitted when a turn fails
 *
 * Example: {"type":"turn.failed","error":"Rate limit exceeded"}
 */
final readonly class TurnFailedEvent extends StreamEvent
{
    public function __construct(
        public string $error,
        public ?string $code = null,
    ) {}

    public function type(): string
    {
        return 'turn.failed';
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            error: (string)($data['error'] ?? 'Unknown error'),
            code: isset($data['code']) ? (string)$data['code'] : null,
        );
    }
}
