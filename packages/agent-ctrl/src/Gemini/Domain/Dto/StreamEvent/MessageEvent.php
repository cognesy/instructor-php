<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Gemini\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\Common\Value\Normalize;

/**
 * Message event — user or assistant text
 *
 * Assistant messages with delta=true are streaming chunks.
 * User messages are emitted once with full content.
 *
 * Example: {"type":"message","timestamp":"...","role":"assistant","content":"Hello","delta":true}
 */
final readonly class MessageEvent extends StreamEvent
{
    public function __construct(
        array $rawData,
        public string $role,
        public string $content,
        public bool $delta,
        public string $timestamp,
    ) {
        parent::__construct($rawData);
    }

    #[\Override]
    public function type(): string
    {
        return 'message';
    }

    public function isAssistant(): bool
    {
        return $this->role === 'assistant';
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function isDelta(): bool
    {
        return $this->delta;
    }

    /**
     * Get the text delta content (for streaming assistant messages)
     */
    public function textDelta(): ?string
    {
        if ($this->isAssistant() && $this->isDelta()) {
            return $this->content;
        }
        return null;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            rawData: $data,
            role: Normalize::toString($data['role'] ?? ''),
            content: Normalize::toString($data['content'] ?? ''),
            delta: Normalize::toBool($data['delta'] ?? false),
            timestamp: Normalize::toString($data['timestamp'] ?? ''),
        );
    }
}
