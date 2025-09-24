<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat;

use Cognesy\Addons\Chat\Collections\Participants;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Addons\Chat\Selectors\RoundRobinSelector;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ErrorPresenceCheck;
use Cognesy\Addons\StepByStep\Continuation\Criteria\FinishReasonCheck;
use Cognesy\Addons\StepByStep\Continuation\Criteria\RetryLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\TokenUsageLimit;
use Cognesy\Addons\StepByStep\State\Contracts\HasSteps;
use Cognesy\Addons\StepByStep\State\Contracts\HasUsage;
use Cognesy\Addons\StepByStep\StateProcessing\CanApplyProcessors;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AccumulateTokenUsage;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AppendStepMessages;
use Cognesy\Addons\StepByStep\StateProcessing\StateProcessors;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;

class ChatFactory
{
    public static function default(
        Participants $participants,
        ?ContinuationCriteria $continuationCriteria = null,
        ?CanApplyProcessors $processors = null,
        ?CanHandleEvents $events = null,
    ): Chat {
        return new Chat(
            participants: $participants,
            nextParticipantSelector: new RoundRobinSelector(),
            processors: $processors ?? new StateProcessors(
                new AppendStepMessages(),
                new AccumulateTokenUsage(),
            ),
            continuationCriteria: $continuationCriteria ?? new ContinuationCriteria(
                new FinishReasonCheck([
                    InferenceFinishReason::Stop,
                    InferenceFinishReason::Length,
                    InferenceFinishReason::ContentFilter,
                ], fn(HasSteps $state): ?string => $state->currentStep()?->finishReason()),
                new StepsLimit(16, fn(HasSteps $state): int => $state->stepCount()),
                new TokenUsageLimit(4096, fn(HasUsage $state): int => $state->usage()->total()),
                new ErrorPresenceCheck(fn(ChatState $state): bool => $state->currentStep()?->hasErrors() ?? false),
                new RetryLimit(2, fn(ChatState $state) => $state->steps(), fn(ChatStep $step): bool => $step->hasErrors()),
            ),
            events: $events,
        );
    }
}
