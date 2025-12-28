<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Agents\ClaudeCode\Domain\Dto\StreamEvent;

/**
 * System event (internal Claude CLI event)
 */
final readonly class SystemEvent extends StreamEvent
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public array $data,
    ) {}

    #[\Override]
    public function type(): string
    {
        return 'system';
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(data: $data);
    }
}
