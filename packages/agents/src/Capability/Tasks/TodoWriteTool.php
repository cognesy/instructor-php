<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Tasks;

use Cognesy\Agents\Tool\Tools\SimpleTool;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;
use InvalidArgumentException;

final class TodoWriteTool extends SimpleTool
{
    public const TOOL_NAME = 'todo_write';

    public function __construct()
    {
        parent::__construct(new TodoWriteToolDescriptor());
    }

    public static function metadataKey(): string
    {
        return 'tasks';
    }

    #[\Override]
    public function __invoke(mixed ...$args): TodoWriteResult
    {
        $todos = $this->extractTodos($args);
        $taskList = $this->buildTaskList($todos);

        return new TodoWriteResult(
            success: true,
            tasks: $taskList->toArray(),
            rendered: $taskList->render(),
            summary: $taskList->renderSummary(),
        );
    }

    #[\Override]
    public function toToolSchema(): array
    {
        return ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::array(
                        name: 'todos',
                        itemSchema: JsonSchema::object()
                            ->withProperties([
                                JsonSchema::string('content', 'Short task summary.'),
                                JsonSchema::enum('status', TaskStatus::values()),
                                JsonSchema::string('activeForm', 'Active present tense form for in_progress tasks.'),
                            ])
                            ->withRequiredProperties(['content', 'status', 'activeForm']),
                    ),
                ])
                ->withRequiredProperties(['todos'])
        )->toArray();
    }

    /** @return array<int, array<string, mixed>> */
    private function extractTodos(array $args): array
    {
        $todos = $this->arg($args, 'todos', 0, []);

        if ($todos === null) {
            return [];
        }

        if (!is_array($todos)) {
            throw new InvalidArgumentException("'todos' must be an array");
        }

        return $todos;
    }

    /** @param array<int, array<string, mixed>> $todos */
    private function buildTaskList(array $todos): TaskList
    {
        if ($todos === []) {
            return TaskList::empty();
        }

        $tasks = [];
        foreach ($todos as $todo) {
            if (!is_array($todo)) {
                throw new InvalidArgumentException('Each task must be an object');
            }

            $tasks[] = $this->taskFromArray($todo);
        }

        return TaskList::empty()->withTasks($tasks);
    }

    /** @param array<string, mixed> $data */
    private function taskFromArray(array $data): Task
    {
        return Task::fromArray($data);
    }
}
