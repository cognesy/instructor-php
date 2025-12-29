<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Dto;

/**
 * Unified tool call representation across all agent types.
 */
final readonly class ToolCall
{
    public function __construct(
        public string $tool,
        public array $input,
        public ?string $output = null,
        public ?string $callId = null,
        public bool $isError = false,
    ) {}

    public function isCompleted(): bool
    {
        return $this->output !== null;
    }
}
