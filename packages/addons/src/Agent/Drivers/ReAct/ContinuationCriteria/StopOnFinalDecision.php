<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Drivers\ReAct\ContinuationCriteria;

use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Enums\AgentStepType;
use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;

/**
 * Allows continuation when the current step is a tool execution.
 * Allows stop when step is final (not a tool execution).
 *
 * @implements CanDecideToContinue<object>
 */
final class StopOnFinalDecision implements CanDecideToContinue
{
    #[\Override]
    public function decide(object $state): ContinuationDecision {
        if (!$state instanceof AgentState) {
            return ContinuationDecision::AllowStop;
        }

        $type = $state->currentStep()?->stepType();

        return match(true) {
            $type === null => ContinuationDecision::AllowStop,
            AgentStepType::ToolExecution->is($type) => ContinuationDecision::AllowContinuation,
            default => ContinuationDecision::AllowStop,
        };
    }
}
