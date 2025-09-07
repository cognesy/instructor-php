<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Processors;

use Cognesy\Addons\Chat\Contracts\CanProcessChatStep;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;

final class AccumulateTokenUsage implements CanProcessChatStep
{
    public function process(ChatStep $step, ChatState $state): ChatState {
        return $state->accumulateUsage($step->usage());
    }
}
