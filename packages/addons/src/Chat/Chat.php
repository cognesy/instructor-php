<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat;

use Cognesy\Addons\Chat\Collections\Participants;
use Cognesy\Addons\Chat\Contracts\CanChooseNextParticipant;
use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Addons\Chat\Events\ChatBeforeSend;
use Cognesy\Addons\Chat\Events\ChatCompleted;
use Cognesy\Addons\Chat\Events\ChatParticipantSelected;
use Cognesy\Addons\Chat\Events\ChatStateUpdated;
use Cognesy\Addons\Chat\Events\ChatTurnCompleted;
use Cognesy\Addons\Chat\Events\ChatTurnStarting;
use Cognesy\Addons\Core\Continuation\ContinuationCriteria;
use Cognesy\Addons\Core\Contracts\CanApplyProcessors;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Throwable;

final readonly class Chat
{
    private Participants $participants;
    private CanChooseNextParticipant $nextParticipantSelector;
    private CanApplyProcessors $stepProcessors;
    private ContinuationCriteria $continuationCriteria;
    private CanHandleEvents $events;

    /**
     * @param CanApplyProcessors<ChatState> $stepProcessors
     */
    public function __construct(
        Participants $participants,
        CanChooseNextParticipant $nextParticipantSelector,
        CanApplyProcessors $stepProcessors,
        ContinuationCriteria $continuationCriteria,
        ?CanHandleEvents $events = null,
    ) {
        $this->participants = $participants;
        $this->nextParticipantSelector = $nextParticipantSelector;
        $this->stepProcessors = $stepProcessors;
        $this->continuationCriteria = $continuationCriteria;
        $this->events = EventBusResolver::using($events);
    }

    public function nextStep(ChatState $state): ChatState {
        if (!$this->hasNextStep($state)) {
            $this->emitChatCompleted($state);
            return $state;
        }

        $this->emitChatTurnStarting($state);
        $participant = $this->selectParticipant($state);
        $this->emitChatBeforeSend($participant, $state);

        try {
            $nextStep = $participant->act($state);
        } catch (Throwable $error) {
            $failureStep = ChatStep::failure(
                participantName: $participant->name(),
                inputMessages: $state->messages(),
                error: $error,
            );
            $newState = $this->updateState($failureStep, $state);
            $this->emitChatTurnCompleted($newState);

            return $newState;
        }

        $newState = $this->updateState($nextStep, $state);
        $this->emitChatTurnCompleted($newState);

        return $newState;
    }

    public function hasNextStep(ChatState $state): bool {
        return $this->continuationCriteria->canContinue($state) ?? false;
    }

    // PRIVATE HELPERS ////////////////////////////////////////////

    private function selectParticipant(ChatState $state): CanParticipateInChat {
        $participant = $this->nextParticipantSelector->nextParticipant($state, $this->participants);
        $this->emitChatParticipantSelected($participant, $state);
        return $participant;
    }

    private function updateState(ChatStep $step, ChatState $state) : ChatState {
        $newState = $state->withAddedStep($step)->withCurrentStep($step);
        $newState = $this->stepProcessors->apply($newState);
        $this->emitChatStateUpdated($newState, $state);
        return $newState;
    }

    // EVENTS ////////////////////////////////////////////

    private function emitChatStateUpdated(ChatState $newState, ChatState $state): void {
        $this->events->dispatch(new ChatStateUpdated([
            'newState' => $newState->toArray(),
            'oldState' => $state->toArray(),
            'step' => $newState->currentStep()?->toArray() ?? [],
        ]));
    }

    private function emitChatParticipantSelected(CanParticipateInChat $participant, ChatState $state) : void {
        $this->events->dispatch(new ChatParticipantSelected([
            'participantName' => $participant?->name(),
            'participantClass' => $participant ? get_class($participant) : null,
            'state' => $state->toArray(),
        ]));
    }

    private function emitChatBeforeSend(CanParticipateInChat $participant, ChatState $state) : void {
        $this->events->dispatch(new ChatBeforeSend([
            'participant' => $participant,
            'state' => $state->toArray()
        ]));
    }

    private function emitChatCompleted(ChatState $state) : void {
        $this->events->dispatch(new ChatCompleted([
            'state' => $state->toArray(),
            'reason' => 'has no next turn'
        ]));
    }

    private function emitChatTurnStarting(ChatState $state) {
        $this->events->dispatch(new ChatTurnStarting([
            'turn' => $state->stepCount() + 1,
            'state' => $state->toArray()
        ]));
    }

    private function emitChatTurnCompleted(ChatState $updatedState) : void {
        $this->events->dispatch(new ChatTurnCompleted([
            'state' => $updatedState->toArray(),
            'step' => $updatedState->currentStep()?->toArray() ?? []
        ]));
    }
}
