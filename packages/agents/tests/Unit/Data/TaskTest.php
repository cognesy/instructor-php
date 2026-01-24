<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Data;

use Cognesy\Agents\AgentBuilder\Capabilities\Tasks\Task;
use Cognesy\Agents\AgentBuilder\Capabilities\Tasks\TaskStatus;

describe('Task', function () {

    it('creates a task with required properties', function () {
        $task = new Task(
            content: 'Fix the bug',
            status: TaskStatus::Pending,
            activeForm: 'Fixing the bug',
        );

        expect($task->content)->toBe('Fix the bug');
        expect($task->status)->toBe(TaskStatus::Pending);
        expect($task->activeForm)->toBe('Fixing the bug');
    });

    it('creates a task from array', function () {
        $data = [
            'content' => 'Write tests',
            'status' => 'in_progress',
            'activeForm' => 'Writing tests',
        ];

        $task = Task::fromArray($data);

        expect($task->content)->toBe('Write tests');
        expect($task->status)->toBe(TaskStatus::InProgress);
        expect($task->activeForm)->toBe('Writing tests');
    });

    it('defaults activeForm to content when missing', function () {
        $data = [
            'content' => 'Deploy to production',
            'status' => 'pending',
        ];

        $task = Task::fromArray($data);

        expect($task->activeForm)->toBe('Deploy to production');
    });

    it('converts task to array', function () {
        $task = new Task(
            content: 'Review PR',
            status: TaskStatus::Completed,
            activeForm: 'Reviewing PR',
        );

        $array = $task->toArray();

        expect($array)->toBe([
            'content' => 'Review PR',
            'status' => 'completed',
            'activeForm' => 'Reviewing PR',
        ]);
    });

    it('creates new task with changed status', function () {
        $task = new Task(
            content: 'Run tests',
            status: TaskStatus::Pending,
            activeForm: 'Running tests',
        );

        $updatedTask = $task->withStatus(TaskStatus::InProgress);

        expect($task->status)->toBe(TaskStatus::Pending);
        expect($updatedTask->status)->toBe(TaskStatus::InProgress);
        expect($updatedTask->content)->toBe('Run tests');
    });

    it('renders pending task with content', function () {
        $task = new Task(
            content: 'Fix bug',
            status: TaskStatus::Pending,
            activeForm: 'Fixing bug',
        );

        expect($task->render())->toBe('○ Fix bug');
    });

    it('renders in_progress task with activeForm', function () {
        $task = new Task(
            content: 'Fix bug',
            status: TaskStatus::InProgress,
            activeForm: 'Fixing bug',
        );

        expect($task->render())->toBe('◐ Fixing bug');
    });

    it('renders completed task with content', function () {
        $task = new Task(
            content: 'Fix bug',
            status: TaskStatus::Completed,
            activeForm: 'Fixing bug',
        );

        expect($task->render())->toBe('● Fix bug');
    });
});
