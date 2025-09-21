<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Contracts;

use Cognesy\Addons\Chat\Collections\Participants;
use Cognesy\Addons\Chat\Data\ChatState;

interface CanChooseNextParticipant
{
    public function nextParticipant(ChatState $state, Participants $participants) : CanParticipateInChat;
}
