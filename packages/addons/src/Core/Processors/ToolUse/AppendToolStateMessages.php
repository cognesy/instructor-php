<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\Processors\ToolUse;

use Cognesy\Addons\Core\Contracts\CanProcessAnyState;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Messages\Messages;

final class AppendToolStateMessages implements CanProcessAnyState
{
    public function process(object $state, ?callable $next = null): ToolUseState
    {
        $newMessages = $state->currentStep()?->messages() ?? Messages::empty();
        $newState = $newMessages->isEmpty()
            ? $state
            : $state->withAppendedMessages($newMessages);

        return $next ? $next($newState) : $newState;
    }

    public function canProcess(object $state): bool
    {
        return $state instanceof ToolUseState;
    }
}
