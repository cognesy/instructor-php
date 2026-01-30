<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Core\AgentLoop;
use Cognesy\Agents\Core\Tools\ToolExecutor;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\AgentHooks\HookStackObserver;
use Cognesy\Agents\AgentHooks\Contracts\Hook;
use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Agents\AgentHooks\Stack\HookStack;
use Cognesy\Agents\Core\Continuation\Enums\StopReason;
use Cognesy\Agents\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Core\Enums\AgentStatus;
use Cognesy\Agents\Core\ErrorHandling\AgentErrorHandler;
use Cognesy\Agents\Core\Events\AgentEventEmitter;
use Cognesy\Messages\Messages;

describe('AgentLoop continuation evaluation failures', function () {
    it('records a failure outcome when continuation evaluation throws', function () {
        $driver = new class implements CanUseTools {
            public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentStep {
                return new AgentStep();
            }
        };

        $hook = new class implements Hook {
            public function appliesTo(): array {
                return [HookType::AfterStep];
            }

            public function process(AgentState $state, HookType $event): AgentState {
                throw new \RuntimeException('hook boom');
            }
        };

        $hookStack = (new HookStack())
            ->with($hook, -200);
        $observer = new HookStackObserver($hookStack);

        $tools = new Tools();
        $agentLoop = new AgentLoop(
            tools: $tools,
            toolExecutor: new ToolExecutor($tools),
            errorHandler: AgentErrorHandler::default(),
            driver: $driver,
            eventEmitter: new AgentEventEmitter(),
            observer: $observer,
        );

        $state = AgentState::empty()->withMessages(Messages::fromString('ping'));

        // Use iterate() to get the failed state
        $failedState = null;
        foreach ($agentLoop->iterate($state) as $stepState) {
            $failedState = $stepState;
            break;
        }

        expect($failedState)->not->toBeNull();
        $outcome = $failedState->continuationOutcome();

        expect($failedState->status())->toBe(AgentStatus::Failed);
        expect($failedState->stepCount())->toBe(1);
        expect($failedState->stepExecutions()->count())->toBe(1);
        expect($failedState->currentStep()?->errorsAsString())->toContain('hook boom');
        expect($outcome)->not->toBeNull();
        expect($outcome?->stopReason())->toBe(StopReason::ErrorForbade);
        expect($outcome?->shouldContinue())->toBeFalse();
    });
});
