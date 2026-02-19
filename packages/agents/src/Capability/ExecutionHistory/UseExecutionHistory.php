<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\ExecutionHistory;

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Hook\Collections\HookTriggers;
use Cognesy\Agents\Hook\Enums\HookTrigger;

/**
 * Capability that records execution summaries after each execute() call.
 *
 * Usage:
 *   $store = new ArrayExecutionStore();
 *   $agent = AgentBuilder::base()
 *       ->withCapability(new UseExecutionHistory(store: $store))
 *       ->build();
 *   $agent->execute($state);
 *   $history = $store->all($state->agentId());
 */
final class UseExecutionHistory implements CanProvideAgentCapability
{
    private ExecutionStore $store;

    public function __construct(
        ?ExecutionStore $store = null,
    ) {
        $this->store = $store ?? new ArrayExecutionStore();
    }

    #[\Override]
    public static function capabilityName(): string
    {
        return 'use_execution_history';
    }

    #[\Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent
    {
        $hooks = $agent->hooks()->with(
            hook: new ExecutionHistoryHook(store: $this->store),
            triggerTypes: HookTriggers::with(HookTrigger::AfterExecution),
            priority: -1000,
            name: 'execution_history',
        );

        return $agent->withHooks($hooks);
    }

    public function store(): ExecutionStore
    {
        return $this->store;
    }
}
