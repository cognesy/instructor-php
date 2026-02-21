<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\OpenAICodex\Domain\ValueObject\CodexThreadId;

/**
 * Event emitted when a new thread is started
 *
 * Example: {"type":"thread.started","thread_id":"0199a213-81c0-7800-8aa1-bbab2a035a53"}
 */
final readonly class ThreadStartedEvent extends StreamEvent
{
    public ?CodexThreadId $threadIdValue;

    public function __construct(
        public string $threadId,
    ) {
        $this->threadIdValue = $threadId !== ''
            ? CodexThreadId::fromString($threadId)
            : null;
    }

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
