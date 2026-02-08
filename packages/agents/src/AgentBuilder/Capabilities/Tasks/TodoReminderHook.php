<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Tasks;

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Hooks\HookContext;
use Cognesy\Agents\Hooks\HookInterface;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

/**
 * Hook that injects gentle TodoWrite reminders to keep planning visible.
 */
final readonly class TodoReminderHook implements HookInterface
{
    private const INITIAL_MARKER = '<todo-reminder-initial>';
    private const NAG_MARKER = '<todo-reminder-nag>';

    private const INITIAL_REMINDER = self::INITIAL_MARKER
        . "\n<reminder>Use todo_write for multi-step tasks.</reminder>";

    private const NAG_REMINDER = self::NAG_MARKER
        . "\n<reminder>%d+ steps without todo_write. Please update your task list.</reminder>";

    public function __construct(private TodoPolicy $policy) {}

    #[\Override]
    public function handle(HookContext $context): HookContext
    {
        $state = $context->state();
        $messages = $state->messages();

        if ($state->stepCount() === 0 && !$this->hasMarker($messages, self::INITIAL_MARKER)) {
            $messages = $messages->appendMessage(Message::asUser(self::INITIAL_REMINDER));
            $state = $state->withMessages($messages);
        }

        $nagAfterSteps = $this->policy->reminderEverySteps;
        if ($nagAfterSteps > 0) {
            $stepsSinceTodo = $this->stepsSinceTodo($state);
            if ($stepsSinceTodo !== null && $stepsSinceTodo >= $nagAfterSteps) {
                $messages = $state->messages();
                $reminder = sprintf(self::NAG_REMINDER, $nagAfterSteps);
                $messages = $messages->appendMessage(Message::asUser($reminder));
                $state = $state->withMessages($messages);
            }
        }

        return $context->withState($state);
    }

    private function stepsSinceTodo(AgentState $state): int {
        $steps = $state->steps()->all();
        $lastIndex = -1;
        foreach ($steps as $index => $step) {
            if ($this->stepHasTodo($step)) {
                $lastIndex = (int) $index;
            }
        }

        if ($lastIndex === -1) {
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
