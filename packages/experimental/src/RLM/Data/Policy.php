<?php declare(strict_types=1);

namespace Cognesy\Experimental\RLM\Data;

final readonly class Policy
{
    public function __construct(
        public int $maxSteps,
        public int $maxTokensIn,
        public int $maxTokensOut,
        public int $maxSubCalls,
        public int $maxWallClockSec,
        public int $maxDepth,
    ) {}
}

