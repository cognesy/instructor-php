<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Contracts;

use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;

interface ChatObserver
{
    public function onStepStart(ChatState $state) : void;
    public function onStepEnd(ChatState $state, ChatStep $step) : void;
}

