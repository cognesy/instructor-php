<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Tools\Subagent;

final readonly class SubagentPolicy
{
    public function __construct(
        public int $maxDepth = 3,
        public int $summaryMaxChars = 8000,
    ) {}
}
