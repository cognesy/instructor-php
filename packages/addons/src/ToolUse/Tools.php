<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse;

use Cognesy\Addons\ToolUse\Contracts\CanAccessContext;
use Cognesy\Addons\ToolUse\Contracts\ToolInterface;
use Cognesy\Addons\ToolUse\Exceptions\ToolExecutionException;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\ToolCalls;
use Cognesy\Utils\Result\Result;
use DateTimeImmutable;
use Throwable;

class Tools
{
    use \Cognesy\Addons\ToolUse\Traits\Tools\HandlesAccess;
    use \Cognesy\Addons\ToolUse\Traits\Tools\HandlesFunctions;
    use \Cognesy\Addons\ToolUse\Traits\Tools\HandlesMutation;
    use \Cognesy\Addons\ToolUse\Traits\Tools\HandlesTransformation;

    private bool $parallelToolCalls;
    /** @var ToolInterface[] */
    private array $tools = [];
    private $throwOnToolFailure = true;

    /**
     * @param \Cognesy\Addons\ToolUse\Contracts\ToolInterface[] $tools
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
