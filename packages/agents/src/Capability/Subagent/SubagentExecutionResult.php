<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Subagent;

use Cognesy\Agents\Data\AgentState;

/** A domain executor may provide a bounded primitive result alongside child state. */
final readonly class SubagentExecutionResult
{
    /** @param array<string, mixed> $toolResult */
    public function __construct(
        public AgentState $state,
        public array $toolResult,
    ) {}
}
