<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\ExecutionHistory;

use Cognesy\Agents\Hook\Contracts\HookInterface;
use Cognesy\Agents\Hook\Data\HookContext;

/**
 * AfterExecution hook that records an ExecutionSummary into the ExecutionStore.
 */
final class ExecutionHistoryHook implements HookInterface
{
    public function __construct(
        private readonly ExecutionStore $store,
    ) {}

    #[\Override]
    public function handle(HookContext $context): HookContext
    {
        $state = $context->state();

        if ($state->execution() === null) {
            return $context;
        }

        $summary = ExecutionSummary::fromState($state);
        $this->store->record($state->agentId(), $summary);

        return $context;
    }
}
