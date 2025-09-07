<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Processors;

use Cognesy\Addons\Chat\Contracts\CanProcessChatStep;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;

class AppendStepMessages implements CanProcessChatStep
{
    public function process(ChatStep $step, ChatState $state): ChatState {
        $withOutputMessage = $state->messages()->appendMessage($step->outputMessage());
        return $state->withMessages($withOutputMessage);
    }
}