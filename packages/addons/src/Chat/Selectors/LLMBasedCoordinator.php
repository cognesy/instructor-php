<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Selectors;

use Cognesy\Addons\Chat\Contracts\CanChooseNextParticipant;
use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\Result\Result;

final class LLMBasedCoordinator implements CanChooseNextParticipant
{
    public function __construct(
        private readonly ?Inference $inference = null,
        private readonly ?string $model = null,
        private readonly string $instruction = 'Choose next participant id from the list and output id only:',
    ) {}

    public function choose(ChatState $state) : ?CanParticipateInChat {
        $participants = $state->participants();
        if ($participants->count() === 0) { return null; }
        $ids = array_map(fn(CanParticipateInChat $p) => $p->id(), $participants->all());
        $prompt = $this->instruction.' '.implode(', ', $ids);
        $inference = $this->inference ?? new Inference();
        $result = Result::try(fn() => $inference->with(
            messages: $prompt,
            model: (string)($this->model ?? ''),
            mode: OutputMode::Text,
        )->get());
        $choice = trim((string) $result->valueOr(''));
        foreach ($participants->all() as $p) {
            if ($p->id() === $choice) { return $p; }
        }
        return $participants->at(0);
    }
}
