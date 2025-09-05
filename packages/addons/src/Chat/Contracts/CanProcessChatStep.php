<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Contracts;

use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Addons\Chat\Data\ChatStepOutcome;

interface CanProcessChatStep
{
    public function processStep(ChatStep $step, ChatState $state) : ChatStepOutcome;
}
