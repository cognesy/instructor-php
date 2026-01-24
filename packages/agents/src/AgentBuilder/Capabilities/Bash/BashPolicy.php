<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Bash;

class BashPolicy
{
    public function __construct(
        public int $maxOutputChars = 50000,
        public int $headChars = 8000,
        public int $tailChars = 40000,
    ) {}
}
