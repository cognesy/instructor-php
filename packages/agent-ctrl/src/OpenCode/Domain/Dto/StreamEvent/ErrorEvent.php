<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenCode\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\Common\Value\Normalize;
use Cognesy\AgentCtrl\OpenCode\Domain\ValueObject\OpenCodeSessionId;

/**
 * Event emitted when an error occurs
 */
final readonly class ErrorEvent extends StreamEvent
{
    public function __construct(
        int $timestamp,
        OpenCodeSessionId|string|null $sessionId,
        public string $message,
        public ?string $code = null,
        public array $rawData = [],
    ) {
        parent::__construct($timestamp, $sessionId);
    }

    #[\Override]
    public function type(): string
    {
        return 'error';
    }

    public static function fromArray(array $data): self
    {
        $part = Normalize::toArray($data['part'] ?? []);
        $error = $part['error'] ?? $data['error'] ?? [];
        $errorData = Normalize::toArray($error);

        return new self(
            timestamp: Normalize::toInt($data['timestamp'] ?? 0),
            sessionId: Normalize::toString($data['sessionID'] ?? ''),
            message: is_string($error)
                ? $error
                : Normalize::toString($errorData['message'] ?? 'Unknown error', 'Unknown error'),
            code: Normalize::toNullableString($errorData['code'] ?? null),
            rawData: $data,
        );
    }
}
