<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Collaboration;

use Cognesy\Addons\Collaboration\Collaboration;
use Cognesy\Addons\Collaboration\Collections\Collaborators;
use Cognesy\Addons\Collaboration\Contracts\CanCollaborate;
use Cognesy\Addons\Collaboration\Data\CollaborationState;
use Cognesy\Addons\Collaboration\Data\CollaborationStep;
use Cognesy\Addons\Collaboration\Selectors\RoundRobin\RoundRobinSelector;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\StateProcessing\StateProcessors;
use Cognesy\Messages\Messages;

describe('Collaboration failure step results', function () {
    it('records a StepResult when a collaborator throws', function () {
        $collaborator = new class implements CanCollaborate {
            public function name(): string {
                return 'boom';
            }

            public function act(CollaborationState $state): CollaborationStep {
                throw new \RuntimeException('collaborator boom');
            }
        };

        $collaboration = new Collaboration(
            collaborators: new Collaborators($collaborator),
            nextCollaboratorSelector: new RoundRobinSelector(),
            processors: new StateProcessors(),
            continuationCriteria: new ContinuationCriteria(),
            events: null,
            forceThrowOnFailure: false,
        );

        $state = (new CollaborationState())
            ->withMessages(Messages::fromString('ping'));
        $failedState = $collaboration->nextStep($state);

        expect($failedState->stepCount())->toBe(1);
        expect($failedState->stepResults())->toHaveCount(1);
        expect($failedState->lastStepResult())->not->toBeNull();
        expect($failedState->currentStep()?->errorsAsString())->toContain('collaborator boom');
        expect($collaboration->hasNextStep($failedState))->toBeFalse();
    });
});
