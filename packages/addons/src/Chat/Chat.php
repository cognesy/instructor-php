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
use Cognesy\Addons\Chat\Events\ChatStepCompleted;
use Cognesy\Addons\Chat\Events\ChatStepStarting;
use Cognesy\Addons\Chat\Exceptions\ChatException;
use Cognesy\Addons\Chat\Exceptions\ChatStepFailed;
use Cognesy\Addons\Core\Continuation\ContinuationCriteria;
use Cognesy\Addons\Core\Contracts\CanApplyProcessors;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Generator;
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

        try {
            $nextStep = $this->makeNextStep($state);
        } catch (Throwable $error) {
            return $this->handleFailure($error, $state);
        }

        return $this->updateState($nextStep, $state);
    }

    public function hasNextStep(ChatState $state): bool {
        return $this->continuationCriteria->canContinue($state) ?? false;
    }

    public function finalStep(ChatState $state): ChatState {
        while ($this->hasNextStep($state)) {
            $state = $this->nextStep($state);
        }
        return $state;
    }

    /** @return Generator<ChatState> */
    public function iterator(ChatState $state): iterable {
        while ($this->hasNextStep($state)) {
            $state = $this->nextStep($state);
            yield $state;
        }
    }

    // INTERNAL ////////////////////////////////////////////

    private function makeNextStep(ChatState $state) : ChatStep {
        $this->emitChatTurnStarting($state);
        $participant = $this->selectParticipant($state);
        $this->emitChatBeforeSend($participant, $state);
        return $participant->act($state);
    }

    private function updateState(ChatStep $step, ChatState $state) : ChatState {
        $newState = $state
            ->withAddedStep($step)
            ->withCurrentStep($step);
        $newState = $this->stepProcessors->apply($newState);
        $this->emitChatStateUpdated($newState, $state);
        $this->emitChatTurnCompleted($newState);
        return $newState;
    }

    private function handleFailure(Throwable $error, ChatState $state) : ChatState {
        $failure = $error instanceof ChatException
            ? $error
            : ChatException::fromThrowable($error);
        $failureStep = ChatStep::failure(
            error: $failure,
            participantName: $state->currentStep()?->participantName() ?? '?',
            inputMessages: $state->messages(),
        );
        $newState = $this->updateState(
            step: $failureStep,
            state: $state,
        );
        $this->emitChatTurnFailed($newState, $failure);
        return $newState;
    }

    private function selectParticipant(ChatState $state): CanParticipateInChat {
        $participant = $this->nextParticipantSelector->nextParticipant($state, $this->participants);
        $this->emitChatParticipantSelected($participant, $state);
        return $participant;
    }

    // MUTATORS ///////////////////////////////////////////////////

    public function with(
        ?Participants $participants = null,
        ?CanChooseNextParticipant $nextParticipantSelector = null,
        ?CanApplyProcessors $stepProcessors = null,
        ?ContinuationCriteria $continuationCriteria = null,
        ?CanHandleEvents $events = null,
    ): Chat {
        return new Chat(
            participants: $participants ?? $this->participants,
            nextParticipantSelector: $nextParticipantSelector ?? $this->nextParticipantSelector,
            stepProcessors: $stepProcessors ?? $this->stepProcessors,
            continuationCriteria: $continuationCriteria ?? $this->continuationCriteria,
            events: EventBusResolver::using($events) ?? $this->events,
        );
    }

    public function withParticipants(Participants $participants): Chat {
        return $this->with(participants: $participants);
    }

    public function withNextParticipantSelector(CanChooseNextParticipant $selector): Chat {
        return $this->with(nextParticipantSelector: $selector);
    }

    public function withStepProcessors(CanApplyProcessors $processors): Chat {
        return $this->with(stepProcessors: $processors);
    }

    public function withContinuationCriteria(ContinuationCriteria $criteria): Chat {
        return $this->with(continuationCriteria: $criteria);
    }

    public function withEventBus(CanHandleEvents $events): Chat {
        return $this->with(events: $events);
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
        $this->events->dispatch(new ChatStepStarting([
            'turn' => $state->stepCount() + 1,
            'state' => $state->toArray()
        ]));
    }

    private function emitChatTurnCompleted(ChatState $updatedState) : void {
        $this->events->dispatch(new ChatStepCompleted([
            'state' => $updatedState->toArray(),
            'step' => $updatedState->currentStep()?->toArray() ?? []
        ]));
    }

    private function emitChatTurnFailed(ChatState $newState, ChatException $exception) : void {
        $this->events->dispatch(new ChatStepFailed([
            'error' => $exception->getMessage(),
            'state' => $newState->toArray(),
            'step' => $newState->currentStep()?->toArray() ?? []
        ]));
    }
}
