<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\ClaudeCodeCli\Domain\Dto\StreamEvent;

/**
 * Unknown content type - preserves raw data
 */
final readonly class UnknownContent extends MessageContent
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
