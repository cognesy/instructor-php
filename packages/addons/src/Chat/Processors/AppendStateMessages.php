<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Processors;

use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Core\Contracts\CanProcessAnyState;
use Cognesy\Messages\Message;

class AppendStateMessages implements CanProcessAnyState
{
    public function process(object $state, ?callable $next = null): ChatState {
        $withOutputMessage = $state
            ->messages()
            ->appendMessage(
                $state->currentStep()?->outputMessage() ?? Message::empty()
            );
        $newState = $state->withMessages($withOutputMessage);

        return $next ? $next($newState) : $newState;
    }

    public function canProcess(object $state): bool {
        return $state instanceof ChatState;
    }
}