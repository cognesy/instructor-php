<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\Common\Value\Normalize;

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
        $error = $data['error'] ?? 'Unknown error';
        $errorData = Normalize::toArray($error);

        return new self(
            error: is_string($error)
                ? $error
                : Normalize::toString($errorData['message'] ?? $error, 'Unknown error'),
            code: Normalize::toNullableString($data['code'] ?? null),
        );
    }
}
