<?php declare(strict_types=1);

namespace Cognesy\Agents\Hooks\Defaults;

use Cognesy\Agents\Core\Context\AgentContext;
use Cognesy\Agents\Hooks\HookContext;
use Cognesy\Agents\Hooks\HookInterface;

final readonly class ClearExecutionBufferHook implements HookInterface
{
    #[\Override]
    public function handle(HookContext $context): HookContext
    {
        $state = $context->state();
        $store = $state->store()
            ->section(AgentContext::EXECUTION_BUFFER_SECTION)
            ->clear();

        return $context->withState($state->withMessageStore($store));
    }
}
