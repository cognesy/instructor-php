<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\ClaudeCode\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\Common\Value\Normalize;

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
            id: Normalize::toString($data['id'] ?? ''),
            name: Normalize::toString($data['name'] ?? 'unknown', 'unknown'),
            input: Normalize::toArray($data['input'] ?? []),
        );
    }
}
