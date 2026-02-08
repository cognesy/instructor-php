<?php declare(strict_types=1);

namespace Cognesy\Addons\AgentBuilder\Capabilities\Tasks;

use Cognesy\Addons\Agent\Core\Contracts\CanAccessAnyState;
use Cognesy\Addons\Agent\Tools\BaseTool;
use InvalidArgumentException;

class TodoWriteTool extends BaseTool implements CanAccessAnyState
{
    private const METADATA_KEY = 'tasks';

    /** @phpstan-ignore property.onlyWritten */
    private ?object $state = null;
    private TodoPolicy $policy;

    public function __construct(?TodoPolicy $policy = null) {
        parent::__construct(
            name: 'todo_write',
            description: <<<'DESC'
Create or update a task list to track progress. Replaces the entire list each call.

Example - initial planning:
todos: [
  {"content": "Read config files", "status": "in_progress", "activeForm": "Reading config files"},
  {"content": "Implement feature", "status": "pending", "activeForm": "Implementing feature"},
  {"content": "Write tests", "status": "pending", "activeForm": "Writing tests"}
]

Example - marking progress:
todos: [
  {"content": "Read config files", "status": "completed", "activeForm": "Reading config files"},
  {"content": "Implement feature", "status": "in_progress", "activeForm": "Implementing feature"},
  {"content": "Write tests", "status": "pending", "activeForm": "Writing tests"}
]

Rules: Only 1 task can be in_progress. Mark completed immediately when done. Max 20 tasks.
DESC,
        );

        $this->policy = $policy ?? new TodoPolicy();
    }

    #[\Override]
    public function withState(object $state): self {
        $clone = clone $this;
        $clone->state = $state;
        return $clone;
    }

    #[\Override]
    public function __invoke(mixed ...$args): TodoResult {
        $todos = $args['todos'] ?? $args[0] ?? [];
        $tasks = $this->parseTasks($todos);
        $taskList = TaskList::empty($this->policy)->withTasks($tasks);

        return new TodoResult(
            success: true,
            tasks: $taskList->toArray(),
            summary: $taskList->renderSummary(),
            rendered: $taskList->render(),
        );
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
