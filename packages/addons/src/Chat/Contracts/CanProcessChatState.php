<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Contracts;

use Cognesy\Addons\Chat\Data\ChatState;

interface CanProcessChatState
{
    public function process(ChatState $state, ?callable $next = null) : ChatState;
}
