<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Agents\Agent\Agent;
use Cognesy\Agents\Agent\Collections\Tools;
use Cognesy\Agents\Agent\Continuation\ContinuationCriteria;
use Cognesy\Agents\Agent\Continuation\ContinuationDecision;
use Cognesy\Agents\Agent\Continuation\StopReason;
use Cognesy\Agents\Agent\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Agent\Contracts\CanUseTools;
use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\Data\AgentStep;
use Cognesy\Agents\Agent\Enums\AgentStatus;
use Cognesy\Agents\Agent\StateProcessing\StateProcessors;
use Cognesy\Agents\Agent\ToolExecutor;
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
