<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Tasks;

use Cognesy\Agents\Hooks\Contracts\HookInterface;
use Cognesy\Agents\Hooks\Data\HookContext;

final readonly class PersistTasksHook implements HookInterface
{
    #[\Override]
    public function handle(HookContext $context): HookContext
    {
        $state = $context->state();
        $step = $state->currentStep();

        if ($step === null) {
            return $context;
        }

        $executions = $step->toolExecutions();
        if (!$executions->hasExecutions()) {
            return $context;
        }

        $tasks = null;
        foreach ($executions->all() as $execution) {
            if ($execution->name() !== TodoWriteTool::TOOL_NAME) {
                continue;
            }

            if ($execution->hasError()) {
                continue;
            }

            $tasks = $this->extractTasks($execution->value());
        }

        if ($tasks === null) {
            return $context;
        }

        $nextState = $state->withMetadata(TodoWriteTool::metadataKey(), $tasks);
        return $context->withState($nextState);
    }

    private function extractTasks(mixed $result): ?array
    {
        if ($result instanceof TodoWriteResult) {
            return $result->tasks;
        }

        if (is_array($result)) {
            $tasks = $result['tasks'] ?? null;
            return is_array($tasks) ? $tasks : null;
        }

        return null;
    }
}
