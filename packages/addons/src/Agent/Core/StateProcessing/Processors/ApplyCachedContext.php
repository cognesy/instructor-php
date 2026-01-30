<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Core\StateProcessing\Processors;

use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\StepByStep\StateProcessing\CanProcessAnyState;
use Cognesy\Polyglot\Inference\Data\CachedInferenceContext;

/**
 * @implements CanProcessAnyState<AgentState>
 */
final readonly class ApplyCachedContext implements CanProcessAnyState
{
    public function __construct(
        private CachedInferenceContext $cachedContext,
        private bool                   $onlyWhenEmpty = true,
    ) {}

    #[\Override]
    public function canProcess(object $state): bool {
        return $state instanceof AgentState;
    }

    #[\Override]
    public function process(object $state, ?callable $next = null): object {
        assert($state instanceof AgentState);

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
