<?php declare(strict_types=1);

namespace Cognesy\Agents\Drivers\ReAct\ContinuationCriteria;

use Cognesy\Agents\Core\Continuation\Contracts\CanEvaluateContinuation;
use Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Enums\AgentStepType;

/**
 * Allows continuation when the current step is a tool execution.
 * Allows stop when step is final (not a tool execution).
 */
final class StopOnFinalDecision implements CanEvaluateContinuation
{
    #[\Override]
    public function evaluate(AgentState $state): ContinuationEvaluation {
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
