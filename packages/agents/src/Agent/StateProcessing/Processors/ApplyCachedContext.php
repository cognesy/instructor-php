<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\StateProcessing\Processors;

use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\StateProcessing\CanProcessAgentState;
use Cognesy\Polyglot\Inference\Data\CachedContext;

final readonly class ApplyCachedContext implements CanProcessAgentState
{
    public function __construct(
        private CachedContext $cachedContext,
        private bool $onlyWhenEmpty = true,
    ) {}

    #[\Override]
    public function canProcess(AgentState $state): bool {
        return true;
    }

    #[\Override]
    public function process(AgentState $state, ?callable $next = null): AgentState {
        $newState = $this->applyCachedContext($state);
        return $next ? $next($newState) : $newState;
    }

    private function applyCachedContext(AgentState $state): AgentState {
        if ($this->cachedContext->isEmpty()) {
            return $state;
        }

        if ($this->onlyWhenEmpty && !$state->cache()->isEmpty()) {
            return $state;
        }

        return $state->withCachedContext($this->cachedContext);
    }
}
