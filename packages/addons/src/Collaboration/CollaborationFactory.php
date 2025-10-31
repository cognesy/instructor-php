<?php declare(strict_types=1);

namespace Cognesy\Addons\Collaboration;

use Cognesy\Addons\Collaboration\Collections\Collaborators;
use Cognesy\Addons\Collaboration\Data\CollaborationState;
use Cognesy\Addons\Collaboration\Data\CollaborationStep;
use Cognesy\Addons\Collaboration\Selectors\RoundRobin\RoundRobinSelector;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ErrorPresenceCheck;
use Cognesy\Addons\StepByStep\Continuation\Criteria\FinishReasonCheck;
use Cognesy\Addons\StepByStep\Continuation\Criteria\RetryLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\TokenUsageLimit;
use Cognesy\Addons\StepByStep\StateProcessing\CanApplyProcessors;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AccumulateTokenUsage;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AppendStepMessages;
use Cognesy\Addons\StepByStep\StateProcessing\StateProcessors;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;

class CollaborationFactory
{
    public static function default(
        Collaborators         $collaborators,
        ?ContinuationCriteria $continuationCriteria = null,
        ?CanApplyProcessors   $processors = null,
        ?CanHandleEvents      $events = null,
    ): Collaboration {
        return new Collaboration(
            collaborators: $collaborators,
            nextCollaboratorSelector: new RoundRobinSelector(),
            processors: $processors ?? self::defaultProcessors(),
            continuationCriteria: $continuationCriteria ?? new ContinuationCriteria(
                new FinishReasonCheck([
                    InferenceFinishReason::Stop,
                    InferenceFinishReason::Length,
                    InferenceFinishReason::ContentFilter,
                ], static fn(CollaborationState $state): ?InferenceFinishReason => $state->currentStep()?->finishReason()),
                new StepsLimit(16, static fn(CollaborationState $state): int => $state->stepCount()),
                new TokenUsageLimit(4096, static fn(CollaborationState $state): int => $state->usage()->total()),
                new ErrorPresenceCheck(static fn(CollaborationState $state): bool => $state->currentStep()?->hasErrors() ?? false),
                new RetryLimit(2, static fn(CollaborationState $state) => $state->steps(), static fn(CollaborationStep $step): bool => $step->hasErrors()),
            ),
            events: $events,
        );
    }
    
    protected static function defaultProcessors(): CanApplyProcessors {
        /** @psalm-suppress InvalidArgument - Processors work via canProcess() runtime check */
        return new StateProcessors(
            new AppendStepMessages(),
            new AccumulateTokenUsage(),
        );
    }
}
