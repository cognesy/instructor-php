<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Drivers\ReAct\ContinuationCriteria;

use Cognesy\Addons\StepByStep\Continuation\CanEvaluateContinuation;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\ContinuationEvaluation;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Enums\ToolUseStepType;

/**
 * Work driver: Requests continuation when the current step is a tool execution.
 * Allows stop when step is final (not a tool execution).
 *
 * Acts as a hybrid:
 * - Before first step (null): AllowContinuation (guard-like, permits bootstrap)
 * - Tool execution step: RequestContinuation (work driver, has work to do)
 * - Final/error step: AllowStop (work driver, work complete)
 *
 * @implements CanEvaluateContinuation<object>
 */
final class StopOnFinalDecision implements CanEvaluateContinuation
{
    #[\Override]
    public function evaluate(object $state): ContinuationEvaluation {
        if (!$state instanceof ToolUseState) {
            return new ContinuationEvaluation(
                criterionClass: self::class,
                decision: ContinuationDecision::AllowStop,
                reason: 'State is not a ToolUseState, allowing stop',
                context: ['stateClass' => $state::class],
            );
        }

        $type = $state->currentStep()?->stepType();

        $decision = match(true) {
            // No step yet: permit bootstrap (act like a guard)
            $type === null => ContinuationDecision::AllowContinuation,
            // Tool execution: request continuation (work to do)
            ToolUseStepType::ToolExecution->is($type) => ContinuationDecision::RequestContinuation,
            // Final/error: allow stop (work complete)
            default => ContinuationDecision::AllowStop,
        };

        $reason = match(true) {
            $type === null => 'No step type yet, allowing bootstrap',
            ToolUseStepType::ToolExecution->is($type) => 'Tool execution step, requesting continuation',
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
