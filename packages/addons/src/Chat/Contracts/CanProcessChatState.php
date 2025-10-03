<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Contracts;

use Cognesy\Addons\Chat\Data\ChatState;

interface CanProcessChatState
{
    /**
     * @param callable(ChatState): ChatState|null $next
     */
    public function process(ChatState $state, ?callable $next = null) : ChatState;
}
