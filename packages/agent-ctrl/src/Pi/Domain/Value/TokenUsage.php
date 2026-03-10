<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Pi\Domain\Value;

use Cognesy\AgentCtrl\Common\Value\Normalize;

/**
 * Pi-specific token usage data
 *
 * Pi usage format (from message.usage):
 * {"input":3,"output":5,"cacheRead":0,"cacheWrite":4625,"totalTokens":4633,
 *  "cost":{"input":0.000015,"output":0.000125,"cacheRead":0,"cacheWrite":0.028906,"total":0.029046}}
 */
final readonly class TokenUsage
{
    public function __construct(
        public int $input,
        public int $output,
        public int $cacheRead = 0,
        public int $cacheWrite = 0,
        public int $totalTokens = 0,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            input: Normalize::toInt($data['input'] ?? 0),
            output: Normalize::toInt($data['output'] ?? 0),
            cacheRead: Normalize::toInt($data['cacheRead'] ?? 0),
            cacheWrite: Normalize::toInt($data['cacheWrite'] ?? 0),
            totalTokens: Normalize::toInt($data['totalTokens'] ?? 0),
        );
    }
}
