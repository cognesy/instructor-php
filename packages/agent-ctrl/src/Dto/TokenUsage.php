<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Dto;

/**
 * Unified token usage across all agent types.
 */
final readonly class TokenUsage
{
    public function __construct(
        public int $input,
        public int $output,
        public ?int $cacheRead = null,
        public ?int $cacheWrite = null,
        public ?int $reasoning = null,
    ) {}

    public function total(): int
    {
        return $this->input + $this->output;
    }

    /**
     * Create from Codex UsageStats
     */
    public static function fromCodex(
        \Cognesy\AgentCtrl\OpenAICodex\Domain\Value\UsageStats $usage
    ): self {
        return new self(
            input: $usage->inputTokens,
            output: $usage->outputTokens,
            cacheRead: $usage->cachedInputTokens,
        );
    }

    /**
     * Create from OpenCode TokenUsage
     */
    public static function fromOpenCode(
        \Cognesy\AgentCtrl\OpenCode\Domain\Value\TokenUsage $usage
    ): self {
        return new self(
            input: $usage->input,
            output: $usage->output,
            cacheRead: $usage->cacheRead,
            cacheWrite: $usage->cacheWrite,
            reasoning: $usage->reasoning,
        );
    }
}
