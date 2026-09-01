<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Agent\Subagent;

use Cognesy\Agents\Capability\Subagent\SubagentPolicy;
use Cognesy\Agents\Capability\Subagent\UseSubagents;
use Cognesy\Tell\Core\Agent\TellAgentAssembly;
use Cognesy\Tell\Core\Agent\TellDelegationScope;
use Cognesy\Tell\Core\Agent\TellSubagentExecutor;
use Cognesy\Tell\Core\Contract\Agent\CanContributeTellAgent;

final readonly class TellSubagentContribution implements CanContributeTellAgent
{
    #[\Override]
    public function contribute(TellAgentAssembly $assembly): void {
        $delegation = $assembly->delegation;
        if ($delegation !== null && !$delegation instanceof TellDelegationScope) {
            throw new \InvalidArgumentException('Unsupported Tell delegation context.');
        }
        $assembly->capabilities->register('use_subagents', new UseSubagents(
            provider: $assembly->definitions,
            policy: new SubagentPolicy(maxDepth: 1),
            executor: $delegation === null ? null : new TellSubagentExecutor(
                $assembly->agents,
                $assembly->tracer,
                $assembly->request,
                $delegation,
                $assembly->diagnostics,
            ),
            currentDepth: match ($delegation) {
                null => 0,
                default => $delegation->depth,
            },
        ));
    }
}
