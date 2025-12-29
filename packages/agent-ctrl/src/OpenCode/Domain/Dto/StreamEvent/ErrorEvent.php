<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenCode\Domain\Dto\StreamEvent;

/**
 * Event emitted when an error occurs
 */
final readonly class ErrorEvent extends StreamEvent
{
    public function __construct(
        int $timestamp,
        string $sessionId,
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
        $part = $data['part'] ?? [];
        $error = $part['error'] ?? $data['error'] ?? [];

        return new self(
            timestamp: $data['timestamp'] ?? 0,
            sessionId: $data['sessionID'] ?? '',
            message: is_string($error) ? $error : ($error['message'] ?? 'Unknown error'),
            code: $error['code'] ?? null,
            rawData: $data,
        );
    }
}
