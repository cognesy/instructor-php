<?php declare(strict_types=1);

namespace Cognesy\Addons\Collaboration;

use Cognesy\Addons\Collaboration\Collections\Collaborators;
use Cognesy\Addons\Collaboration\Contracts\CanChooseNextCollaborator;
use Cognesy\Addons\Collaboration\Contracts\CanCollaborate;
use Cognesy\Addons\Collaboration\Data\CollaborationState;
use Cognesy\Addons\Collaboration\Data\CollaborationStep;
use Cognesy\Addons\Collaboration\Events\CollaborationBeforeSend;
use Cognesy\Addons\Collaboration\Events\CollaborationCompleted;
use Cognesy\Addons\Collaboration\Events\CollaboratorSelected;
use Cognesy\Addons\Collaboration\Events\CollaborationStateUpdated;
use Cognesy\Addons\Collaboration\Events\CollaborationStepCompleted;
use Cognesy\Addons\Collaboration\Events\CollaborationStepFailed;
use Cognesy\Addons\Collaboration\Events\CollaborationStepStarting;
use Cognesy\Addons\Collaboration\Exceptions\CollaborationException;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\StateProcessing\CanApplyProcessors;
use Cognesy\Addons\StepByStep\StepByStep;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Events\Traits\HandlesEvents;
use Throwable;

/**
 * Orchestrates a multi-turn chat between various participants.
 * 
 * Participants can be humans, AI models, or other entities capable of contributing to the conversation.
 * The chat continues until a specified continuation criteria is no longer met.
 * Each turn involves selecting the next participant, allowing them to contribute,
 * and updating the chat state accordingly.
 *
 * @extends StepByStep<CollaborationState, CollaborationStep>
 */
class Collaboration extends StepByStep
{
    use HandlesEvents;

    private readonly Collaborators $collaborators;
    private readonly CanChooseNextCollaborator $nextCollaboratorSelector;
    private readonly ContinuationCriteria $continuationCriteria;
    private bool $forceThrowOnFailure;

    /**
     * @param CanApplyProcessors<CollaborationState> $processors
     */
    public function __construct(
        Collaborators             $collaborators,
        CanChooseNextCollaborator $nextCollaboratorSelector,
        CanApplyProcessors        $processors,
        ContinuationCriteria      $continuationCriteria,
        ?CanHandleEvents          $events = null,
        bool                      $forceThrowOnFailure = true,
    ) {
        parent::__construct($processors);

        $this->collaborators = $collaborators;
        $this->nextCollaboratorSelector = $nextCollaboratorSelector;
        $this->continuationCriteria = $continuationCriteria;
        $this->events = EventBusResolver::using($events);
        $this->forceThrowOnFailure = $forceThrowOnFailure;
    }

    // INTERNAL ////////////////////////////////////////////

    protected function selectCollaborator(CollaborationState $state): CanCollaborate {
        $participant = $this->nextCollaboratorSelector->nextCollaborator($state, $this->collaborators);
        $this->emitCollaboratorSelected($participant, $state);
        return $participant;
    }

    #[\Override]
    protected function canContinue(object $state): bool {
        assert($state instanceof CollaborationState);
        return $this->continuationCriteria->canContinue($state);
    }

    #[\Override]
    protected function makeNextStep(object $state): CollaborationStep {
        assert($state instanceof CollaborationState);
        $this->emitCollaborationTurnStarting($state);
        $participant = $this->selectCollaborator($state);
        $this->emitCollaborationBeforeSend($participant, $state);
        return $participant->act($state);
    }

    #[\Override]
    protected function applyStep(object $state, object $nextStep): CollaborationState {
        assert($state instanceof CollaborationState);
        assert($nextStep instanceof CollaborationStep);
        $newState = $state
            ->withAddedStep($nextStep)
            ->withCurrentStep($nextStep);
        $this->emitCollaborationStateUpdated($newState);
        return $newState;
    }

    #[\Override]
    protected function onNoNextStep(object $state): CollaborationState {
        assert($state instanceof CollaborationState);
        $this->emitCollaborationCompleted($state);
        return $state;
    }

    #[\Override]
    protected function onStepCompleted(object $state): CollaborationState {
        assert($state instanceof CollaborationState);
        $this->emitCollaborationTurnCompleted($state);
        return $state;
    }

