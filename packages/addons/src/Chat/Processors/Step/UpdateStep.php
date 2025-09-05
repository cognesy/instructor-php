<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Processors\Step;

use Cognesy\Addons\Chat\Contracts\CanProcessChatStep;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Addons\Chat\Data\ChatStepOutcome;

final class UpdateStep implements CanProcessChatStep
{
    public function processStep(ChatStep $step, ChatState $state): ChatStepOutcome {
        $newState = $state->withCurrentStep($step)->withAddedStep($step);
        return new ChatStepOutcome($step, $newState);
    }
}
