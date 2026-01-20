<?php declare(strict_types=1);

namespace Cognesy\Addons\AgentBuilder\Capabilities\SelfCritique;

use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Core\Enums\AgentStepType;
use Cognesy\Addons\StepByStep\Continuation\CanEvaluateContinuation;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\ContinuationEvaluation;
use Cognesy\Addons\StepByStep\Continuation\StopReason;

/**
 * Signals continuation when self-critic requests a revision.
 *
 * Returns AllowContinuation when revision needed (has work to do),
 * AllowStop when approved or max iterations reached,
 * ForbidContinuation when max iterations exceeded (hard stop).
 * @implements CanEvaluateContinuation<AgentState>
 */
class SelfCriticContinuationCheck implements CanEvaluateContinuation
{
    public const METADATA_KEY = 'self_critic_result';
    public const ITERATION_KEY = 'self_critic_iteration';

    public function __construct(
        private int $maxIterations = 2,
    ) {}

    #[\Override]
    public function evaluate(object $state): ContinuationEvaluation {
        assert($state instanceof AgentState);

        $currentStep = $state->currentStep();

        // If no step yet or not a final response, let other criteria decide
        if ($currentStep === null || $currentStep->stepType() !== AgentStepType::FinalResponse) {
            return new ContinuationEvaluation(
                criterionClass: self::class,
                decision: ContinuationDecision::AllowStop,
                reason: 'Not a final response step, deferring to other criteria',
                context: ['stepType' => $currentStep?->stepType()?->value ?? 'none'],
            );
        }

        // Check if critic result exists in metadata
        $criticResult = $state->metadata()->get(self::METADATA_KEY);
        $iteration = $state->metadata()->get(self::ITERATION_KEY, 0);

        // If no critic result, means critic hasn't run yet - let other criteria decide
        if ($criticResult === null) {
            return new ContinuationEvaluation(
                criterionClass: self::class,
                decision: ContinuationDecision::AllowStop,
                reason: 'No critic result, deferring to other criteria',
                context: ['iteration' => $iteration],
            );
        }

        // If critic approved, we're done - allow stop
        if ($criticResult instanceof SelfCriticResult && $criticResult->approved) {
            return new ContinuationEvaluation(
                criterionClass: self::class,
                decision: ContinuationDecision::AllowStop,
                reason: 'Self-critic approved the response',
                context: ['iteration' => $iteration, 'approved' => true],
            );
        }

        // If not approved but we've hit max iterations, force stop
        if ($iteration >= $this->maxIterations) {
            return new ContinuationEvaluation(
                criterionClass: self::class,
                decision: ContinuationDecision::ForbidContinuation,
                reason: sprintf('Max self-critic iterations reached: %d/%d', $iteration, $this->maxIterations),
                context: ['iteration' => $iteration, 'maxIterations' => $this->maxIterations],
                stopReason: StopReason::RetryLimitReached,
            );
        }

        // Not approved and under max iterations - signal continuation for revision
        return new ContinuationEvaluation(
            criterionClass: self::class,
            decision: ContinuationDecision::AllowContinuation,
            reason: sprintf('Self-critic requests revision (iteration %d/%d)', $iteration, $this->maxIterations),
            context: ['iteration' => $iteration, 'maxIterations' => $this->maxIterations, 'approved' => false],
        );
    }
}
