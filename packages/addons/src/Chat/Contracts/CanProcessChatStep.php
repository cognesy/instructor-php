<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Contracts;

use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;

interface CanProcessChatStep
{
    public function process(ChatStep $step, ChatState $state) : ChatState;
}
