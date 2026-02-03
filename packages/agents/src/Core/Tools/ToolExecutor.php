<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Tools;

use Cognesy\Agents\Core\Collections\ToolExecutions;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Core\Contracts\ToolInterface;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\ToolExecution;
use Cognesy\Agents\Events\CanEmitAgentEvents;
use Cognesy\Agents\Exceptions\InvalidToolArgumentsException;
use Cognesy\Agents\Exceptions\ToolExecutionException;
use Cognesy\Agents\Hooks\Data\HookContext;
use Cognesy\Agents\Hooks\Interceptors\CanInterceptAgentLifecycle;
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
    private CanEmitAgentEvents $eventEmitter;
    private CanInterceptAgentLifecycle $interceptor;
    private bool $throwOnToolFailure;
    private bool $stopOnToolBlock;

    public function __construct(
        Tools $tools,
        CanEmitAgentEvents $eventEmitter,
        CanInterceptAgentLifecycle $interceptor,
        bool $throwOnToolFailure = false,
        bool $stopOnToolBlock = false,
    ) {
        $this->tools = $tools;
        $this->eventEmitter = $eventEmitter;
        $this->interceptor = $interceptor;
        $this->throwOnToolFailure = $throwOnToolFailure;
        $this->stopOnToolBlock = $stopOnToolBlock;
    }

    // MAIN API /////////////////////////////////////////////

    public function executeTools(
        ToolCalls $toolCalls,
        AgentState $state,
    ) : ToolExecutions {
        $executions = new ToolExecutions();
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
                $executions = $executions->withAddedExecution($execution);
                if ($this->stopOnToolBlock) {
                    break;
                }
                continue;
            }

            // Emit tool call started event
            $this->eventEmitter->toolCallStarted($toolCall, new DateTimeImmutable());

            // Execute the tool call
            $execution = $this->executeToolCall($toolCall, $state); // ToolExecution

            // Emit tool call completed event
            $this->eventEmitter->toolCallCompleted($execution);

            // After tool use hook
            $hookContext = $this->interceptor->intercept(HookContext::afterToolUse($state, $execution));
            $execution = $hookContext->toolExecution() ?? $execution;
            $state = $hookContext->state();

            // Add execution to the collection
            $executions = $executions->withAddedExecution($execution);

            $this->handleFailure($execution, $toolCall);
        }
        return $executions;
    }

    // INTERNAL /////////////////////////////////////////////

    private function executeToolCall(ToolCall $toolCall, AgentState $state): ToolExecution {
        $startedAt = new DateTimeImmutable();
        try {
            $result = $this->doExecute($toolCall, $state);
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
        return match(true) {
            // Inject agent state if tool may need it
            $tool instanceof CanAccessAgentState => $tool->withAgentState($state),
            default => $tool,
        };
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
        if ($this->throwOnToolFailure) {
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
}
