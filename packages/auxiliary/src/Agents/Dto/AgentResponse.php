<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Agents\Dto;

use Cognesy\Auxiliary\Agents\Enum\AgentType;

/**
 * Common response format for all CLI-based code agents.
 */
final readonly class AgentResponse
{
    /**
     * @param AgentType $agentType The agent type that produced this response
     * @param string $text The main text content from the agent
     * @param int $exitCode Process exit code (0 = success)
     * @param string|null $sessionId Session identifier for resume capability
     * @param TokenUsage|null $usage Token usage statistics
     * @param float|null $cost Cost in USD
     * @param list<ToolCall> $toolCalls Tool calls made during execution
     * @param mixed $rawResponse The original bridge-specific response
     */
    public function __construct(
        public AgentType $agentType,
        public string $text,
        public int $exitCode,
        public ?string $sessionId = null,
        public ?TokenUsage $usage = null,
        public ?float $cost = null,
        public array $toolCalls = [],
        public mixed $rawResponse = null,
    ) {}

    public function isSuccess(): bool
    {
        return $this->exitCode === 0;
    }

    public function text(): string
    {
        return $this->text;
    }

    public function usage(): ?TokenUsage
    {
        return $this->usage;
    }

    public function cost(): ?float
    {
        return $this->cost;
    }
}
