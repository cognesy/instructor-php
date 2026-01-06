<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Capabilities\Tasks;

use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\StepByStep\StateProcessing\CanProcessAnyState;

class TodoRenderProcessor implements CanProcessAnyState
{
    private const MARKER = '<todo-render>';

    public function __construct(private TodoPolicy $policy) {}

    #[\Override]
    public function canProcess(object $state): bool {
        return $state instanceof AgentState;
    }

    #[\Override]
    public function process(object $state, ?callable $next = null): object {
        $newState = $next ? $next($state) : $state;
        assert($newState instanceof AgentState);

        if ($this->policy->renderEverySteps <= 0) {
            return $newState;
        }

        if ($newState->stepCount() === 0 || $newState->stepCount() % $this->policy->renderEverySteps !== 0) {
            return $newState;
        }

        if ($this->currentStepHasTodo($newState)) {
            return $newState;
        }

        $tasks = $newState->metadata()->get(TodoWriteTool::metadataKey(), []);
        if (!is_array($tasks) || $tasks === []) {
            return $newState;
        }

        $taskList = TaskList::fromArray($tasks, $this->policy);
        $rendered = implode("\n", [
            self::MARKER,
            $taskList->render(),
            $taskList->renderSummary(),
            '</todo-render>',
        ]);

        $messages = $newState->messages();
        if ($this->hasMarker($messages, self::MARKER)) {
            return $newState;
        }

        $messages = $messages->appendMessage(Message::asUser($rendered));
        return $newState->withMessages($messages);
    }

    private function currentStepHasTodo(AgentState $state): bool {
        $step = $state->currentStep();
        if ($step === null) {
            return false;
        }
        foreach ($step->toolExecutions()->all() as $execution) {
            if ($execution->toolCall()->name() === 'todo_write') {
                return true;
            }
        }
        return false;
    }

    private function hasMarker(Messages $messages, string $marker): bool {
        $array = $messages->toArray();
        if ($array === []) {
            return false;
        }
        $last = $array[array_key_last($array)];
        $content = $last['content'] ?? '';
        return is_string($content) && str_contains($content, $marker);
    }
}
