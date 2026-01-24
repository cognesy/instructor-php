<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\SelfCritique;

use Cognesy\Agents\Agent\Continuation\CanEvaluateContinuation;
use Cognesy\Agents\Agent\Continuation\ContinuationDecision;
use Cognesy\Agents\Agent\Continuation\ContinuationEvaluation;
use Cognesy\Agents\Agent\Continuation\StopReason;
use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\Enums\AgentStepType;

/**
 * Signals continuation when self-critic requests a revision.
 *
 * Returns AllowContinuation when revision needed (has work to do),
 * AllowStop when approved or max iterations reached,
 * ForbidContinuation when max iterations exceeded (hard stop).
 */
class SelfCriticContinuationCheck implements CanEvaluateContinuation
{
    public const METADATA_KEY = 'self_critic_result';
    public const ITERATION_KEY = 'self_critic_iteration';

    public function __construct(
        private int $maxIterations = 2,
    ) {}

    #[\Override]
    public function evaluate(AgentState $state): ContinuationEvaluation {
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
        /** @var int $iteration */
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
