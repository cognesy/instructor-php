<?php declare(strict_types=1);

namespace Cognesy\Addons\Collaboration\Selectors\LLMSelector;

use Cognesy\Addons\Collaboration\Collections\Collaborators;
use Cognesy\Addons\Collaboration\Contracts\CanChooseNextCollaborator;
use Cognesy\Addons\Collaboration\Contracts\CanCollaborate;
use Cognesy\Addons\Collaboration\Data\CollaborationState;
use Cognesy\Addons\Collaboration\Exceptions\NoCollaboratorException;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Utils\Result\Result;

final readonly class LLMBasedCoordinator implements CanChooseNextCollaborator
{
    public function __construct(
        private ?StructuredOutput $structuredOutput = null,
        private string $instruction = 'Choose the next participant who should take turn in this conversation.',
    ) {}

    #[\Override]
    public function nextCollaborator(CollaborationState $state, Collaborators $collaborators) : CanCollaborate {
        if ($collaborators->count() === 0) {
            throw new NoCollaboratorException('No participants available to select from.');
        }

        $firstParticipant = $collaborators->at(0);
        if ($firstParticipant === null) {
            throw new NoCollaboratorException('No participants available to select from.');
        }

        if ($collaborators->count() === 1) {
            return $firstParticipant;
        }

        $ids = array_map(static fn(CanCollaborate $p) => $p->name(), $collaborators->all());
        $availableParticipants = 'Available participants: ' . implode(', ', $ids);

        $messages = $state->messages();
        $prompt = "{$this->instruction}\nAvailable participants:\n{$availableParticipants}";

        $structuredOutput = $this->structuredOutput ?? new StructuredOutput();

        $result = Result::try(fn() => $structuredOutput
            ->withMessages($messages->toArray())
            ->withPrompt($prompt)
            ->withResponseModel(CollaboratorChoice::class)
            ->get());

        $choice = $result->valueOr(null);
        if ($choice instanceof CollaboratorChoice) {
            foreach ($collaborators->all() as $p) {
                if ($p->name() === $choice->collaboratorName) {
                    return $p;
                }
            }
        }

        // Fallback to first participant if choice is invalid
        return $firstParticipant;
    }
}
