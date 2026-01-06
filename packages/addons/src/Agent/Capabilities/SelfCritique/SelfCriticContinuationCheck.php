<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Capabilities\SelfCritique;

use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Core\Enums\AgentStepType;
use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;

/**
 * Signals continuation when self-critic requests a revision.
 *
 * Returns AllowContinuation when revision needed (has work to do),
 * AllowStop when approved or max iterations reached,
 * ForbidContinuation when max iterations exceeded (hard stop).
 */
class SelfCriticContinuationCheck implements CanDecideToContinue
{
    public const METADATA_KEY = 'self_critic_result';
    public const ITERATION_KEY = 'self_critic_iteration';

    public function __construct(
        private int $maxIterations = 2,
    ) {}

    #[\Override]
    public function decide(object $state): ContinuationDecision {
        assert($state instanceof AgentState);

        $currentStep = $state->currentStep();

        // If no step yet or not a final response, let other criteria decide
        if ($currentStep === null || $currentStep->stepType() !== AgentStepType::FinalResponse) {
            return ContinuationDecision::AllowStop;
        }

        // Check if critic result exists in metadata
        $criticResult = $state->metadata()->get(self::METADATA_KEY);
        $iteration = $state->metadata()->get(self::ITERATION_KEY, 0);

        // If no critic result, means critic hasn't run yet - let other criteria decide
        if ($criticResult === null) {
            return ContinuationDecision::AllowStop;
        }

        // If critic approved, we're done - allow stop
        if ($criticResult instanceof SelfCriticResult && $criticResult->approved) {
            return ContinuationDecision::AllowStop;
        }

        // If not approved but we've hit max iterations, force stop
        if ($iteration >= $this->maxIterations) {
            return ContinuationDecision::ForbidContinuation;
        }

        // Not approved and under max iterations - signal continuation for revision
        return ContinuationDecision::AllowContinuation;
    }
}
