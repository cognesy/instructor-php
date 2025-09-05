<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Processors\Step;

use Cognesy\Addons\Chat\Contracts\CanProcessChatStep;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Addons\Chat\Data\ChatStepOutcome;

final class AccumulateTokenUsage implements CanProcessChatStep
{
    public function processStep(ChatStep $step, ChatState $state): ChatStepOutcome {
        $newState = $state->accumulateUsage($step->usage());
        return new ChatStepOutcome($step, $newState);
    }
}
