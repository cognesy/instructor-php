<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Subagent;

final readonly class SubagentPolicy
{
    public function __construct(
        public int $maxDepth = 3,
        public int $summaryMaxChars = 8000,
    ) {}

    public static function default(): self {
        return new self();
    }
}
