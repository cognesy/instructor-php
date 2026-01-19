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
use Cognesy\Polyglot\Inference\Data\Usage;

final class CountingUsage extends Usage
{
    public function withAccumulated(Usage $usage): Usage {
        return new self(
            inputTokens: $this->inputTokens + 1,
            outputTokens: $this->outputTokens,
            cacheWriteTokens: $this->cacheWriteTokens,
            cacheReadTokens: $this->cacheReadTokens,
            reasoningTokens: $this->reasoningTokens,
        );
    }
}

describe('Collaboration failure usage accumulation', function () {
    it('accumulates usage when onFailure records a step result', function () {
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
            ->withMessages(Messages::fromString('ping'))
            ->withUsage(new CountingUsage());
        $failedState = $collaboration->nextStep($state);

        expect($failedState->usage()->input())->toBe(1);
    });
});
