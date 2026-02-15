<?php declare(strict_types=1);

namespace Cognesy\Agents\Tool;

use Cognesy\Agents\Collections\ToolExecutions;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Continuation\AgentStopException;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Data\ToolExecution;
use Cognesy\Agents\Events\ToolCallCompleted;
use Cognesy\Agents\Events\ToolCallStarted;
use Cognesy\Agents\Exceptions\InvalidToolArgumentsException;
use Cognesy\Agents\Exceptions\ToolExecutionException;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Agents\Interception\CanInterceptAgentLifecycle;
use Cognesy\Agents\Tool\Contracts\CanAccessAgentState;
use Cognesy\Agents\Tool\Contracts\CanAccessToolCall;
use Cognesy\Agents\Tool\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Tool\Contracts\ToolInterface;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Result;
use DateTimeImmutable;
use Throwable;

/**
 * Tool executor with optional lifecycle observer support.
 *
 * Executes tool calls and notifies the observer before/after each execution.
 * The observer can modify tool calls, block execution, or modify results.
 */
final readonly class ToolExecutor implements CanExecuteToolCalls
{
    private Tools $tools;
    private CanHandleEvents $events;
    private CanInterceptAgentLifecycle $interceptor;
    private bool $throwOnToolFailure;
    private bool $stopOnToolBlock;

    public function __construct(
        Tools $tools,
        CanHandleEvents $events,
        CanInterceptAgentLifecycle $interceptor,
        bool $throwOnToolFailure = false,
        bool $stopOnToolBlock = false,
    ) {
        $this->tools = $tools;
        $this->events = $events;
        $this->interceptor = $interceptor;
        $this->throwOnToolFailure = $throwOnToolFailure;
        $this->stopOnToolBlock = $stopOnToolBlock;
    }

    // MAIN API /////////////////////////////////////////////

    #[\Override]
    public function executeTools(
        ToolCalls $toolCalls,
        AgentState $state,
    ) : ToolExecutions {
        /** @var ToolExecution[] $results */
        $results = [];

        foreach ($toolCalls->each() as $toolCall) {
            // Before tool use hook
            $hookContext = $this->interceptor->intercept(HookContext::beforeToolUse($state, $toolCall));
            $toolCall = $hookContext->toolCall() ?? $toolCall;
            $state = $hookContext->state();
            if ($hookContext->isToolExecutionBlocked()) {
                $execution = $hookContext->toolExecution() ?? ToolExecution::blocked(
                    toolCall: $toolCall,
                    message: 'Tool execution blocked by beforeToolUse hook.',
                );
                $results[] = $execution;
                if ($this->stopOnToolBlock) {
                    break;
                }
                continue;
            }

            // Emit tool call started event
            $this->emitToolCallStarted($toolCall, new DateTimeImmutable());

            // Execute the tool call
            $execution = $this->executeToolCall($toolCall, $state);

            // Emit tool call completed event
            $this->emitToolCallCompleted($execution);

            // After tool use hook
            $hookContext = $this->interceptor->intercept(HookContext::afterToolUse($state, $execution));
            $execution = $hookContext->toolExecution() ?? $execution;
            $state = $hookContext->state();

            $results[] = $execution;

            $this->handleFailure($execution, $toolCall);
        }

        return new ToolExecutions(...$results);
    }

    // INTERNAL /////////////////////////////////////////////

    private function executeToolCall(ToolCall $toolCall, AgentState $state): ToolExecution {
        $startedAt = new DateTimeImmutable();
        try {
            $result = $this->doExecute($toolCall, $state);
        } catch (AgentStopException $e) {
            throw $e;
        } catch (Throwable $e) {
            $result = Result::failure(
                new ToolExecutionException(
                    message: "Exception during execution of tool '{$toolCall->name()}': " . $e->getMessage(),
                    toolCall: $toolCall,
                    previous: $e,
                ),
            );
        }
        return new ToolExecution(
            toolCall: $toolCall,
            result: $result,
            startedAt: $startedAt,
            completedAt: new DateTimeImmutable(),
        );
    }

    private function doExecute(ToolCall $toolCall, AgentState $state): Result {
        $tool = $this->prepareTool($toolCall, $state);
        $args = $toolCall->args();
        $validation = $this->validateArgs($tool, $args);
        return match (true) {
            $validation->isFailure() => $validation,
            default => $tool->use(...$args),
        };
    }

    private function prepareTool(ToolCall $toolCall, AgentState $state): ToolInterface {
        $toolName = $toolCall->name();
        $tool = $this->tools->get($toolName);

        if ($tool instanceof CanAccessAgentState) {
            $tool = $tool->withAgentState($state);
        }

        if ($tool instanceof CanAccessToolCall) {
            $tool = $tool->withToolCall($toolCall);
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

    private function handleFailure(ToolExecution $execution, ToolCall $toolCall) : void {
        $failure = $execution->result();
        if (!$failure instanceof Failure) {
            return;
        }
        if (!$this->throwOnToolFailure) {
            return;
        }
        throw match(true) {
            $failure->exception() instanceof ToolExecutionException => $failure->exception(),
            default => new ToolExecutionException(
                message: 'Error during tool execution: ' . $failure->exception()->getMessage(),
                toolCall: $toolCall,
                previous: $failure->exception(),
            ),
        };
    }

    // EVENT EMISSION ////////////////////////////////////////////

    private function emitToolCallStarted(ToolCall $toolCall, DateTimeImmutable $startedAt): void {
        $this->events->dispatch(new ToolCallStarted(
            tool: $toolCall->name(),
            args: $toolCall->args(),
            startedAt: $startedAt,
        ));
    }

    private function emitToolCallCompleted(ToolExecution $execution): void {
        $this->events->dispatch(new ToolCallCompleted(
            tool: $execution->toolCall()->name(),
            success: $execution->result()->isSuccess(),
            error: $execution->errorAsString(),
            startedAt: $execution->startedAt(),
            completedAt: $execution->completedAt(),
        ));
    }
}
