<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\StateProcessors;

use Cognesy\Addons\Agent\Collections\TaskList;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Step\HasStepToolExecutions;
use Cognesy\Addons\Agent\Tools\TodoWriteTool;
use Cognesy\Addons\StepByStep\StateProcessing\CanProcessAnyState;

/**
 * Extracts tasks from TodoWriteTool executions and persists them in AgentState metadata.
 *
 * @implements CanProcessAnyState<AgentState>
 */
final class PersistTasksProcessor implements CanProcessAnyState
{
    #[\Override]
    public function canProcess(object $state): bool {
        return $state instanceof AgentState;
    }

    #[\Override]
    public function process(object $state, ?callable $next = null): object {
        $newState = $next ? $next($state) : $state;

        assert($newState instanceof AgentState);

        $currentStep = $newState->currentStep();
        if ($currentStep === null) {
            return $newState;
        }

        if (!($currentStep instanceof HasStepToolExecutions)) {
            return $newState;
        }

        $toolExecutions = $currentStep->toolExecutions();
        if (!$toolExecutions->hasExecutions()) {
            return $newState;
        }

        foreach ($toolExecutions->all() as $execution) {
            if ($execution->toolCall()->name() !== 'todo_write') {
                continue;
            }

            if ($execution->hasError()) {
                continue;
            }

            $result = $execution->value();
            if (!is_array($result) || !isset($result['tasks'])) {
                continue;
            }

            $taskList = TaskList::fromArray($result['tasks']);
            $metadata = $newState->metadata()->withKeyValue(
                TodoWriteTool::metadataKey(),
                $taskList->toArray()
            );

            $newState = $newState->with(variables: $metadata);
        }

        return $newState;
    }
}
