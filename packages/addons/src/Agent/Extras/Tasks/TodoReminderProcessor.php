<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Extras\Tasks;

use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Data\AgentStep;
use Cognesy\Addons\StepByStep\StateProcessing\CanProcessAnyState;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

/**
 * Injects gentle TodoWrite reminders to keep planning visible.
 *
 * @implements CanProcessAnyState<AgentState>
 */
final readonly class TodoReminderProcessor implements CanProcessAnyState
{
    private const INITIAL_MARKER = '<todo-reminder-initial>';
    private const NAG_MARKER = '<todo-reminder-nag>';

    private const INITIAL_REMINDER = self::INITIAL_MARKER
        . "\n<reminder>Use todo_write for multi-step tasks.</reminder>";

    private const NAG_REMINDER = self::NAG_MARKER
        . "\n<reminder>%d+ steps without todo_write. Please update your task list.</reminder>";

    public function __construct(private TodoPolicy $policy) {}

    #[\Override]
    public function canProcess(object $state): bool {
        return $state instanceof AgentState;
    }

    #[\Override]
    public function process(object $state, ?callable $next = null): object {
        $newState = $state;
        assert($newState instanceof AgentState);

        $messages = $newState->messages();
        if ($newState->stepCount() === 0 && !$this->hasMarker($messages, self::INITIAL_MARKER)) {
            $messages = $messages->appendMessage(Message::asUser(self::INITIAL_REMINDER));
            $newState = $newState->withMessages($messages);
        }

        $nagAfterSteps = $this->policy->reminderEverySteps;
        if ($nagAfterSteps > 0) {
            $stepsSinceTodo = $this->stepsSinceTodo($newState);
            if ($stepsSinceTodo !== null && $stepsSinceTodo >= $nagAfterSteps) {
                $messages = $newState->messages();
                $reminder = sprintf(self::NAG_REMINDER, $nagAfterSteps);
                $messages = $messages->appendMessage(Message::asUser($reminder));
                $newState = $newState->withMessages($messages);
            }
        }

        return $next ? $next($newState) : $newState;
    }

    private function stepsSinceTodo(AgentState $state): ?int {
        $steps = $state->steps()->all();
        $lastIndex = null;
        foreach ($steps as $index => $step) {
            if ($this->stepHasTodo($step)) {
                $lastIndex = $index;
            }
        }

        if ($lastIndex === null) {
            return count($steps);
        }

        return count($steps) - 1 - $lastIndex;
    }

    private function stepHasTodo(AgentStep $step): bool {
        foreach ($step->toolExecutions()->all() as $execution) {
            if ($execution->toolCall()->name() === 'todo_write') {
                return true;
            }
        }
        return false;
    }

    private function hasMarker(Messages $messages, string $marker): bool {
        foreach ($messages->toArray() as $message) {
            $content = $message['content'] ?? '';
            if (is_string($content) && str_contains($content, $marker)) {
                return true;
            }
        }
        return false;
    }
}
