<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\StreamEvent;

/**
 * Event emitted when a new thread is started
 *
 * Example: {"type":"thread.started","thread_id":"0199a213-81c0-7800-8aa1-bbab2a035a53"}
 */
final readonly class ThreadStartedEvent extends StreamEvent
{
    public function __construct(
        public string $threadId,
    ) {}

    public function type(): string
    {
        return 'thread.started';
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            threadId: (string)($data['thread_id'] ?? ''),
        );
    }
}
