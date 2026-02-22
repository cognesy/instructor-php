<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Dto;

use Cognesy\AgentCtrl\ValueObject\AgentToolCallId;

/**
 * Unified tool call representation across all agent types.
 */
final readonly class ToolCall
{
    private ?AgentToolCallId $callId;

    public function __construct(
        public string $tool,
        public array $input,
        public ?string $output = null,
        AgentToolCallId|string|null $callId = null,
        public bool $isError = false,
    ) {
        $this->callId = match (true) {
            $callId instanceof AgentToolCallId => $callId,
            is_string($callId) && $callId !== '' => AgentToolCallId::fromString($callId),
            default => null,
        };
    }

    public function callId(): ?AgentToolCallId
    {
        return $this->callId;
    }

    public function isCompleted(): bool
    {
        return $this->output !== null;
    }
}
