<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Gemini\Domain\Value;

use Cognesy\AgentCtrl\Common\Value\Normalize;

/**
 * Gemini-specific token usage data
 *
 * Gemini usage format (from result event stats):
 * {"total_tokens":100,"input_tokens":40,"output_tokens":60,"cached":0,"input":40,"duration_ms":1234,"tool_calls":2}
 */
final readonly class TokenUsage
{
    public function __construct(
        public int $input,
        public int $output,
        public int $cached = 0,
        public int $totalTokens = 0,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            input: Normalize::toInt($data['input_tokens'] ?? $data['input'] ?? 0),
            output: Normalize::toInt($data['output_tokens'] ?? 0),
            cached: Normalize::toInt($data['cached'] ?? 0),
            totalTokens: Normalize::toInt($data['total_tokens'] ?? 0),
        );
    }
}
