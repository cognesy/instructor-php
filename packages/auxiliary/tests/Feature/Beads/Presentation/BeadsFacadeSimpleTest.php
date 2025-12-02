<?php

declare(strict_types=1);

use Cognesy\Auxiliary\Beads\Domain\Model\Task;
use Cognesy\Auxiliary\Beads\Domain\Model\TaskType;
use Cognesy\Auxiliary\Beads\Domain\Model\TaskStatus;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Agent;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Priority;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\TaskId;
use Cognesy\Auxiliary\Beads\Infrastructure\Parser\TaskParser;

test('Agent value object creation and equality work correctly', function () {
    // Test creating an Agent value object
    $agent1 = new Agent('test-agent-123', 'Test Agent Name');
    $agent2 = new Agent('test-agent-123', 'Test Agent Name');
    $agent3 = new Agent('different-agent', 'Different Agent');

    expect($agent1->id)->toBe('test-agent-123')
        ->and($agent1->name)->toBe('Test Agent Name')
        ->and($agent1->equals($agent2))->toBeTrue()
        ->and($agent1->equals($agent3))->toBeFalse()
        ->and($agent1->displayName())->toBe('Test Agent Name');
});

test('Task creation and lifecycle operations work correctly', function () {
    // Test creating a task through the domain model
    $agent = new Agent('claude-123', 'Claude AI');

    $task = Task::create(
        id: new TaskId('test-feature123'),
        title: 'Implement new feature',
        type: TaskType::Feature,
        priority: Priority::high(),
        description: 'This is a test feature implementation'
    );

    // Test initial state
    expect($task->id()->value)->toBe('test-feature123')
        ->and($task->title())->toBe('Implement new feature')
        ->and($task->type())->toBe(TaskType::Feature)
        ->and($task->priority())->toEqual(Priority::high())
        ->and($task->status())->toBe(TaskStatus::Open)
        ->and($task->assignee())->toBeNull()
        ->and($task->canBeClaimed())->toBeTrue()
        ->and($task->canBeStarted())->toBeFalse();

    // Test claiming the task
    $claimedTask = $task->claim($agent);
    expect($claimedTask->assignee())->toBe($agent)
        ->and($claimedTask->status())->toBe(TaskStatus::InProgress)
        ->and($claimedTask->canBeClaimed())->toBeFalse()
        ->and($claimedTask->canBeCompleted())->toBeTrue();

    // Test completing the task
    $completedTask = $claimedTask->complete('Feature implementation finished');
    expect($completedTask->status())->toBe(TaskStatus::Closed)
        ->and($completedTask->closedAt())->not()->toBeNull()
        ->and($completedTask->canBeCompleted())->toBeFalse();
});

test('Priority system works correctly for task ordering', function () {
    // Test priority comparison and ordering
    $priorities = [
        Priority::critical(),
        Priority::high(),
        Priority::medium(),
        Priority::low(),
        Priority::backlog()
    ];

    // Verify ordering
    for ($i = 0; $i < count($priorities) - 1; $i++) {
        expect($priorities[$i]->isHigherThan($priorities[$i + 1]))->toBeTrue()
            ->and($priorities[$i + 1]->isLowerThan($priorities[$i]))->toBeTrue();
    }

    // Test priority values
    expect(Priority::critical()->value)->toBe(0)
        ->and(Priority::high()->value)->toBe(1)
        ->and(Priority::medium()->value)->toBe(2)
        ->and(Priority::low()->value)->toBe(3)
        ->and(Priority::backlog()->value)->toBe(4);
});

test('TaskParser integration with different task types works correctly', function () {
    $parser = new TaskParser();

    // Test parsing different task types
    $taskTypes = [
        'task' => TaskType::Task,
        'bug' => TaskType::Bug,
        'feature' => TaskType::Feature,
        'epic' => TaskType::Epic,
    ];

    foreach ($taskTypes as $bdType => $expectedType) {
        $data = [
            'id' => "test-{$bdType}456",
            'title' => "Test {$bdType}",
            'issue_type' => $bdType,
            'priority' => 1,
            'description' => "This is a test {$bdType}",
            'labels' => ['test', $bdType]
        ];

        $task = $parser->parse($data);

        expect($task->type())->toBe($expectedType)
            ->and($task->title())->toBe("Test {$bdType}")
            ->and($task->priority())->toEqual(Priority::high())
            ->and($task->labels())->toBe(['test', $bdType]);
    }
});

test('Task validation prevents invalid operations', function () {
    $task = Task::create(
        id: new TaskId('test-validation123'),
        title: 'Test Task',
        type: TaskType::Task,
        priority: Priority::medium()
    );

    // Test that unassigned tasks can't be started
    expect(fn() => $task->start())
        ->toThrow(InvalidArgumentException::class, 'Cannot start task: must be open and assigned');

    // Test that closed tasks can't be claimed
    $closedTask = $task->complete('Closing for test');
    expect(fn() => $closedTask->claim(new Agent('test-123', 'Test')))
        ->toThrow(InvalidArgumentException::class);
});

test('Full workflow: create -> claim -> complete works end-to-end', function () {
    // Simulate a complete task workflow
    $agent = new Agent('workflow-test', 'Workflow Test Agent');

    // Step 1: Create task
    $newTask = Task::create(
        id: new TaskId('workflow-test123'),
        title: 'End-to-end workflow test',
        type: TaskType::Task,
        priority: Priority::medium(),
        description: 'Testing complete task lifecycle'
    );

    expect($newTask->isOpen())->toBeTrue()
        ->and($newTask->assignee())->toBeNull();

    // Step 2: Agent claims and starts task
    $activeTask = $newTask->claim($agent);

    expect($activeTask->isInProgress())->toBeTrue()
        ->and($activeTask->assignee())->toBe($agent)
        ->and($activeTask->updatedAt())->not()->toBeNull();

    // Step 3: Agent completes task
    $finishedTask = $activeTask->complete('Workflow test completed successfully');

    expect($finishedTask->isClosed())->toBeTrue()
        ->and($finishedTask->closedAt())->not()->toBeNull()
        ->and($finishedTask->assignee())->toBe($agent); // Assignee preserved after completion
});