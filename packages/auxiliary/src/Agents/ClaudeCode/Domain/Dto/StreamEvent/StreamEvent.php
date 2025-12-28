<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Agents\ClaudeCode\Domain\Dto\StreamEvent;

/**
 * Base class for all streaming events from Claude CLI
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
            'stream_event', 'assistant', 'user' => MessageEvent::fromArray($data),
            'result' => ResultEvent::fromArray($data),
            'error' => ErrorEvent::fromArray($data),
            'system' => SystemEvent::fromArray($data),
            default => UnknownEvent::fromArray($data),
        };
    }
}
