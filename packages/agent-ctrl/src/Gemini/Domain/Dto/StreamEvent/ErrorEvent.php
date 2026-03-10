<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Gemini\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\Common\Value\Normalize;

/**
 * Error event — warning or error during execution
 *
 * Example: {"type":"error","timestamp":"...","severity":"error","message":"Something went wrong"}
 */
final readonly class ErrorEvent extends StreamEvent
{
    public function __construct(
        array $rawData,
        public string $severity,
        public string $message,
        public string $timestamp,
    ) {
        parent::__construct($rawData);
    }

    #[\Override]
    public function type(): string
    {
        return 'error';
    }

    public function isWarning(): bool
    {
        return $this->severity === 'warning';
    }

    public function isError(): bool
    {
        return $this->severity === 'error';
    }

    public static function fromArray(array $data): self
    {
        return new self(
            rawData: $data,
            severity: Normalize::toString($data['severity'] ?? 'error', 'error'),
            message: Normalize::toString($data['message'] ?? 'Unknown error', 'Unknown error'),
            timestamp: Normalize::toString($data['timestamp'] ?? ''),
        );
    }
}