    #[\Override]
    protected function onFailure(Throwable $error, object $state): CollaborationState {
        assert($state instanceof CollaborationState);
        $failure = $error instanceof CollaborationException
            ? $error
            : CollaborationException::fromThrowable($error);
        $failureStep = CollaborationStep::failure(
            error: $failure,
            collaboratorName: $state->currentStep()?->participantName() ?? '?',
            inputMessages: $state->messages(),
        );
        $failedState = $this->applyStep(
            state: $state,
            nextStep: $failureStep
        );
        $this->emitCollaborationTurnFailed($failedState, $failure);
        if ($this->forceThrowOnFailure) {
            throw $failure;
        }
        return $failedState;
    }

    // MUTATORS ///////////////////////////////////////////////////

    /**
     * @param CanApplyProcessors<CollaborationState>|null $processors
     */
    public function with(
        ?Collaborators             $collaborators = null,
        ?CanChooseNextCollaborator $nextCollaboratorSelector = null,
        ?CanApplyProcessors        $processors = null,
        ?ContinuationCriteria      $continuationCriteria = null,
        ?CanHandleEvents           $events = null,
    ): Collaboration {
        return new Collaboration(
            collaborators: $collaborators ?? $this->collaborators,
            nextCollaboratorSelector: $nextCollaboratorSelector ?? $this->nextCollaboratorSelector,
            processors: $processors ?? $this->processors,
            continuationCriteria: $continuationCriteria ?? $this->continuationCriteria,
            events: EventBusResolver::using($events),
        );
    }

    public function withParticipants(Collaborators $collaborators): Collaboration {
        return $this->with(collaborators: $collaborators);
    }

    public function withNextParticipantSelector(CanChooseNextCollaborator $selector): Collaboration {
        return $this->with(nextCollaboratorSelector: $selector);
    }

    /**
     * @param CanApplyProcessors<CollaborationState> $processors
     */
    public function withProcessors(CanApplyProcessors $processors): Collaboration {
        return $this->with(processors: $processors);
    }

    public function withContinuationCriteria(ContinuationCriteria $criteria): Collaboration {
        return $this->with(continuationCriteria: $criteria);
    }

    public function withEventBus(CanHandleEvents $events): Collaboration {
        return $this->with(events: $events);
    }

    // EVENTS ////////////////////////////////////////////

    private function emitCollaborationStateUpdated(CollaborationState $state): void {
        $this->events->dispatch(new CollaborationStateUpdated([
            'state' => $state->toArray(),
            'step' => $state->currentStep()?->toArray() ?? [],
        ]));
    }

    private function emitCollaboratorSelected(CanCollaborate $collaborator, CollaborationState $state) : void {
        $this->events->dispatch(new CollaboratorSelected([
            'collaboratorName' => $collaborator->name(),
            'collaboratorClass' => get_class($collaborator),
            'state' => $state->toArray(),
        ]));
    }

    private function emitCollaborationBeforeSend(CanCollaborate $collaborator, CollaborationState $state) : void {
        $this->events->dispatch(new CollaborationBeforeSend([
            'collaborator' => $collaborator,
            'state' => $state->toArray()
        ]));
    }

    private function emitCollaborationCompleted(CollaborationState $state) : void {
        $this->events->dispatch(new CollaborationCompleted([
            'state' => $state->toArray(),
            'reason' => 'has no next turn'
        ]));
    }

    private function emitCollaborationTurnStarting(CollaborationState $state) : void {
        $this->events->dispatch(new CollaborationStepStarting([
            'turn' => $state->stepCount() + 1,
            'state' => $state->toArray()
        ]));
    }

    private function emitCollaborationTurnCompleted(CollaborationState $updatedState) : void {
        $this->events->dispatch(new CollaborationStepCompleted([
            'state' => $updatedState->toArray(),
            'step' => $updatedState->currentStep()?->toArray() ?? []
        ]));
    }

    private function emitCollaborationTurnFailed(CollaborationState $newState, CollaborationException $exception) : void {
        $this->events->dispatch(new CollaborationStepFailed([
            'error' => $exception->getMessage(),
            'state' => $newState->toArray(),
            'step' => $newState->currentStep()?->toArray() ?? []
        ]));
    }
}
