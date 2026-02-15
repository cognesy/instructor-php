<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Tasks;

use Cognesy\Agents\Tool\ToolDescriptor;

final readonly class TodoWriteToolDescriptor extends ToolDescriptor
{
    public function __construct() {
        parent::__construct(
            name: TodoWriteTool::TOOL_NAME,
            description: 'Write or replace the current task list with up to 20 items.',
            metadata: [
                'name' => TodoWriteTool::TOOL_NAME,
                'summary' => 'Persist or replace the current task checklist.',
                'namespace' => 'tasks',
                'tags' => ['tasks', 'planning', 'todo'],
            ],
            instructions: [
                'parameters' => [
                    'todos' => 'List of task items with content, status, and activeForm.',
                ],
                'returns' => 'TodoWriteResult with normalized task list and rendered summary.',
            ],
        );
    }
}
