<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Capabilities\SelfCritique;

use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Enums\AgentStepType;
use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;

class SelfCriticContinuationCheck implements CanDecideToContinue
{
    public const METADATA_KEY = 'self_critic_result';
    public const ITERATION_KEY = 'self_critic_iteration';

    public function __construct(
        private int $maxIterations = 2,
    ) {}

    #[\Override]
    public function canContinue(object $state): bool {
        assert($state instanceof AgentState);

        $currentStep = $state->currentStep();

        // If no step yet or not a final response, continue based on default logic
        if ($currentStep === null || $currentStep->stepType() !== AgentStepType::FinalResponse) {
            return true; // Let other criteria decide
        }

        // Check if critic result exists in metadata
        $criticResult = $state->metadata()->get(self::METADATA_KEY);
        $iteration = $state->metadata()->get(self::ITERATION_KEY, 0);

        // If no critic result, means critic hasn't run yet - should continue to process
        if ($criticResult === null) {
            return true;
        }

        // If critic approved, we're done
        if ($criticResult instanceof SelfCriticResult && $criticResult->approved) {
            return false;
        }

        // If not approved but we've hit max iterations, accept anyway
        if ($iteration >= $this->maxIterations) {
            return false;
        }

        // Not approved and under max iterations - should continue for revision
        return true;
    }
}
