<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Subagent;

use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Agents\Template\Data\AgentDefinition;

/** Immutable public input for an optional domain-owned subagent executor. */
final readonly class SubagentInvocation
{
    public function __construct(
        public AgentDefinition $definition,
        public string $prompt,
        public Tools $parentTools,
        public CanUseTools $parentDriver,
        public ?AgentState $parentState,
        public int $depth,
        public SubagentPolicy $policy,
        public string $context = 'fork',
    ) {}
}
