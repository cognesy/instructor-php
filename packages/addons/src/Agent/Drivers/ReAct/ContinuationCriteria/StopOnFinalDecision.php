<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Drivers\ReAct\ContinuationCriteria;

use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Enums\AgentStepType;
use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;

/**
 * @implements CanDecideToContinue<object>
 */
final class StopOnFinalDecision implements CanDecideToContinue
{
    #[\Override]
    public function canContinue(object $state): bool {
        if (!$state instanceof AgentState) {
            return true;
        }

        $type = $state->currentStep()?->stepType();
        return match(true) {
            $type === null => true,
            AgentStepType::ToolExecution->is($type) => true,
            default => false
        };
    }
}
