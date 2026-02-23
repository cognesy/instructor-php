<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Dto;

use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\AgentCtrl\ValueObject\AgentSessionId;

/**
 * Common response format for all CLI-based code agents.
 */
final readonly class AgentResponse
{
    private ?AgentSessionId $sessionId;
    /** @var list<string> */
    private array $parseFailureSamples;

    /**
     * @param AgentType $agentType The agent type that produced this response
     * @param string $text The main text content from the agent
     * @param int $exitCode Process exit code (0 = success)
     * @param AgentSessionId|string|null $sessionId Session identifier for resume capability
     * @param TokenUsage|null $usage Token usage statistics
     * @param float|null $cost Cost in USD
     * @param list<ToolCall> $toolCalls Tool calls made during execution
     * @param mixed $rawResponse The original bridge-specific response
     * @param int $parseFailures Number of malformed JSON payloads skipped during parsing
     * @param list<string> $parseFailureSamples Sample malformed payload lines
     */
    public function __construct(
        public AgentType $agentType,
        public string $text,
        public int $exitCode,
        AgentSessionId|string|null $sessionId = null,
        public ?TokenUsage $usage = null,
        public ?float $cost = null,
        public array $toolCalls = [],
        public mixed $rawResponse = null,
        public int $parseFailures = 0,
        array $parseFailureSamples = [],
    ) {
        $this->sessionId = match (true) {
            $sessionId instanceof AgentSessionId => $sessionId,
            is_string($sessionId) && $sessionId !== '' => AgentSessionId::fromString($sessionId),
            default => null,
        };
        $this->parseFailureSamples = array_values($parseFailureSamples);
    }

    public function sessionId(): ?AgentSessionId
    {
        return $this->sessionId;
    }

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

    public function parseFailures(): int
    {
        return $this->parseFailures;
    }

    /**
     * @return list<string>
     */
    public function parseFailureSamples(): array
    {
        return $this->parseFailureSamples;
    }
}
