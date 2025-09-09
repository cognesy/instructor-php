<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Processors;

use Cognesy\Addons\ToolUse\Contracts\CanProcessToolState;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Polyglot\Inference\Data\Usage;

class AccumulateTokenUsage implements CanProcessToolState
{
    public function process(ToolUseState $state, ?callable $next = null): ToolUseState {
        $newState = $state->accumulateUsage(
            $state->currentStep()?->usage() ?? Usage::none()
        );

        return $next ? $next($newState) : $newState;
    }
}
