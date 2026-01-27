<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Agent\Agent;
use Cognesy\Agents\Core\Tools\ToolExecutor;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Continuation\ContinuationCriteria;
use Cognesy\Agents\Core\Continuation\Data\ContinuationOutcome;
use Cognesy\Agents\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Core\Contracts\CanHandleAgentErrors;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Core\Enums\AgentStatus;
use Cognesy\Agents\Core\ErrorHandling\Data\ErrorHandlingResult;
use Cognesy\Agents\Core\Events\AgentEventEmitter;
use Cognesy\Agents\Core\Exceptions\AgentException;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Throwable;

describe('Agent failure usage accumulation', function () {
    it('derives usage from recorded failure steps', function () {
        $driver = new class implements CanUseTools {
            public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentStep {
                throw new \RuntimeException('driver boom');
            }
        };

        $errorHandler = new class implements CanHandleAgentErrors {
            public function handleError(Throwable $error, AgentState $state): ErrorHandlingResult {
                $exception = AgentException::fromThrowable($error);
                $response = new InferenceResponse(usage: new Usage(1, 0));
                $step = new AgentStep(
                    inputMessages: $state->messages(),
                    inferenceResponse: $response,
                );

                return new ErrorHandlingResult(
                    failureStep: $step,
                    outcome: ContinuationOutcome::empty(),
                    finalStatus: AgentStatus::Failed,
                    exception: $exception,
                );
            }
        };

        $tools = new Tools();
        $agent = new Agent(
            tools: $tools,
            toolExecutor: new ToolExecutor($tools),
            errorHandler: $errorHandler,
            continuationCriteria: new ContinuationCriteria(),
            driver: $driver,
            eventEmitter: new AgentEventEmitter(),
        );

        $state = AgentState::empty()->withMessages(Messages::fromString('ping'));

        $failedState = null;
        foreach ($agent->iterate($state) as $stepState) {
            $failedState = $stepState;
            break;
        }

        expect($failedState)->not->toBeNull();
        expect($failedState->usage()->input())->toBe(1);
    });
});
