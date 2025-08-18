<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse;

use Cognesy\Addons\ToolUse\Contracts\CanAccessToolUseState;
use Cognesy\Addons\ToolUse\Contracts\ToolInterface;
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

    public function useTool(ToolCall $toolCall, ToolUseState $state) : ToolExecution {
        $startedAt = new DateTimeImmutable();
        $result = $this->execute($toolCall->name(), $toolCall->args(), $state);
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

    public function useTools(ToolCalls $toolCalls, ToolUseState $state): ToolExecutions {
        $toolExecutions = new ToolExecutions();
        foreach ($toolCalls->all() as $toolCall) {
            $toolExecutions->add($this->useTool($toolCall, $state));
        }
        return $toolExecutions;
    }

    // INTERNAL ////////////////////////////////////////////////

    protected function execute(string $name, array $args, ToolUseState $state): Result {
        try {
            $tool = match($this->tools[$name] instanceof CanAccessToolUseState) {
                true => $this->tools[$name]->withState($state),
                false => $this->tools[$name],
            };
            $result = $tool->use(...$args);
        } catch (Throwable $e) {
            return Result::failure($e);
        }
        return $result;
    }
}
