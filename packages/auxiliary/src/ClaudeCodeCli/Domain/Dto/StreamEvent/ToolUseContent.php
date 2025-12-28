<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\ClaudeCodeCli\Domain\Dto\StreamEvent;

/**
 * Tool use request from the agent
 */
final readonly class ToolUseContent extends MessageContent
{
    /**
     * @param array<string, mixed> $input
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $input,
    ) {}

    #[\Override]
    public function type(): string
    {
        return 'tool_use';
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            name: $data['name'] ?? 'unknown',
            input: $data['input'] ?? [],
        );
    }
}
