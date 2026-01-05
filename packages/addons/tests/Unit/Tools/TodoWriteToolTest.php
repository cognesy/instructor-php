<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Tools;

use Cognesy\Addons\Agent\Capabilities\Tasks\TodoWriteTool;

describe('TodoWriteTool', function () {

    it('has correct name and description', function () {
        $tool = new TodoWriteTool();

        expect($tool->name())->toBe('todo_write');
        expect($tool->description())->toContain('task list');
    });

    it('creates tasks from valid input', function () {
        $tool = new TodoWriteTool();

        $result = $tool([
            ['content' => 'Task 1', 'status' => 'pending', 'activeForm' => 'Doing task 1'],
            ['content' => 'Task 2', 'status' => 'in_progress', 'activeForm' => 'Doing task 2'],
        ]);

        expect($result->success)->toBeTrue();
        expect($result->tasks)->toHaveCount(2);
    });

    it('returns rendered output', function () {
        $tool = new TodoWriteTool();

        $result = $tool([
            ['content' => 'Fix bug', 'status' => 'completed', 'activeForm' => 'Fixing bug'],
            ['content' => 'Write tests', 'status' => 'in_progress', 'activeForm' => 'Writing tests'],
        ]);

        expect($result->rendered)->toContain('Fix bug');
        expect($result->rendered)->toContain('Writing tests');
    });

    it('returns summary', function () {
        $tool = new TodoWriteTool();

        $result = $tool([
            ['content' => 'Task 1', 'status' => 'completed', 'activeForm' => 'Doing 1'],
            ['content' => 'Task 2', 'status' => 'in_progress', 'activeForm' => 'Doing 2'],
            ['content' => 'Task 3', 'status' => 'pending', 'activeForm' => 'Doing 3'],
        ]);

        expect($result->summary)->toContain('1/3 completed');
        expect($result->summary)->toContain('1 in progress');
        expect($result->summary)->toContain('1 pending');
    });

    it('throws exception for missing content', function () {
        $tool = new TodoWriteTool();

        expect(fn() => $tool([
            ['status' => 'pending', 'activeForm' => 'Doing task'],
        ]))->toThrow(\InvalidArgumentException::class, "'content' is required");
    });

    it('throws exception for empty content', function () {
        $tool = new TodoWriteTool();

        expect(fn() => $tool([
            ['content' => '', 'status' => 'pending', 'activeForm' => 'Doing task'],
        ]))->toThrow(\InvalidArgumentException::class, "'content' is required");
    });

    it('throws exception for missing status', function () {
        $tool = new TodoWriteTool();

        expect(fn() => $tool([
            ['content' => 'Task', 'activeForm' => 'Doing task'],
        ]))->toThrow(\InvalidArgumentException::class, "'status' is required");
    });

    it('throws exception for invalid status', function () {
        $tool = new TodoWriteTool();

        expect(fn() => $tool([
            ['content' => 'Task', 'status' => 'invalid', 'activeForm' => 'Doing task'],
        ]))->toThrow(\InvalidArgumentException::class, "'status' must be one of");
    });

    it('throws exception for missing activeForm', function () {
        $tool = new TodoWriteTool();

        expect(fn() => $tool([
            ['content' => 'Task', 'status' => 'pending'],
        ]))->toThrow(\InvalidArgumentException::class, "'activeForm' is required");
    });

    it('throws exception for empty activeForm', function () {
        $tool = new TodoWriteTool();

        expect(fn() => $tool([
            ['content' => 'Task', 'status' => 'pending', 'activeForm' => ''],
        ]))->toThrow(\InvalidArgumentException::class, "'activeForm' is required");
    });

    it('validates max 20 tasks constraint', function () {
        $tool = new TodoWriteTool();

        $todos = [];
        for ($i = 1; $i <= 21; $i++) {
            $todos[] = ['content' => "Task {$i}", 'status' => 'pending', 'activeForm' => "Doing {$i}"];
        }

        expect(fn() => $tool($todos))
            ->toThrow(\InvalidArgumentException::class, 'Maximum 20 tasks');
    });

    it('validates only 1 in_progress constraint', function () {
        $tool = new TodoWriteTool();

        expect(fn() => $tool([
            ['content' => 'Task 1', 'status' => 'in_progress', 'activeForm' => 'Doing 1'],
            ['content' => 'Task 2', 'status' => 'in_progress', 'activeForm' => 'Doing 2'],
        ]))->toThrow(\InvalidArgumentException::class, 'Only 1 task can be in_progress');
    });

    it('allows empty task list', function () {
        $tool = new TodoWriteTool();

        $result = $tool([]);

        expect($result->success)->toBeTrue();
        expect($result->tasks)->toBeEmpty();
    });

    it('trims whitespace from content and activeForm', function () {
        $tool = new TodoWriteTool();

        $result = $tool([
            ['content' => '  Task with spaces  ', 'status' => 'pending', 'activeForm' => '  Doing task  '],
        ]);

        expect($result->tasks[0]['content'])->toBe('Task with spaces');
        expect($result->tasks[0]['activeForm'])->toBe('Doing task');
    });

    it('provides metadata key', function () {
        expect(TodoWriteTool::metadataKey())->toBe('tasks');
    });

    it('generates valid tool schema', function () {
        $tool = new TodoWriteTool();
        $schema = $tool->toToolSchema();

        expect($schema['type'])->toBe('function');
        expect($schema['function']['name'])->toBe('todo_write');
        expect($schema['function']['parameters'])->toBeArray();
    });
});
