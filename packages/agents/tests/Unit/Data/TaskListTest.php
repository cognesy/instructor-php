<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Data;

use Cognesy\Agents\AgentBuilder\Capabilities\Tasks\Task;
use Cognesy\Agents\AgentBuilder\Capabilities\Tasks\TaskList;
use Cognesy\Agents\AgentBuilder\Capabilities\Tasks\TaskStatus;

describe('TaskList', function () {

    it('creates an empty task list', function () {
        $list = TaskList::empty();

        expect($list->isEmpty())->toBeTrue();
        expect($list->count())->toBe(0);
    });

    it('creates task list from array', function () {
        $data = [
            ['content' => 'Task 1', 'status' => 'pending', 'activeForm' => 'Doing task 1'],
            ['content' => 'Task 2', 'status' => 'completed', 'activeForm' => 'Doing task 2'],
        ];

        $list = TaskList::fromArray($data);

        expect($list->count())->toBe(2);
        expect($list->isEmpty())->toBeFalse();
    });

    it('converts task list to array', function () {
        $tasks = [
            new Task('Task 1', TaskStatus::Pending, 'Doing task 1'),
            new Task('Task 2', TaskStatus::Completed, 'Doing task 2'),
        ];

        $list = TaskList::empty()->withTasks($tasks);
        $array = $list->toArray();

        expect($array)->toHaveCount(2);
        expect($array[0]['content'])->toBe('Task 1');
        expect($array[1]['status'])->toBe('completed');
    });

    it('returns all tasks', function () {
        $tasks = [
            new Task('Task 1', TaskStatus::Pending, 'Doing task 1'),
            new Task('Task 2', TaskStatus::InProgress, 'Doing task 2'),
        ];

        $list = TaskList::empty()->withTasks($tasks);
        $all = $list->all();

        expect($all)->toHaveCount(2);
        expect($all[0])->toBeInstanceOf(Task::class);
    });

    it('counts tasks by status', function () {
        $tasks = [
            new Task('Task 1', TaskStatus::Pending, 'Doing task 1'),
            new Task('Task 2', TaskStatus::Pending, 'Doing task 2'),
            new Task('Task 3', TaskStatus::InProgress, 'Doing task 3'),
            new Task('Task 4', TaskStatus::Completed, 'Doing task 4'),
        ];

        $list = TaskList::empty()->withTasks($tasks);

        expect($list->countByStatus(TaskStatus::Pending))->toBe(2);
        expect($list->countByStatus(TaskStatus::InProgress))->toBe(1);
        expect($list->countByStatus(TaskStatus::Completed))->toBe(1);
    });

    it('returns current in_progress task', function () {
        $tasks = [
            new Task('Task 1', TaskStatus::Completed, 'Doing task 1'),
            new Task('Task 2', TaskStatus::InProgress, 'Doing task 2'),
            new Task('Task 3', TaskStatus::Pending, 'Doing task 3'),
        ];

        $list = TaskList::empty()->withTasks($tasks);
        $current = $list->currentInProgress();

        expect($current)->not->toBeNull();
        expect($current->content)->toBe('Task 2');
    });

    it('returns null when no task is in_progress', function () {
        $tasks = [
            new Task('Task 1', TaskStatus::Completed, 'Doing task 1'),
            new Task('Task 2', TaskStatus::Pending, 'Doing task 2'),
        ];

        $list = TaskList::empty()->withTasks($tasks);

        expect($list->currentInProgress())->toBeNull();
    });

    it('renders task list', function () {
        $tasks = [
            new Task('Fix bug', TaskStatus::Completed, 'Fixing bug'),
            new Task('Write tests', TaskStatus::InProgress, 'Writing tests'),
            new Task('Deploy', TaskStatus::Pending, 'Deploying'),
        ];

        $list = TaskList::empty()->withTasks($tasks);
        $rendered = $list->render();

        expect($rendered)->toContain('1. ● Fix bug');
        expect($rendered)->toContain('2. ◐ Writing tests');
        expect($rendered)->toContain('3. ○ Deploy');
    });

    it('renders empty list message', function () {
        $list = TaskList::empty();

        expect($list->render())->toBe('(no tasks)');
    });

    it('renders summary', function () {
        $tasks = [
            new Task('Task 1', TaskStatus::Completed, 'Doing task 1'),
            new Task('Task 2', TaskStatus::InProgress, 'Doing task 2'),
            new Task('Task 3', TaskStatus::Pending, 'Doing task 3'),
            new Task('Task 4', TaskStatus::Pending, 'Doing task 4'),
        ];

        $list = TaskList::empty()->withTasks($tasks);
        $summary = $list->renderSummary();

        expect($summary)->toBe('Tasks: 1/4 completed, 1 in progress, 2 pending');
    });

    it('throws exception when exceeding max tasks', function () {
        $tasks = [];
        for ($i = 1; $i <= 21; $i++) {
            $tasks[] = new Task("Task {$i}", TaskStatus::Pending, "Doing task {$i}");
        }

        expect(fn() => TaskList::empty()->withTasks($tasks))
            ->toThrow(\InvalidArgumentException::class, 'Maximum 20 tasks allowed');
    });

    it('throws exception when multiple tasks are in_progress', function () {
        $tasks = [
            new Task('Task 1', TaskStatus::InProgress, 'Doing task 1'),
            new Task('Task 2', TaskStatus::InProgress, 'Doing task 2'),
        ];

        expect(fn() => TaskList::empty()->withTasks($tasks))
            ->toThrow(\InvalidArgumentException::class, 'Only 1 task can be in_progress');
    });

    it('allows exactly 20 tasks', function () {
        $tasks = [];
        for ($i = 1; $i <= 20; $i++) {
            $tasks[] = new Task("Task {$i}", TaskStatus::Pending, "Doing task {$i}");
        }

        $list = TaskList::empty()->withTasks($tasks);

        expect($list->count())->toBe(20);
    });
});