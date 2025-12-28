<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Agents\OpenAICodex\Domain\Value;

/**
 * Token usage statistics from Codex execution
 */
final readonly class UsageStats
{
    public function __construct(
        public int $inputTokens,
        public int $cachedInputTokens,
        public int $outputTokens,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            inputTokens: (int)($data['input_tokens'] ?? 0),
            cachedInputTokens: (int)($data['cached_input_tokens'] ?? 0),
            outputTokens: (int)($data['output_tokens'] ?? 0),
        );
    }

    public function totalInputTokens(): int
    {
        return $this->inputTokens;
    }

    public function billableInputTokens(): int
    {
        return $this->inputTokens - $this->cachedInputTokens;
    }
}
