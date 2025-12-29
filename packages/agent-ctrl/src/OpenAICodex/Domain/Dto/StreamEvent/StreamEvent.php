<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\StreamEvent;

/**
 * Base class for all streaming events from Codex CLI
 *
 * Event types:
 * - thread.started - Contains thread_id
 * - turn.started - Marks turn start
 * - turn.completed - Contains usage stats
 * - turn.failed - Contains error details
 * - item.started - Contains item with type, id, status
 * - item.completed - Contains completed item with content
 * - error - Contains error message
 */
abstract readonly class StreamEvent
{
    abstract public function type(): string;

    /**
     * Create appropriate StreamEvent subclass from raw data
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $type = $data['type'] ?? 'unknown';

        return match ($type) {
            'thread.started' => ThreadStartedEvent::fromArray($data),
            'turn.started' => TurnStartedEvent::fromArray($data),
            'turn.completed' => TurnCompletedEvent::fromArray($data),
            'turn.failed' => TurnFailedEvent::fromArray($data),
            'item.started' => ItemStartedEvent::fromArray($data),
            'item.completed' => ItemCompletedEvent::fromArray($data),
            'error' => ErrorEvent::fromArray($data),
            default => UnknownEvent::fromArray($data),
        };
    }
}
