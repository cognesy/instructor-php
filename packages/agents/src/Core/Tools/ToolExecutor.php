<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Tools;

use Cognesy\Agents\Core\Collections\ToolExecutions;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Contracts\CanEmitAgentEvents;
use Cognesy\Agents\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Core\Contracts\ToolInterface;
use Cognesy\Agents\Core\Data\ToolExecution;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Events\AgentEventEmitter;
use Cognesy\Agents\Core\Exceptions\InvalidToolArgumentsException;
use Cognesy\Agents\Core\Exceptions\ToolCallBlockedException;
use Cognesy\Agents\Core\Exceptions\ToolExecutionException;
use Cognesy\Agents\Core\Lifecycle\CanObserveAgentLifecycle;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Result;
use DateTimeImmutable;

/**
 * Tool executor with optional lifecycle observer support.
 *
 * Executes tool calls and notifies the observer before/after each execution.
 * The observer can modify tool calls, block execution, or modify results.
 */
final readonly class ToolExecutor implements CanExecuteToolCalls
{
    private Tools $tools;
    private bool $throwOnToolFailure;
    private CanEmitAgentEvents $eventEmitter;
    private ?CanObserveAgentLifecycle $observer;

    public function __construct(
        Tools $tools,
        bool $throwOnToolFailure = false,
        ?CanEmitAgentEvents $eventEmitter = null,
        ?CanObserveAgentLifecycle $observer = null,
    ) {
        $this->tools = $tools;
        $this->throwOnToolFailure = $throwOnToolFailure;
        $this->eventEmitter = $eventEmitter ?? new AgentEventEmitter();
        $this->observer = $observer;
    }

    // MAIN API /////////////////////////////////////////////

    #[\Override]
    public function useTool(ToolCall $toolCall, AgentState $state): ToolExecution {
        // Before hook - can modify or block
        if ($this->observer !== null) {
            $decision = $this->observer->onBeforeToolUse($toolCall, $state);
            if ($decision->isBlocked()) {
                $this->eventEmitter->toolCallBlocked($toolCall, $decision->reason());
                throw new ToolCallBlockedException($toolCall->name(), $decision->reason());
            }
            $toolCall = $decision->toolCall();
        }

        $execution = $this->executeDirectly($toolCall, $state);

        // After hook - can modify result
        if ($this->observer !== null) {
            $execution = $this->observer->onAfterToolUse($execution, $state);
        }

        if ($this->throwOnToolFailure && $execution->result() instanceof Failure) {
            $this->throwOnFailure($execution->result());
        }

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

    public function eventEmitter(): CanEmitAgentEvents {
        return $this->eventEmitter;
    }

    public function observer(): ?CanObserveAgentLifecycle {
        return $this->observer;
    }

    // MUTATORS //////////////////////////////////////////////

    public function withTools(Tools $tools): self {
        return $this->with(tools: $tools);
    }

    public function withThrowOnToolFailure(bool $throw): self {
        return $this->with(throwOnToolFailure: $throw);
    }

    public function withEventEmitter(CanEmitAgentEvents $eventEmitter): self {
        return $this->with(eventEmitter: $eventEmitter);
    }

    public function withObserver(?CanObserveAgentLifecycle $observer): self {
        return $this->with(observer: $observer);
    }

    public function with(
        ?Tools $tools = null,
        ?bool $throwOnToolFailure = null,
        ?CanEmitAgentEvents $eventEmitter = null,
        ?CanObserveAgentLifecycle $observer = null,
    ) : self {
        return new self(
            tools: $tools ?? $this->tools,
            throwOnToolFailure: $throwOnToolFailure ?? $this->throwOnToolFailure,
            eventEmitter: $eventEmitter ?? $this->eventEmitter,
            observer: $observer ?? $this->observer,
        );
    }

    // INTERNAL /////////////////////////////////////////////

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
