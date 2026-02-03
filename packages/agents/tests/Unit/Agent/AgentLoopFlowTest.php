<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Core\AgentLoop;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Core\Enums\ExecutionStatus;
use Cognesy\Agents\Core\Tools\ToolExecutor;
use Cognesy\Agents\Events\CanEmitAgentEvents;
use Cognesy\Agents\Exceptions\ToolExecutionBlockedException;
use Cognesy\Agents\Hooks\Data\HookContext;
use Cognesy\Agents\Hooks\Enums\HookTrigger;
use Cognesy\Agents\Hooks\Interceptors\CanInterceptAgentLifecycle;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\Usage;
use DateTimeImmutable;
use RuntimeException;

final class CountingEventEmitter implements CanEmitAgentEvents
{
    public int $stepStartedCount = 0;
    public int $stepCompletedCount = 0;
    public int $executionFailedCount = 0;
    public int $toolCallBlockedCount = 0;

    public function executionStarted(AgentState $state, int $availableTools): void {}

    public function stepStarted(AgentState $state): void {
        $this->stepStartedCount++;
    }

    public function stepCompleted(AgentState $state): void {
        $this->stepCompletedCount++;
    }

    public function stateUpdated(AgentState $state): void {}

    public function continuationEvaluated(AgentState $state): void {}

    public function executionStopped(AgentState $state): void {}

    public function executionFinished(AgentState $state): void {}

    public function executionFailed(AgentState $state, \Throwable $exception): void {
        $this->executionFailedCount++;
    }

    public function toolCallStarted(ToolCall $toolCall, DateTimeImmutable $startedAt): void {}

    public function toolCallCompleted(\Cognesy\Agents\Core\Data\ToolExecution $execution): void {}

    public function toolCallBlocked(ToolCall $toolCall, string $reason, ?string $hookName = null): void {
        $this->toolCallBlockedCount++;
    }

    public function inferenceRequestStarted(AgentState $state, int $messageCount, ?string $model = null): void {}

    public function inferenceResponseReceived(AgentState $state, ?InferenceResponse $response, DateTimeImmutable $requestStartedAt): void {}

    public function subagentSpawning(string $parentAgentId, string $subagentName, string $prompt, int $depth, int $maxDepth): void {}

    public function subagentCompleted(
        string $parentAgentId,
        string $subagentId,
        string $subagentName,
        ExecutionStatus $status,
        int $steps,
        ?Usage $usage,
        DateTimeImmutable $startedAt
    ): void {}

    public function hookExecuted(string $hookType, string $tool, string $outcome, ?string $reason, DateTimeImmutable $startedAt): void {}

    public function decisionExtractionFailed(AgentState $state, string $errorMessage, string $errorType, int $attemptNumber = 1, int $maxAttempts = 1): void {}

    public function validationFailed(AgentState $state, string $validationType, array $errors): void {}

    public function stopSignalReceived(\Cognesy\Agents\Core\Stop\StopSignal $signal) {}
}

final class ThrowingInterceptor implements CanInterceptAgentLifecycle
{
    public function __construct(private HookTrigger $trigger) {}

    public function intercept(HookContext $context): HookContext
    {
        if ($context->triggerType()->equals($this->trigger)) {
            throw new RuntimeException('intercept boom');
        }

        return $context;
    }
}

final class InjectStepInterceptor implements CanInterceptAgentLifecycle
{
    public function __construct(private AgentStep $step) {}

    public function intercept(HookContext $context): HookContext
    {
        return match ($context->triggerType()) {
            HookTrigger::BeforeStep => $context->withState(
                $context->state()->withCurrentStep($this->step),
            ),
            default => $context,
        };
    }
}

describe('AgentLoop flow', function () {
    it('skips after-step when before-step hook throws', function () {
        $tools = new Tools();
        $eventEmitter = new CountingEventEmitter();
        $interceptor = new ThrowingInterceptor(HookTrigger::BeforeStep);

        $driver = new class implements CanUseTools {
            public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentState
            {
                return $state;
            }
        };

        $loop = new AgentLoop(
            tools: $tools,
            toolExecutor: new ToolExecutor($tools, $eventEmitter, $interceptor),
            driver: $driver,
            eventEmitter: $eventEmitter,
            interceptor: $interceptor,
        );

        $finalState = $loop->execute(AgentState::empty());

        expect($eventEmitter->stepStartedCount)->toBe(1);
        expect($eventEmitter->stepCompletedCount)->toBe(0);
        expect($finalState->stepCount())->toBe(0);
        expect($finalState->status())->toBe(ExecutionStatus::Failed);
    });

    it('records a failed step when tool use throws after a step is prepared', function () {
        $tools = new Tools();
        $eventEmitter = new CountingEventEmitter();
        $step = new AgentStep();
        $interceptor = new InjectStepInterceptor($step);

        $driver = new class implements CanUseTools {
            public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentState
            {
                throw new RuntimeException('driver boom');
            }
        };

        $loop = new AgentLoop(
            tools: $tools,
            toolExecutor: new ToolExecutor($tools, $eventEmitter, $interceptor),
            driver: $driver,
            eventEmitter: $eventEmitter,
            interceptor: $interceptor,
        );

        $finalState = $loop->execute(AgentState::empty());

        expect($finalState->status())->toBe(ExecutionStatus::Failed);
        expect($finalState->stepCount())->toBe(1);
        expect($finalState->errors()->hasAny())->toBeTrue();
        expect($eventEmitter->stepCompletedCount)->toBe(1);
    });

    it('emits tool-block and failure events when tool execution is blocked', function () {
        $tools = new Tools();
        $eventEmitter = new CountingEventEmitter();
        $step = new AgentStep();
        $interceptor = new InjectStepInterceptor($step);

        $driver = new class implements CanUseTools {
            public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentState
            {
                $toolCall = ToolCall::fromArray([
                    'id' => 'call_blocked',
                    'name' => 'test_tool',
                    'arguments' => json_encode(['arg' => 'val']),
                ]);

                throw new ToolExecutionBlockedException($toolCall, 'TestHook', 'blocked');
            }
        };

        $loop = new AgentLoop(
            tools: $tools,
            toolExecutor: new ToolExecutor($tools, $eventEmitter, $interceptor),
            driver: $driver,
            eventEmitter: $eventEmitter,
            interceptor: $interceptor,
        );

        $finalState = $loop->execute(AgentState::empty());

        expect($finalState->status())->toBe(ExecutionStatus::Failed);
        expect($eventEmitter->toolCallBlockedCount)->toBe(1);
        expect($eventEmitter->executionFailedCount)->toBe(1);
        expect($eventEmitter->stepCompletedCount)->toBe(1);
    });
});
