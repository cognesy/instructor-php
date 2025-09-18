<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Compilers;

use Cognesy\Addons\Chat\Contracts\CanCompileMessages;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Messages\Messages;

class AllSections implements CanCompileMessages {
    public function compile(ChatState $state): Messages {
        return $state->store()->toMessages();
    }
}