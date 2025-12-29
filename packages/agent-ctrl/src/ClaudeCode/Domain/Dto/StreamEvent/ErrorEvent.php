<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\ClaudeCode\Domain\Dto\StreamEvent;

/**
 * Error event from agent
 */
final readonly class ErrorEvent extends StreamEvent
{
    public function __construct(
        public string $error,
    ) {}

    #[\Override]
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
            error: $data['error'] ?? '',
        );
    }
}
