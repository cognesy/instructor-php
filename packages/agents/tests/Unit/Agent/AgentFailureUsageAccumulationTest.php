<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Core\AgentLoop;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Core\Enums\AgentStatus;
use Cognesy\Agents\Core\Tools\ToolExecutor;
use Cognesy\Agents\Events\AgentEventEmitter;
use Cognesy\Agents\Exceptions\AgentException;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Throwable;
use tmp\ErrorHandling\Contracts\CanHandleAgentErrors;
use tmp\ErrorHandling\Data\ErrorHandlingResult;

describe('AgentLoop failure usage accumulation', function () {
    it('derives usage from recorded failure steps', function () {
        $driver = new class implements CanUseTools {
            public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentStep {
                throw new \RuntimeException('driver boom');
            }
        };

        $errorHandler = new class implements CanHandleAgentErrors {
            public function handleError(Throwable $error, AgentState $state): ErrorHandlingResult {
                $exception = AgentException::fromError($error);
                $response = new InferenceResponse(usage: new Usage(1, 0));
                $step = new AgentStep(
                    inputMessages: $state->messages(),
                    inferenceResponse: $response,
                );

                return new ErrorHandlingResult(
                    failureStep: $step,
                    stopSignal: null,
                    finalStatus: AgentStatus::Failed,
                    exception: $exception,
                );
            }
        };

        $tools = new Tools();
        $agentLoop = new AgentLoop(
            tools: $tools,
            toolExecutor: new ToolExecutor($tools),
            errorHandler: $errorHandler,
            driver: $driver,
            eventEmitter: new AgentEventEmitter(),
        );

        $state = AgentState::empty()->withMessages(Messages::fromString('ping'));

        $failedState = null;
        foreach ($agentLoop->iterate($state) as $stepState) {
            $failedState = $stepState;
            break;
        }

        expect($failedState)->not->toBeNull();
        expect($failedState->usage()->input())->toBe(1);
    });
})->skip('hooks not integrated yet');
