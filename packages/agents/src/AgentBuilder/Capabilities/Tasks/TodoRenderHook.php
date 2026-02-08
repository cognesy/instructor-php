<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Tasks;

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Hooks\HookContext;
use Cognesy\Agents\Hooks\HookInterface;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

/**
 * Hook that renders task list periodically.
 */
final readonly class TodoRenderHook implements HookInterface
{
    private const MARKER = '<todo-render>';

    public function __construct(private TodoPolicy $policy) {}

    #[\Override]
    public function handle(HookContext $context): HookContext
    {
        $state = $context->state();
        if ($this->policy->renderEverySteps <= 0) {
            return $context;
        }

        if ($state->stepCount() === 0 || $state->stepCount() % $this->policy->renderEverySteps !== 0) {
            return $context;
        }

        if ($this->currentStepHasTodo($state)) {
            return $context;
        }

        $tasks = $state->metadata()->get(TodoWriteTool::metadataKey(), []);
        if (!is_array($tasks) || $tasks === []) {
            return $context;
        }

        $taskList = TaskList::fromArray($tasks, $this->policy);
        $rendered = implode("\n", [
            self::MARKER,
            $taskList->render(),
            $taskList->renderSummary(),
            '</todo-render>',
        ]);

        $messages = $state->messages();
        if ($this->hasMarker($messages, self::MARKER)) {
            return $context;
        }

        $messages = $messages->appendMessage(Message::asUser($rendered));
        return $context->withState($state->withMessages($messages));
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
