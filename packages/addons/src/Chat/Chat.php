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
use Cognesy\Addons\Core\Contracts\CanExecuteIteratively;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Generator;
use Throwable;

/**
 * Orchestrates a multi-turn chat between various participants.
 * 
 * Participants can be humans, AI models, or other entities capable of contributing to the conversation.
 * The chat continues until a specified continuation criteria is no longer met.
 * Each turn involves selecting the next participant, allowing them to contribute,
 * and updating the chat state accordingly.
 *
 * @implements CanExecuteIteratively<ChatState>
 */
final readonly class Chat implements CanExecuteIteratively
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

    /**
     * @param object<ChatState> $state
     * @return object<ChatState>
     */
    public function nextStep(object $state): object {
        assert($state instanceof ChatState);
        if (!$this->hasNextStep($state)) {
            return $this->handleNoNextStep($state);
        }

        try {
            $nextStep = $this->makeNextStep($state);
        } catch (Throwable $error) {
            return $this->handleFailure($error, $state);
        }

        return $this->updateState($nextStep, $state);
    }

    /**
     * @param object<ChatState> $state
     */
    public function hasNextStep(object $state): bool {
        assert($state instanceof ChatState);
        return $this->continuationCriteria->canContinue($state) ?? false;
    }

    /**
     * @param object<ChatState> $state
     * @return object<ChatState>
     */
    public function finalStep(object $state): object {
        assert($state instanceof ChatState);
        while ($this->hasNextStep($state)) {
            $state = $this->nextStep($state);
        }

        $finalState = $this->handleNoNextStep($state);
        return match (true) {
            $finalState === $state => $state,
            default => $finalState,
        };
    }

    /**
     * @param object<ChatState> $state
     * @return Generator<ChatState>
     */
    public function iterator(object $state): iterable {
        assert($state instanceof ChatState);
        while ($this->hasNextStep($state)) {
            $state = $this->nextStep($state);
            yield $state;
        }

        $finalState = $this->handleNoNextStep($state);
        if ($finalState !== $state) {
            yield $finalState;
        }
    }

    // INTERNAL ////////////////////////////////////////////

    protected function makeNextStep(ChatState $state) : ChatStep {
        $this->emitChatTurnStarting($state);
        $participant = $this->selectParticipant($state);
        $this->emitChatBeforeSend($participant, $state);
        return $participant->act($state);
    }

    protected function updateState(ChatStep $step, ChatState $state) : ChatState {
        $newState = $state
            ->withAddedStep($step)
            ->withCurrentStep($step);
        $newState = $this->stepProcessors->apply($newState);
        $this->emitChatStateUpdated($newState, $state);
        $this->emitChatTurnCompleted($newState);
        return $newState;
    }

    protected function handleNoNextStep(object $state) : object {
        assert($state instanceof ChatState);
        $this->emitChatCompleted($state);
        return $state;
    }

    protected function handleFailure(Throwable $error, ChatState $state) : ChatState {
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

    protected function selectParticipant(ChatState $state): CanParticipateInChat {
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
