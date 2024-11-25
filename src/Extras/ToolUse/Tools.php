<?php

namespace Cognesy\Instructor\Extras\ToolUse;

use Cognesy\Instructor\Extras\ToolUse\Contracts\CanAccessContext;
use Cognesy\Instructor\Extras\ToolUse\Contracts\ToolInterface;
use Cognesy\Instructor\Extras\ToolUse\Exceptions\ToolExecutionException;
use Cognesy\Instructor\Features\LLM\Data\ToolCall;
use Cognesy\Instructor\Features\LLM\Data\ToolCalls;
use Cognesy\Instructor\Utils\Result\Result;
use DateTimeImmutable;
use Throwable;

class Tools
{
    use Traits\Tools\HandlesAccess;
    use Traits\Tools\HandlesFunctions;
    use Traits\Tools\HandlesMutation;
    use Traits\Tools\HandlesTransformation;

    private bool $parallelToolCalls;
    /** @var ToolInterface[] */
    private array $tools = [];
    private $throwOnToolFailure = true;

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

    public function useTool(ToolCall $toolCall, ToolUseContext $context) : ToolExecution {
        $startedAt = new DateTimeImmutable();
        $result = $this->execute($toolCall->name(), $toolCall->args(), $context);
        $endedAt = new DateTimeImmutable();
        $toolExecution = new ToolExecution(
            toolCall: $toolCall,
            result: $result,
            startedAt: $startedAt,
            endedAt: $endedAt,
        );
        if ($result->isFailure() && $this->throwOnToolFailure) {
            throw new ToolExecutionException($result->error());
        }
        return $toolExecution;
    }

    public function useTools(ToolCalls $toolCalls, ToolUseContext $context): ToolExecutions {
        $toolExecutions = new ToolExecutions();
        foreach ($toolCalls->all() as $toolCall) {
            $toolExecutions->add($this->useTool($toolCall, $context));
        }
        return $toolExecutions;
    }

    // INTERNAL ////////////////////////////////////////////////

    protected function execute(string $name, array $args, ToolUseContext $context): Result {
        try {
            $tool = match($this->tools[$name] instanceof CanAccessContext) {
                true => $this->tools[$name]->withContext($context),
                false => $this->tools[$name],
            };
            $result = $tool->use(...$args);
        } catch (Throwable $e) {
            return Result::failure($e);
        }
        return $result;
    }
}
