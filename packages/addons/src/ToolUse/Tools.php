<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse;

use Cognesy\Addons\ToolUse\Contracts\CanAccessToolUseState;
use Cognesy\Addons\ToolUse\Contracts\ToolInterface;
use Cognesy\Addons\ToolUse\Contracts\ToolsObserver;
use Cognesy\Addons\ToolUse\Exceptions\ToolExecutionException;
use Cognesy\Addons\ToolUse\Traits\Tools\HandlesAccess;
use Cognesy\Addons\ToolUse\Traits\Tools\HandlesFunctions;
use Cognesy\Addons\ToolUse\Traits\Tools\HandlesMutation;
use Cognesy\Addons\ToolUse\Traits\Tools\HandlesTransformation;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\ToolCalls;
use Cognesy\Utils\Result\Result;
use DateTimeImmutable;
use Throwable;

class Tools
{
    use HandlesAccess;
    use HandlesFunctions;
    use HandlesMutation;
    use HandlesTransformation;

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
        foreach ($tools as $tool) {
            $this->addTool($tool);
        }
        $this->parallelToolCalls = $parallelToolCalls;
    }

    public function useTool(ToolCall $toolCall, ToolUseState $state) : ToolExecution {
        $startedAt = new DateTimeImmutable();
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
        if ($result->isFailure() && $this->throwOnToolFailure) {
            throw new ToolExecutionException($result->error());
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
