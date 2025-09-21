<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Selectors;

use Cognesy\Addons\Chat\Collections\Participants;
use Cognesy\Addons\Chat\Contracts\CanChooseNextParticipant;
use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Exceptions\NoParticipantsException;

final class RoundRobinSelector implements CanChooseNextParticipant
{
    private int $index = 0;

    public function nextParticipant(ChatState $state, Participants $participants) : CanParticipateInChat {
        if ($participants->count() === 0) {
            throw new NoParticipantsException('No participants available to select from.');
        }
        $participant = $participants->at($this->index);
        $this->index = ($this->index + 1) % $participants->count();
        return $participant;
    }
}
