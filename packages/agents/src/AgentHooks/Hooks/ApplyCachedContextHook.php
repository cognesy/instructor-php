<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentHooks\Hooks;

use Cognesy\Agents\AgentHooks\Contracts\Hook;
use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Polyglot\Inference\Data\CachedInferenceContext;

/**
 * Hook that applies cached context to agent state before each step.
 */
final readonly class ApplyCachedContextHook implements Hook
{
    public function __construct(
        private CachedInferenceContext $cachedContext,
    ) {}

    #[\Override]
    public function appliesTo(): array
    {
        return [HookType::BeforeStep];
    }

    #[\Override]
    public function process(AgentState $state, HookType $event): AgentState
    {
        return $state->withCachedContext($this->cachedContext);
    }
}
