<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Subagent;

final readonly class SubagentPolicy
{
    public function __construct(
        public int $maxDepth = 3,
    ) {}

    public static function default(): self {
        return new self();
    }
}
