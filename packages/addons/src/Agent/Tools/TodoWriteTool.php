<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Tools;

use Cognesy\Addons\Agent\Collections\TaskList;
use Cognesy\Addons\Agent\Contracts\CanAccessAnyState;
use Cognesy\Addons\Agent\Data\Task;
use Cognesy\Addons\Agent\Enums\TaskStatus;
use InvalidArgumentException;

class TodoWriteTool extends BaseTool implements CanAccessAnyState
{
    private const METADATA_KEY = 'tasks';

    private ?object $state = null;

    public function __construct() {
        parent::__construct(
            name: 'todo_write',
            description: 'Create or update a structured task list to track progress. Each task needs: content (what to do), status (pending/in_progress/completed), and activeForm (present tense action shown during execution). Maximum 20 tasks, only 1 can be in_progress at a time.',
        );
    }

    #[\Override]
    public function withState(object $state): self {
        $clone = clone $this;
        $clone->state = $state;
        return $clone;
    }

    #[\Override]
    public function __invoke(mixed ...$args): array {
        $todos = $args['todos'] ?? $args[0] ?? [];
        $tasks = $this->parseTasks($todos);
        $taskList = TaskList::empty()->withTasks($tasks);

        return [
            'success' => true,
            'tasks' => $taskList->toArray(),
            'summary' => $taskList->renderSummary(),
            'rendered' => $taskList->render(),
        ];
    }

    /**
     * @param list<array{content: string, status: string, activeForm: string}> $todos
     * @return list<Task>
     */
    private function parseTasks(array $todos): array {
        $tasks = [];

        foreach ($todos as $index => $todo) {
            $this->validateTodoItem($todo, $index);

            $tasks[] = new Task(
                content: trim($todo['content']),
                status: TaskStatus::from($todo['status']),
                activeForm: trim($todo['activeForm']),
            );
        }

        return $tasks;
    }

    private function validateTodoItem(array $todo, int $index): void {
        $position = $index + 1;

        if (!isset($todo['content']) || trim($todo['content']) === '') {
            throw new InvalidArgumentException(
                "Task #{$position}: 'content' is required and cannot be empty"
            );
        }

        if (!isset($todo['status'])) {
            throw new InvalidArgumentException(
                "Task #{$position}: 'status' is required (pending/in_progress/completed)"
            );
        }

        $validStatuses = ['pending', 'in_progress', 'completed'];
        if (!in_array($todo['status'], $validStatuses, true)) {
            throw new InvalidArgumentException(
                "Task #{$position}: 'status' must be one of: " . implode(', ', $validStatuses)
            );
        }

        if (!isset($todo['activeForm']) || trim($todo['activeForm']) === '') {
            throw new InvalidArgumentException(
                "Task #{$position}: 'activeForm' is required (present tense action, e.g., 'Running tests')"
            );
        }
    }

    public static function metadataKey(): string {
        return self::METADATA_KEY;
    }

    #[\Override]
    public function toToolSchema(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => $this->description(),
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'todos' => [
                            'type' => 'array',
                            'description' => 'List of tasks to create or update',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'content' => [
                                        'type' => 'string',
                                        'description' => 'What needs to be done',
                                    ],
                                    'status' => [
                                        'type' => 'string',
                                        'enum' => ['pending', 'in_progress', 'completed'],
                                        'description' => 'Task status',
                                    ],
                                    'activeForm' => [
                                        'type' => 'string',
                                        'description' => 'Present tense action (e.g., "Running tests")',
                                    ],
                                ],
                                'required' => ['content', 'status', 'activeForm'],
                            ],
                        ],
                    ],
                    'required' => ['todos'],
                ],
            ],
        ];
    }
}
