<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Processors;

use Cognesy\Addons\Core\Contracts\CanProcessAnyState;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Polyglot\Inference\Data\Usage;

class AccumulateTokenUsage implements CanProcessAnyState
{
    public function process(object $state, ?callable $next = null): ToolUseState {
        $newState = $state->withAccumulatedUsage(
            $state->currentStep()?->usage() ?? Usage::none()
        );

        return $next ? $next($newState) : $newState;
    }

    public function canProcess(object $state): bool {
        return $state instanceof ToolUseState;
    }
}
