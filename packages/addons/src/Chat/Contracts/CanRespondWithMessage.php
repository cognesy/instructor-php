<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Contracts;

use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Messages\Message;

interface CanRespondWithMessage
{
    public function respond(ChatState $state): Message;
}