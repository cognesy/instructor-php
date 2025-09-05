<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Selectors;

use Cognesy\Addons\Chat\Contracts\CanChooseNextParticipant;
use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;
use Cognesy\Addons\Chat\Data\ChatState;

final class RoundRobinSelector implements CanChooseNextParticipant
{
    private int $index = -1;

    public function choose(ChatState $state) : ?CanParticipateInChat {
        $participants = $state->participants();
        if ($participants->count() === 0) { return null; }
        $this->index = ($this->index + 1) % $participants->count();
        return $participants->at($this->index);
    }
}
