<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Contracts;

use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;

interface CanParticipateInChat
{
    public function id() : string;
    public function act(ChatState $state) : ChatStep;
}

