<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Addons\Agent\Agent;
use Cognesy\Addons\Agent\Core\Collections\Tools;
use Cognesy\Addons\Agent\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Addons\Agent\Core\Contracts\CanUseTools;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Core\Data\AgentStep;
use Cognesy\Addons\Agent\Core\Enums\AgentStatus;
use Cognesy\Addons\Agent\Core\ToolExecutor;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\StopReason;
use Cognesy\Addons\StepByStep\StateProcessing\StateProcessors;
use Cognesy\Messages\Messages;

describe('Agent continuation evaluation failures', function () {
    it('records a failure outcome when continuation evaluation throws', function () {
        $driver = new class implements CanUseTools {
            public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentStep {
                return new AgentStep();
            }
        };

        $criterion = ContinuationCriteria::when(
            static function (AgentState $state): ContinuationDecision {
                throw new \RuntimeException('criteria boom');
            }
        );
        $continuationCriteria = new ContinuationCriteria($criterion);

        $tools = new Tools();
        $agent = new Agent(
            tools: $tools,
            toolExecutor: new ToolExecutor($tools),
            processors: new StateProcessors(),
            continuationCriteria: $continuationCriteria,
            driver: $driver,
            events: null,
        );

        $state = AgentState::empty()->withMessages(Messages::fromString('ping'));
        $failedState = $agent->nextStep($state);
        $outcome = $failedState->continuationOutcome();

        expect($failedState->status())->toBe(AgentStatus::Failed);
        expect($failedState->stepCount())->toBe(1);
        expect($failedState->stepResults())->toHaveCount(1);
        expect($failedState->currentStep()?->errorsAsString())->toContain('criteria boom');
        expect($outcome)->not->toBeNull();
        expect($outcome?->stopReason())->toBe(StopReason::ErrorForbade);
        expect($outcome?->shouldContinue())->toBeFalse();
        expect($agent->hasNextStep($failedState))->toBeFalse();
    });
});
