<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Capabilities\Tasks;

use Cognesy\Addons\Agent\Core\Contracts\HasStepToolExecutions;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\StepByStep\StateProcessing\CanProcessAnyState;

class PersistTasksProcessor implements CanProcessAnyState
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
            if ($result instanceof TodoResult) {
                $result = $result->toArray();
            }
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
