<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse;

use Cognesy\Addons\ToolUse\Collections\ToolExecutions;
use Cognesy\Addons\ToolUse\Collections\Tools;
use Cognesy\Addons\ToolUse\Contracts\CanAccessAnyState;
use Cognesy\Addons\ToolUse\Contracts\ToolInterface;
use Cognesy\Addons\ToolUse\Data\ToolExecution;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Events\ToolCallCompleted;
use Cognesy\Addons\ToolUse\Events\ToolCallStarted;
use Cognesy\Addons\ToolUse\Exceptions\InvalidToolArgumentsException;
use Cognesy\Addons\ToolUse\Exceptions\ToolExecutionException;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Result;
use DateTimeImmutable;

final readonly class ToolExecutor
{
    private Tools $tools;
    private bool $throwOnToolFailure;
    private CanHandleEvents $events;

    public function __construct(
        Tools $tools,
        bool $throwOnToolFailure = false,
        ?CanHandleEvents $events = null,
    ) {
        $this->tools = $tools;
        $this->throwOnToolFailure = $throwOnToolFailure;
        $this->events = EventBusResolver::using($events);
    }

    // MAIN API /////////////////////////////////////////////

    public function useTool(ToolCall $toolCall, ToolUseState $state): ToolExecution {
        $startedAt = new DateTimeImmutable();
        $this->emitToolCallStarted($toolCall, $startedAt);
        $result = $this->execute($toolCall, $state);
        $endedAt = new DateTimeImmutable();
        $toolExecution = new ToolExecution(
            toolCall: $toolCall,
            result: $result,
            startedAt: $startedAt,
            endedAt: $endedAt,
        );
        $this->emitToolCallCompleted($toolExecution);

        if ($result instanceof Failure && $this->throwOnToolFailure) {
            $this->throwOnFailure($result);
        }

        return $toolExecution;
    }

    public function useTools(ToolCalls $toolCalls, ToolUseState $state): ToolExecutions {
        $executions = new ToolExecutions();
        foreach ($toolCalls->all() as $toolCall) {
            $executions = $executions->withAddedExecution($this->useTool($toolCall, $state));
        }
        return $executions;
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
            events: $this->events,
        );
    }

    public function withThrowOnToolFailure(bool $throw): self {
        return new self(
            tools: $this->tools,
            throwOnToolFailure: $throw,
            events: $this->events,
        );
    }

    public function withEventHandler(CanHandleEvents $events): self {
        return new self(
            tools: $this->tools,
            throwOnToolFailure: $this->throwOnToolFailure,
            events: EventBusResolver::using($events),
        );
    }

    // INTERNAL /////////////////////////////////////////////

    private function execute(ToolCall $toolCall, ToolUseState $state): Result {
        $tool = $this->prepareTool($toolCall->name(), $state);
        $args = $toolCall->args();

        $validation = $this->validateArgs($tool, $args);
        if ($validation->isFailure()) {
            return $validation;
        }

        return $tool->use(...$args);
    }

    private function prepareTool(string $name, ToolUseState $state): ToolInterface {
        $tool = $this->tools->get($name);
        if ($tool instanceof CanAccessAnyState) {
            // Since CanAccessAnyState extends ToolInterface, withState preserves ToolInterface
            return $tool->withState($state);
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

    // EVENTS ////////////////////////////////////////////////////

    private function emitToolCallStarted(ToolCall $toolCall, DateTimeImmutable $startedAt): void {
        $this->events->dispatch(new ToolCallStarted([
            'tool' => $toolCall->name(),
            'args' => $toolCall->args(),
            'at' => $startedAt->format(DATE_ATOM),
        ]));
    }

    private function emitToolCallCompleted(ToolExecution $toolExecution): void {
        $error = null;
        if ($toolExecution->result()->isFailure()) {
            $errorValue = $toolExecution->result()->error();
            $error = match(true) {
                is_string($errorValue) => $errorValue,
                $errorValue instanceof \Throwable => $errorValue->getMessage(),
                is_object($errorValue) && method_exists($errorValue, '__toString') => (string) $errorValue,
                default => is_scalar($errorValue) ? (string) $errorValue : null,
            };
        }

        $this->events->dispatch(new ToolCallCompleted([
            'tool' => $toolExecution->toolCall()->name(),
            'success' => $toolExecution->result()->isSuccess(),
            'error' => $error,
            'startedAt' => $toolExecution->startedAt()->format(DATE_ATOM),
            'endedAt' => $toolExecution->endedAt()->format(DATE_ATOM),
        ]));
    }
}
