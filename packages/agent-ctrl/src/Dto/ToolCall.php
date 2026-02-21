<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Dto;

use Cognesy\AgentCtrl\ValueObject\AgentToolCallId;

/**
 * Unified tool call representation across all agent types.
 */
final readonly class ToolCall
{
    public ?string $callId;
    public ?AgentToolCallId $callIdValue;

    public function __construct(
        public string $tool,
        public array $input,
        public ?string $output = null,
        AgentToolCallId|string|null $callId = null,
        public bool $isError = false,
    ) {
        $this->callIdValue = match (true) {
            $callId instanceof AgentToolCallId => $callId,
            is_string($callId) && $callId !== '' => AgentToolCallId::fromString($callId),
            default => null,
        };
        $this->callId = $this->callIdValue?->toString();
    }

    public function isCompleted(): bool
    {
        return $this->output !== null;
    }
}
