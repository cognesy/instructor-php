<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Processors;

use Cognesy\Addons\Core\Contracts\CanProcessAnyState;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Messages\Messages;

class AppendToolStateMessages implements CanProcessAnyState
{
    public function process(object $state, ?callable $next = null): ToolUseState {
        $newMessages = $state->currentStep()?->messages() ?? Messages::empty();
        $newState = match(true) {
            $newMessages->isEmpty() => $state,
            default => $state->appendMessages($newMessages)
        };
        return $next ? $next($newState) : $newState;
    }

    public function canProcess(object $state): bool {
        return $state instanceof ToolUseState;
    }
}