<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\ClaudeCode\Domain\Dto\StreamEvent;

/**
 * Final result event from agent
 */
final readonly class ResultEvent extends StreamEvent
{
    public function __construct(
        public string $result,
    ) {}

    #[\Override]
    public function type(): string
    {
        return 'result';
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            result: $data['result'] ?? '',
        );
    }
}
