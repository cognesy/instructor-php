<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Contracts;

use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Messages\Messages;

interface CanProcessMessage
{
    public function beforeSend(Messages $messages, ChatState $state) : Messages;
    public function beforeAppend(Messages $messages, ChatState $state) : Messages;
}

