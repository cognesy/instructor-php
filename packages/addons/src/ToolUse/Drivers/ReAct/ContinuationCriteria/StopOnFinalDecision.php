<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Drivers\ReAct\ContinuationCriteria;

use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
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
 * @implements CanDecideToContinue<object>
 */
final class StopOnFinalDecision implements CanDecideToContinue
{
    #[\Override]
    public function decide(object $state): ContinuationDecision {
        if (!$state instanceof ToolUseState) {
            return ContinuationDecision::AllowStop;
        }

        $type = $state->currentStep()?->stepType();

        return match(true) {
            // No step yet: permit bootstrap (act like a guard)
            $type === null => ContinuationDecision::AllowContinuation,
            // Tool execution: request continuation (work to do)
            ToolUseStepType::ToolExecution->is($type) => ContinuationDecision::RequestContinuation,
            // Final/error: allow stop (work complete)
            default => ContinuationDecision::AllowStop,
        };
    }
}
