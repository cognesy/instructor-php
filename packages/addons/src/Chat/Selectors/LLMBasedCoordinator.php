<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Selectors;

use Cognesy\Addons\Chat\Contracts\CanChooseNextParticipant;
use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\Collections\Participants;
use Cognesy\Addons\Chat\Data\ParticipantChoice;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Utils\Result\Result;

final class LLMBasedCoordinator implements CanChooseNextParticipant
{
    public function __construct(
        private readonly ?StructuredOutput $structuredOutput = null,
        private readonly string $instruction = 'Choose the next participant who should take turn in this conversation.',
    ) {}

    public function nextParticipant(ChatState $state, Participants $participants) : CanParticipateInChat {
        if ($participants->count() === 0) {
            return throw new \RuntimeException('No participants available to select from.');
        }
        if ($participants->count() === 1) {
            return $participants->at(0);
        }

        $ids = array_map(fn(CanParticipateInChat $p) => $p->name(), $participants->all());
        $availableParticipants = 'Available participants: ' . implode(', ', $ids);
        
        $messages = $state->messages();
        $prompt = "{$this->instruction}\nAvailable participants:\n{$availableParticipants}";
        
        $structuredOutput = $this->structuredOutput ?? new StructuredOutput();

        $result = Result::try(fn() => $structuredOutput
            ->withMessages($messages->toArray())
            ->withPrompt($prompt)
            ->withResponseModel(ParticipantChoice::class)
            ->get());

        $choice = $result->valueOr(null);
        if ($choice instanceof ParticipantChoice) {
            foreach ($participants->all() as $p) {
                if ($p->name() === $choice->participantName) {
                    return $p;
                }
            }
        }

        // Fallback to first participant if choice is invalid
        return $participants->at(0);
    }
}
