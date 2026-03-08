<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\ClaudeCode\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\Common\Value\Normalize;

/**
 * Tool execution result
 */
final readonly class ToolResultContent extends MessageContent
{
    public function __construct(
        public string $toolUseId,
        public string $content,
        public bool $isError,
    ) {}

    #[\Override]
    public function type(): string
    {
        return 'tool_result';
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            toolUseId: Normalize::toString($data['tool_use_id'] ?? ''),
            content: Normalize::toString($data['content'] ?? ''),
            isError: Normalize::toBool($data['is_error'] ?? false),
        );
    }
}
