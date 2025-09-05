<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Selectors;

use Cognesy\Addons\Chat\Contracts\CanChooseNextParticipant;
use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\ToolUse\ToolUse;

final class ToolBasedCoordinator implements CanChooseNextParticipant
{
    /** @var null|callable(ChatState): ToolUse */
    private $factory;

    public function __construct(
        private readonly ?ToolUse $toolUse = null,
        ?callable $toolUseFactory = null,
        private readonly string $instruction = 'Choose next participant id from the list and output id only:',
    ) {
        $this->factory = $toolUseFactory;
    }

    public function choose(ChatState $state) : ?CanParticipateInChat {
        $participants = $state->participants();
        if ($participants->count() === 0) { return null; }
        $ids = array_map(fn(CanParticipateInChat $p) => $p->id(), $participants->all());
        $prompt = $this->instruction.' '.implode(', ', $ids);
        $toolUse = $this->toolUse ?? (is_callable($this->factory) ? ($this->factory)($state) : null);
        if (!$toolUse) { return $participants->at(0); }
        $step = $toolUse->withMessages($prompt)->finalStep();
        $choice = trim((string)$step->response());
        foreach ($participants->all() as $p) {
            if ($p->id() === $choice) { return $p; }
        }
        return $participants->at(0);
    }
}
