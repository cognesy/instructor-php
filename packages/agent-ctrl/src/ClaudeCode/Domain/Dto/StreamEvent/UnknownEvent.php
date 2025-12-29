<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\ClaudeCode\Domain\Dto\StreamEvent;

/**
 * Unknown event type - preserves raw data
 */
final readonly class UnknownEvent extends StreamEvent
{
    /**
     * @param array<string, mixed> $rawData
     */
    public function __construct(
        public array $rawData,
    ) {}

    #[\Override]
    public function type(): string
    {
        return $this->rawData['type'] ?? 'unknown';
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(rawData: $data);
    }
}
