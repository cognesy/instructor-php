<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Processors;

use Cognesy\Addons\ToolUse\Contracts\CanProcessToolState;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Messages\Messages;

class AppendToolStateMessages implements CanProcessToolState
{
    public function process(ToolUseState $state, ?callable $next = null): ToolUseState {
        $newMessages = $state->currentStep()?->messages() ?? Messages::empty();
        $newState = match(true) {
            $newMessages->isEmpty() => $state,
            default => $state->appendMessages($newMessages)
        };
        return $next ? $next($newState) : $newState;
    }
}