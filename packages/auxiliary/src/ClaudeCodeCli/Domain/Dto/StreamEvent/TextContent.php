<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\ClaudeCodeCli\Domain\Dto\StreamEvent;

/**
 * Text content from the agent
 */
final readonly class TextContent extends MessageContent
{
    public function __construct(
        public string $text,
    ) {}

    #[\Override]
    public function type(): string
    {
        return 'text';
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            text: $data['text'] ?? '',
        );
    }
}
