<?php declare(strict_types=1);

namespace Cognesy\Experimental\RLM\Data;

use Cognesy\Experimental\RLM\Data\Handles\ContextHandle;

final readonly class RlmInvocation
{
    /**
     * @param array<string,mixed> $hints
     */
    public function __construct(
        public string $task,
        public ContextHandle $context,
        public Policy $policy,
        public array $hints = [],
    ) {}
}

