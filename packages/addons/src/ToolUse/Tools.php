<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse;

use Cognesy\Addons\ToolUse\Contracts\CanAccessToolUseState;
use Cognesy\Addons\ToolUse\Contracts\ToolInterface;
use Cognesy\Addons\ToolUse\Contracts\ToolsObserver;
use Cognesy\Addons\ToolUse\Data\ToolExecution;
use Cognesy\Addons\ToolUse\Data\ToolExecutions;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Events\ToolCallCompleted;
use Cognesy\Addons\ToolUse\Events\ToolCallStarted;
use Cognesy\Addons\ToolUse\Exceptions\ToolExecutionException;
use Cognesy\Addons\ToolUse\Traits\Tools\HandlesAccess;
use Cognesy\Addons\ToolUse\Traits\Tools\HandlesFunctions;
use Cognesy\Addons\ToolUse\Traits\Tools\HandlesMutation;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Events\Traits\HandlesEvents;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\ToolCalls;
use Cognesy\Utils\Result\Result;
use DateTimeImmutable;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

class Tools
{
    use HandlesEvents;
    use HandlesAccess;
    use HandlesFunctions;
    use HandlesMutation;

    private bool $parallelToolCalls;
    /** @var ToolInterface[] */
    private array $tools = [];
    private bool $throwOnToolFailure = false;
    private ?ToolsObserver $observer = null;

    /**
     * @param ToolInterface[] $tools
     */
    public function __construct(
        array $tools = [],
        bool $parallelToolCalls = false
    ) {
        // default event bus to avoid requiring explicit wiring in tests
        $this->events = EventBusResolver::default();
        foreach ($tools as $tool) {
            $this->addTool($tool);
        }
        $this->parallelToolCalls = $parallelToolCalls;
    }

    public function withEventHandler(CanHandleEvents|EventDispatcherInterface $events): self {
        $this->events = EventBusResolver::using($events);
        return $this;
    }

    public function useTool(ToolCall $toolCall, ToolUseState $state) : ToolExecution {
        $startedAt = new DateTimeImmutable();
        $this->dispatch(new ToolCallStarted([
            'tool' => $toolCall->name(),
            'args' => $toolCall->args(),
            'at' => $startedAt->format(DATE_ATOM),
        ]));
        if ($this->observer) { $this->observer->onToolStart($state, $toolCall); }
        $result = $this->execute($toolCall->name(), $toolCall->args(), $state);
        $endedAt = new DateTimeImmutable();
        $toolExecution = new ToolExecution(
            toolCall: $toolCall,
            result: $result,
            startedAt: $startedAt,
            endedAt: $endedAt,
        );
        if ($this->observer) { $this->observer->onToolEnd($state, $toolExecution); }
        $this->dispatch(new ToolCallCompleted([
            'tool' => $toolCall->name(),
            'success' => $result->isSuccess(),
            'error' => $result->isFailure() ? (string)$result->error() : null,
            'startedAt' => $startedAt->format(DATE_ATOM),
            'endedAt' => $endedAt->format(DATE_ATOM),
        ]));
        if ($result->isFailure() && $this->throwOnToolFailure) {
            $err = $result->error();
            throw new ToolExecutionException(is_object($err) ? ($err->getMessage() ?? 'Tool failed') : (string)$err, 0, $err instanceof \Throwable ? $err : null);
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

    // INTERNAL ////////////////////////////////////////////////

    public function withThrowOnToolFailure(bool $throw): self {
        $this->throwOnToolFailure = $throw;
        return $this;
    }

    public function withObserver(ToolsObserver $observer) : self {
        $this->observer = $observer;
        return $this;
    }

    public function toToolSchema() : array {
        $schema = [];
        foreach ($this->tools as $tool) {
            $schema[] = $tool->toToolSchema();
        }
        return $schema;
    }

    // INTERNAL ////////////////////////////////////////////////

    protected function execute(string $name, array $args, ToolUseState $state): Result {
        try {
            $tool = match($this->tools[$name] instanceof CanAccessToolUseState) {
                true => $this->tools[$name]->withState($state),
                false => $this->tools[$name],
            };
            // Pragmatic validation: ensure all required parameters are present
            $schema = $tool->toToolSchema()['function']['parameters'] ?? [];
            $required = $schema['required'] ?? [];
            if (!empty($required)) {
                $missing = [];
                foreach ($required as $param) {
                    if (!array_key_exists($param, $args)) {
                        $missing[] = $param;
                    }
                }
                if (!empty($missing)) {
                    return Result::failure(new \Cognesy\Addons\ToolUse\Exceptions\InvalidToolArgumentsException(
                        message: "Missing required parameters: " . implode(', ', $missing)
                    ));
                }
            }
            $result = $tool->use(...$args);
        } catch (Throwable $e) {
            return Result::failure($e);
        }
        return $result;
    }
}
