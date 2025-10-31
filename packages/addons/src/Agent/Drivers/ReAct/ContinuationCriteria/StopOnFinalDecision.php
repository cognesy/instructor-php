<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Drivers\ReAct\ContinuationCriteria;

use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;
use Cognesy\Addons\Agent\Data\ToolUseState;
use Cognesy\Addons\Agent\Enums\AgentStepType;

/**
 * @implements CanDecideToContinue<object>
 */
final class StopOnFinalDecision implements CanDecideToContinue
{
    #[\Override]
    public function canContinue(object $state): bool {
        if (!$state instanceof ToolUseState) {
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
