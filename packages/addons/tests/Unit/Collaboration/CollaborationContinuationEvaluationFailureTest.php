<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Collaboration;

use Cognesy\Addons\Collaboration\Collaboration;
use Cognesy\Addons\Collaboration\Collections\Collaborators;
use Cognesy\Addons\Collaboration\Contracts\CanCollaborate;
use Cognesy\Addons\Collaboration\Data\CollaborationState;
use Cognesy\Addons\Collaboration\Data\CollaborationStep;
use Cognesy\Addons\Collaboration\Selectors\RoundRobin\RoundRobinSelector;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\StopReason;
use Cognesy\Addons\StepByStep\StateProcessing\StateProcessors;
use Cognesy\Messages\Messages;

describe('Collaboration continuation evaluation failures', function () {
    it('records a failure outcome when continuation evaluation throws', function () {
        $collaborator = new class implements CanCollaborate {
            public function name(): string {
                return 'alpha';
            }

            public function act(CollaborationState $state): CollaborationStep {
                return new CollaborationStep(
                    collaboratorName: 'alpha',
                    inputMessages: Messages::fromString('ping'),
                    outputMessages: Messages::fromString('pong'),
                );
            }
        };

        $criterion = ContinuationCriteria::when(
            static function (CollaborationState $state): ContinuationDecision {
                throw new \RuntimeException('criteria boom');
            }
        );
        $continuationCriteria = new ContinuationCriteria($criterion);

        $collaboration = new Collaboration(
            collaborators: new Collaborators($collaborator),
            nextCollaboratorSelector: new RoundRobinSelector(),
            processors: new StateProcessors(),
            continuationCriteria: $continuationCriteria,
            events: null,
            forceThrowOnFailure: false,
        );

        $state = (new CollaborationState())->withMessages(Messages::fromString('ping'));
        $failedState = $collaboration->nextStep($state);
        $outcome = $failedState->continuationOutcome();

        expect($failedState->stepCount())->toBe(1);
        expect($failedState->stepResults())->toHaveCount(1);
        expect($failedState->currentStep()?->errorsAsString())->toContain('criteria boom');
        expect($outcome)->not->toBeNull();
        expect($outcome?->stopReason())->toBe(StopReason::ErrorForbade);
        expect($outcome?->shouldContinue())->toBeFalse();
        expect($collaboration->hasNextStep($failedState))->toBeFalse();
    });
});
