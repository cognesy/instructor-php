<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent;

use Cognesy\Agents\Agent\Collections\ToolExecutions;
use Cognesy\Agents\Agent\Collections\Tools;
use Cognesy\Agents\Agent\Contracts\CanEmitAgentEvents;
use Cognesy\Agents\Agent\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Agent\Contracts\ToolInterface;
use Cognesy\Agents\Agent\Data\ToolExecution;
use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\Events\AgentEventEmitter;
use Cognesy\Agents\Agent\Exceptions\InvalidToolArgumentsException;
use Cognesy\Agents\Agent\Exceptions\ToolCallBlockedException;
use Cognesy\Agents\Agent\Exceptions\ToolExecutionException;
use Cognesy\Agents\Agent\Hooks\Data\HookOutcome;
use Cognesy\Agents\Agent\Hooks\Data\ToolHookContext;
use Cognesy\Agents\Agent\Hooks\Stack\HookStack;
use Cognesy\Agents\Agent\Tools\CanAccessAgentState;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Result;
use DateTimeImmutable;

final readonly class ToolExecutor implements CanExecuteToolCalls
{
    private Tools $tools;
    private bool $throwOnToolFailure;
    private CanEmitAgentEvents $eventEmitter;
    private HookStack $toolHookStack;

    public function __construct(
        Tools $tools,
        bool $throwOnToolFailure = false,
        ?CanEmitAgentEvents $eventEmitter = null,
        ?HookStack $toolHookStack = null,
    ) {
        $this->tools = $tools;
        $this->throwOnToolFailure = $throwOnToolFailure;
        $this->eventEmitter = $eventEmitter ?? new AgentEventEmitter();
        $this->toolHookStack = $toolHookStack ?? new HookStack();
    }

    // MAIN API /////////////////////////////////////////////

    #[\Override]
    public function useTool(ToolCall $toolCall, AgentState $state): ToolExecution {
        $toolCall = $this->processBeforeHooks($toolCall, $state);
        $execution = $this->executeDirectly($toolCall, $state);
        $execution = $this->processAfterHooks($toolCall, $execution, $state);

        if ($this->throwOnToolFailure && $execution->result() instanceof Failure) {
            $this->throwOnFailure($execution->result());
        }

        return $execution;
    }

    private function processBeforeHooks(ToolCall $toolCall, AgentState $state): ToolCall {
        $context = ToolHookContext::beforeTool($toolCall, $state);
        $outcome = $this->toolHookStack->process(
            $context,
            static fn($ctx) => HookOutcome::proceed($ctx)
        );

        if ($outcome->isBlocked() || $outcome->isStopped()) {
            throw new ToolCallBlockedException(
                $toolCall->name(),
                $outcome->reason() ?? 'Blocked by hook',
            );
        }

        return $this->extractToolCall($outcome, $toolCall);
    }

    private function processAfterHooks(ToolCall $toolCall, ToolExecution $execution, AgentState $state): ToolExecution {
        $context = ToolHookContext::afterTool($toolCall, $execution, $state);
        $outcome = $this->toolHookStack->process(
            $context,
            static fn($ctx) => HookOutcome::proceed($ctx)
        );

        return $this->extractExecution($outcome, $execution);
    }

    private function extractToolCall(HookOutcome $outcome, ToolCall $default): ToolCall {
        $context = $outcome->context();
        return ($context instanceof ToolHookContext)
            ? $context->toolCall()
            : $default;
    }

    private function extractExecution(HookOutcome $outcome, ToolExecution $default): ToolExecution {
        $context = $outcome->context();
        return ($context instanceof ToolHookContext && $context->execution() !== null)
            ? $context->execution()
            : $default;
    }

    /**
     * Execute a tool call directly without middleware.
     * This is the final handler in the middleware chain.
     */
    private function executeDirectly(ToolCall $toolCall, AgentState $state): ToolExecution {
        $startedAt = new DateTimeImmutable();
        $this->eventEmitter->toolCallStarted($toolCall, $startedAt);

        $result = $this->execute($toolCall, $state);

        $execution = new ToolExecution(
            toolCall: $toolCall,
            result: $result,
            startedAt: $startedAt,
            completedAt: new DateTimeImmutable(),
        );
        $this->eventEmitter->toolCallCompleted($execution);

        return $execution;
    }

    #[\Override]
    public function useTools(ToolCalls $toolCalls, AgentState $state): ToolExecutions {
        return new ToolExecutions(
            ...array_map(
                fn(ToolCall $toolCall) => $this->useTool($toolCall, $state),
                $toolCalls->all()
            )
        );
    }

    // ACCESSORS /////////////////////////////////////////////

    public function tools(): Tools {
        return $this->tools;
    }

    // MUTATORS //////////////////////////////////////////////

    public function withTools(Tools $tools): self {
        return new self(
            tools: $tools,
            throwOnToolFailure: $this->throwOnToolFailure,
            eventEmitter: $this->eventEmitter,
            toolHookStack: $this->toolHookStack,
        );
    }

    public function withThrowOnToolFailure(bool $throw): self {
        return new self(
            tools: $this->tools,
            throwOnToolFailure: $throw,
            eventEmitter: $this->eventEmitter,
            toolHookStack: $this->toolHookStack,
        );
    }

    public function withEventEmitter(CanEmitAgentEvents $eventEmitter): self {
        return new self(
            tools: $this->tools,
            throwOnToolFailure: $this->throwOnToolFailure,
            eventEmitter: $eventEmitter,
            toolHookStack: $this->toolHookStack,
        );
    }

    public function withToolHookStack(HookStack $toolHookStack): self {
        return new self(
            tools: $this->tools,
            throwOnToolFailure: $this->throwOnToolFailure,
            eventEmitter: $this->eventEmitter,
            toolHookStack: $toolHookStack,
        );
    }

    public function toolHookStack(): HookStack {
        return $this->toolHookStack;
    }

    // INTERNAL /////////////////////////////////////////////

    private function execute(ToolCall $toolCall, AgentState $state): Result {
        $tool = $this->prepareTool($toolCall->name(), $state);
        $args = $toolCall->args();

        $validation = $this->validateArgs($tool, $args);
        if ($validation->isFailure()) {
            return $validation;
        }

        return $tool->use(...$args);
    }

    private function prepareTool(string $name, AgentState $state): ToolInterface {
        $tool = $this->tools->get($name);

        // Inject agent state if tool needs it
        if ($tool instanceof CanAccessAgentState) {
            $tool = $tool->withAgentState($state);
        }

        return $tool;
    }

    private function validateArgs(ToolInterface $tool, array $args): Result {
        $toolSchema = $tool->toToolSchema();
        $parameters = $toolSchema['function']['parameters'] ?? [];
        $required = $parameters['required'] ?? [];
        if ($required === []) {
            return Result::success(null);
        }
        $missing = [];
        foreach ($required as $param) {
            if (!array_key_exists($param, $args)) {
                $missing[] = $param;
            }
        }
        if ($missing === []) {
            return Result::success(null);
        }

        return Result::failure(
            new InvalidToolArgumentsException(
                message: 'Missing required parameters: ' . implode(', ', $missing),
            ),
        );
    }

    // ERROR HANDLING ////////////////////////////////////////////

    private function throwOnFailure(Failure $result): void {
        $exception = $result->exception();
        if ($exception instanceof ToolExecutionException) {
            throw $exception;
        }

        throw new ToolExecutionException(
            message: $result->errorMessage(),
            previous: $exception,
        );
    }

}
