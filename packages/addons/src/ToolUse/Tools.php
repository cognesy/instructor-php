<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse;

use Cognesy\Addons\ToolUse\Contracts\CanAccessAnyState;
use Cognesy\Addons\ToolUse\Contracts\ToolInterface;
use Cognesy\Addons\ToolUse\Data\Collections\ToolExecutions;
use Cognesy\Addons\ToolUse\Data\ToolExecution;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Events\ToolCallCompleted;
use Cognesy\Addons\ToolUse\Events\ToolCallStarted;
use Cognesy\Addons\ToolUse\Exceptions\InvalidToolArgumentsException;
use Cognesy\Addons\ToolUse\Exceptions\ToolExecutionException;
use Cognesy\Addons\ToolUse\Traits\Tools\HandlesAccess;
use Cognesy\Addons\ToolUse\Traits\Tools\HandlesMutation;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\ToolCalls;
use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Result;
use DateTimeImmutable;

final readonly class Tools
{
    use HandlesAccess;
    use HandlesMutation;

    /** @var ToolInterface[] */
    private array $tools;
    private bool $parallelToolCalls;
    private bool $throwOnToolFailure;
    private CanHandleEvents $events;

    /**
     * @param ToolInterface[] $tools
     */
    public function __construct(
        array $tools = [],
        bool $parallelToolCalls = false,
        bool $throwOnToolFailure = false,
        ?CanHandleEvents $events = null,
    ) {
        $this->events = EventBusResolver::using($events);
        $toolsArray = [];
        foreach ($tools as $tool) {
            $toolsArray[$tool->name()] = $tool;
        }
        $this->tools = $toolsArray;
        $this->throwOnToolFailure = $throwOnToolFailure;
        $this->parallelToolCalls = $parallelToolCalls;
    }

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
        $toolExecutions = new ToolExecutions();
        foreach ($toolCalls->all() as $toolCall) {
            $toolExecutions->add($this->useTool($toolCall, $state));
        }
        return $toolExecutions;
    }

    public function toToolSchema(): array {
        $schema = [];
        foreach ($this->tools as $tool) {
            $schema[] = $tool->toToolSchema();
        }
        return $schema;
    }

    // INTERNAL ////////////////////////////////////////////////

    protected function execute(ToolCall $toolCall, ToolUseState $state): Result {
        $name = $toolCall->name();
        $args = $toolCall->args();
        $tool = $this->prepareTool($name, $state);

        $result = $this->validateArgs($tool, $args);
        if ($result->isFailure()) {
            return $result;
        }

        return $tool->use(...$args);
    }

    protected function prepareTool(string $name, ToolUseState $state): ToolInterface {
        if (!isset($this->tools[$name])) {
            throw new \InvalidArgumentException("Tool '{$name}' not found");
        }
        
        return match ($this->tools[$name] instanceof CanAccessAnyState) {
            true => $this->tools[$name]->withState($state),
            false => $this->tools[$name],
        };
    }

    protected function validateArgs(ToolInterface $tool, array $args): Result {
        $toolSchema = $tool->toToolSchema();
        $parameters = $toolSchema['function']['parameters'] ?? [];

        $required = $parameters['required'] ?? [];
        if (empty($required)) { return Result::success(null); }
        $missing = [];
        foreach ($required as $param) {
            if (!array_key_exists($param, $args)) {
                $missing[] = $param;
            }
        }
        if (empty($missing)) { return Result::success(null); }
        return Result::failure(new InvalidToolArgumentsException(
            message: "Missing required parameters: " . implode(', ', $missing),
        ));
    }

    private function emitToolCallStarted(ToolCall $toolCall, DateTimeImmutable $startedAt): void {
        $this->events->dispatch(new ToolCallStarted([
            'tool' => $toolCall->name(),
            'args' => $toolCall->args(),
            'at' => $startedAt->format(DATE_ATOM),
        ]));
    }

    private function emitToolCallCompleted(ToolExecution $toolExecution): void {
        $this->events->dispatch(new ToolCallCompleted([
            'tool' => $toolExecution->toolCall()->name(),
            'success' => $toolExecution->result()->isSuccess(),
            'error' => $toolExecution->result()->isFailure() ? (string) $toolExecution->result()->error() : null,
            'startedAt' => $toolExecution->startedAt()->format(DATE_ATOM),
            'endedAt' => $toolExecution->endedAt()->format(DATE_ATOM),
        ]));
    }

    private function throwOnFailure(Failure $result) : void {
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
