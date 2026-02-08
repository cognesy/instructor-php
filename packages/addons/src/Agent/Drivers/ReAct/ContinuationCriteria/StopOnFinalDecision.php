<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/instructor-agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Drivers\ReAct\ContinuationCriteria;

use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Core\Enums\AgentStepType;
use Cognesy\Addons\StepByStep\Continuation\CanEvaluateContinuation;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\ContinuationEvaluation;

/**
 * Allows continuation when the current step is a tool execution.
 * Allows stop when step is final (not a tool execution).
 *
 * @implements CanEvaluateContinuation<object>
 */
final class StopOnFinalDecision implements CanEvaluateContinuation
{
    #[\Override]
    public function evaluate(object $state): ContinuationEvaluation {
        if (!$state instanceof AgentState) {
            return new ContinuationEvaluation(
                criterionClass: self::class,
                decision: ContinuationDecision::AllowStop,
                reason: 'State is not an AgentState, allowing stop',
                context: ['stateClass' => $state::class],
            );
        }

        $type = $state->currentStep()?->stepType();

        $decision = match(true) {
            $type === null => ContinuationDecision::AllowStop,
            AgentStepType::ToolExecution->is($type) => ContinuationDecision::AllowContinuation,
            default => ContinuationDecision::AllowStop,
        };

        $reason = match(true) {
            $type === null => 'No step type available, allowing stop',
            AgentStepType::ToolExecution->is($type) => 'Tool execution step, allowing continuation',
            default => sprintf('Final step type "%s", allowing stop', $type->value ?? 'unknown'),
        };

        return new ContinuationEvaluation(
            criterionClass: self::class,
            decision: $decision,
            reason: $reason,
            context: ['stepType' => $type?->value ?? 'none'],
        );
    }
}
