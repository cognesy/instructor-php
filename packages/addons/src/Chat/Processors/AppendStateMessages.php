<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Processors;

use Cognesy\Addons\Chat\Contracts\CanProcessChatState;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Messages\Message;

class AppendStateMessages implements CanProcessChatState
{
    public function process(ChatState $state, ?callable $next = null): ChatState {
        $withOutputMessage = $state
            ->messages()
            ->appendMessage(
                $state->currentStep()?->outputMessage() ?? Message::empty()
            );
        $newState = $state->withMessages($withOutputMessage);

        return $next ? $next($newState) : $newState;
    }
}