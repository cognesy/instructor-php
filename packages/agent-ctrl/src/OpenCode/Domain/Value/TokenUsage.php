<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenCode\Domain\Value;

/**
 * Token usage statistics from OpenCode step_finish event
 */
final readonly class TokenUsage
{
    public function __construct(
        public int $input,
        public int $output,
        public int $reasoning = 0,
        public int $cacheRead = 0,
        public int $cacheWrite = 0,
    ) {}

    /**
     * Create from step_finish tokens array
     *
     * Expected format:
     * {"input": 13998, "output": 7, "reasoning": 0, "cache": {"read": 0, "write": 0}}
     */
    public static function fromArray(array $data): self
    {
        $cache = $data['cache'] ?? [];
        return new self(
            input: $data['input'] ?? 0,
            output: $data['output'] ?? 0,
            reasoning: $data['reasoning'] ?? 0,
            cacheRead: $cache['read'] ?? 0,
            cacheWrite: $cache['write'] ?? 0,
        );
    }

    /**
     * Total tokens used (excluding cache)
     */
    public function total(): int
    {
        return $this->input + $this->output + $this->reasoning;
    }

    /**
     * Total tokens including reasoning
     */
    public function totalWithReasoning(): int
    {
        return $this->input + $this->output + $this->reasoning;
    }
}
