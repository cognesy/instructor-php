<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Selectors\LLMSelector;

use Cognesy\Addons\Chat\Collections\Participants;
use Cognesy\Addons\Chat\Contracts\CanChooseNextParticipant;
use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Exceptions\NoParticipantsException;
use Cognesy\Instructor\Contracts\CanCreateStructuredOutput;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Utils\Result\Result;

final readonly class LLMBasedCoordinator implements CanChooseNextParticipant
{
    public function __construct(
        private CanCreateStructuredOutput $structuredOutput,
        private string $instruction = 'Choose the next participant who should take turn in this conversation.',
    ) {}

    #[\Override]
    public function nextParticipant(ChatState $state, Participants $participants) : CanParticipateInChat {
        if ($participants->count() === 0) {
            throw new NoParticipantsException('No participants available to select from.');
        }

        $firstParticipant = $participants->at(0);
        if ($firstParticipant === null) {
            throw new NoParticipantsException('No participants available to select from.');
        }

        if ($participants->count() === 1) {
            return $firstParticipant;
        }

        $ids = array_map(static fn(CanParticipateInChat $p) => $p->name(), $participants->all());
        $availableParticipants = 'Available participants: ' . implode(', ', $ids);

        $messages = $state->messages();
        $prompt = "{$this->instruction}\nAvailable participants:\n{$availableParticipants}";

        $request = new StructuredOutputRequest(
            messages: $messages,
            requestedSchema: ParticipantChoice::class,
            prompt: $prompt,
        );
        $result = Result::try(fn() => $this->structuredOutput->create($request)->get());

        $choice = $result->valueOr(null);
        if ($choice instanceof ParticipantChoice) {
            foreach ($participants->all() as $p) {
                if ($p->name() === $choice->participantName) {
                    return $p;
                }
            }
        }

        // Fallback to first participant if choice is invalid
        return $firstParticipant;
    }
}
