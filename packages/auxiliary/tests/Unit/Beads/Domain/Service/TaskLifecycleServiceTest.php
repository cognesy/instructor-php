<?php

declare(strict_types=1);

use Cognesy\Auxiliary\Beads\Domain\Model\Task;
use Cognesy\Auxiliary\Beads\Domain\Model\TaskStatus;
use Cognesy\Auxiliary\Beads\Domain\Model\TaskType;
use Cognesy\Auxiliary\Beads\Domain\Service\TaskLifecycleService;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Agent;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Priority;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\TaskId;

// Helper function to create a task for testing
function createTestTask(string $id, TaskStatus $status = TaskStatus::Open, ?Agent $assignee = null): Task {
    $task = Task::create(
        id: new TaskId($id),
        title: 'Test task',
        type: TaskType::Task,
        priority: Priority::medium(),
        description: 'Test description'
    );

    // If we need a different status or assignee, use reflection to create it
    if ($status !== TaskStatus::Open || $assignee !== null) {
        $reflection = new ReflectionClass(Task::class);
        $constructor = $reflection->getConstructor();
        

        $taskWithAssigneeAndStatus = $reflection->newInstanceWithoutConstructor();
        $constructor->invoke(
            $taskWithAssigneeAndStatus,
            new TaskId($id),
            'Test task',
            $status,
            TaskType::Task,
            Priority::medium(),
            $assignee,
            'Test description',
            [],
            new DateTimeImmutable(),
            null,
            null
        );

        return $taskWithAssigneeAndStatus;
    }

    return $task;
}

describe('TaskLifecycleService', function () {
    test('successfully claims an open unassigned task', function () {
        $service = new TaskLifecycleService();
        $agent = new Agent('claude-123', 'Claude AI');
        $task = createTestTask('test-abc123');

        $result = $service->attemptClaim($task, $agent);

        expect($result)->not()->toBeNull()
            ->and($result->status())->toBe(TaskStatus::InProgress)
            ->and($result->assignee())->toBe($agent)
            ->and($result->updatedAt())->not()->toBeNull();
    });

    test('returns null for task that cannot be claimed', function () {
        $service = new TaskLifecycleService();
        $agent = new Agent('claude-123', 'Claude AI');

        // Task already assigned (in progress)
        $assignedTask = createTestTask('test-def456', TaskStatus::InProgress, $agent);

        expect($service->attemptClaim($assignedTask, $agent))->toBeNull();
    });

    test('returns null for closed task when attempting claim', function () {
        $service = new TaskLifecycleService();
        $agent = new Agent('claude-123', 'Claude AI');

        $closedTask = createTestTask('test-ghi789', TaskStatus::Closed);

        expect($service->attemptClaim($closedTask, $agent))->toBeNull();
    });

    test('returns null for blocked task when attempting claim', function () {
        $service = new TaskLifecycleService();
        $agent = new Agent('claude-123', 'Claude AI');

        $blockedTask = createTestTask('test-jkl012', TaskStatus::Blocked);

        expect($service->attemptClaim($blockedTask, $agent))->toBeNull();
    });

    test('successfully starts an open assigned task', function () {
        $service = new TaskLifecycleService();
        $agent = new Agent('claude-123', 'Claude AI');

        $assignedTask = createTestTask('test-mno345', TaskStatus::Open, $agent);

        $result = $service->attemptStart($assignedTask);

        expect($result)->not()->toBeNull()
            ->and($result->status())->toBe(TaskStatus::InProgress)
            ->and($result->assignee())->toBe($agent)
            ->and($result->updatedAt())->not()->toBeNull();
    });

    test('returns null for unassigned task when attempting start', function () {
        $service = new TaskLifecycleService();

        $unassignedTask = createTestTask('test-pqr678');

        expect($service->attemptStart($unassignedTask))->toBeNull();
    });

    test('returns null for task already in progress when attempting start', function () {
        $service = new TaskLifecycleService();
        $agent = new Agent('claude-123', 'Claude AI');

        $inProgressTask = createTestTask('test-stu901', TaskStatus::InProgress, $agent);

        expect($service->attemptStart($inProgressTask))->toBeNull();
    });

    test('successfully completes an in-progress task', function () {
        $service = new TaskLifecycleService();
        $agent = new Agent('claude-123', 'Claude AI');

        $inProgressTask = createTestTask('test-bcd890', TaskStatus::InProgress, $agent);
        $reason = 'Task completed successfully';

        $result = $service->attemptComplete($inProgressTask, $reason);

        expect($result)->not()->toBeNull()
            ->and($result->status())->toBe(TaskStatus::Closed)
            ->and($result->assignee())->toBe($agent)
            ->and($result->updatedAt())->not()->toBeNull()
            ->and($result->closedAt())->not()->toBeNull();
    });

    test('successfully completes an open task', function () {
        $service = new TaskLifecycleService();
        $agent = new Agent('claude-123', 'Claude AI');

        $openTask = createTestTask('test-efg123', TaskStatus::Open, $agent);
        $reason = 'Task completed without starting';

        $result = $service->attemptComplete($openTask, $reason);

        expect($result)->not()->toBeNull()
            ->and($result->status())->toBe(TaskStatus::Closed);
    });

    test('returns null for already closed task when attempting complete', function () {
        $service = new TaskLifecycleService();
        $agent = new Agent('claude-123', 'Claude AI');

        $closedTask = createTestTask('test-hij456', TaskStatus::Closed, $agent);

        expect($service->attemptComplete($closedTask, 'reason'))->toBeNull();
    });

    test('returns null for blocked task when attempting complete', function () {
        $service = new TaskLifecycleService();
        $agent = new Agent('claude-123', 'Claude AI');

        $blockedTask = createTestTask('test-klm789', TaskStatus::Blocked, $agent);

        expect($service->attemptComplete($blockedTask, 'reason'))->toBeNull();
    });

    test('successfully claims and starts an open unassigned task', function () {
        $service = new TaskLifecycleService();
        $agent = new Agent('claude-123', 'Claude AI');

        $task = createTestTask('test-nop012');

        $result = $service->claimAndStart($task, $agent);

        expect($result)->not()->toBeNull()
            ->and($result->status())->toBe(TaskStatus::InProgress)
            ->and($result->assignee())->toBe($agent)
            ->and($result->updatedAt())->not()->toBeNull();
    });

    test('claimAndStart returns null if claiming fails', function () {
        $service = new TaskLifecycleService();
        $agent = new Agent('claude-123', 'Claude AI');

        $assignedTask = createTestTask('test-qrs345', TaskStatus::InProgress, $agent);

        expect($service->claimAndStart($assignedTask, $agent))->toBeNull();
    });

    test('does not mutate original tasks', function () {
        $service = new TaskLifecycleService();
        $agent = new Agent('claude-123', 'Claude AI');

        $originalTask = createTestTask('test-wxy901');
        $originalStatus = $originalTask->status();
        $originalAssignee = $originalTask->assignee();

        $service->attemptClaim($originalTask, $agent);

        // Original task should remain unchanged
        expect($originalTask->status())->toBe($originalStatus)
            ->and($originalTask->assignee())->toBe($originalAssignee);
    });

    test('returns new task instances', function () {
        $service = new TaskLifecycleService();
        $agent = new Agent('claude-123', 'Claude AI');

        $originalTask = createTestTask('test-zab234');

        $claimedTask = $service->attemptClaim($originalTask, $agent);

        expect($claimedTask)->not()->toBe($originalTask)
            ->and($claimedTask)->not()->toBeNull();
    });

    test('handles full claim-start-complete workflow', function () {
        $service = new TaskLifecycleService();
        $agent = new Agent('claude-123', 'Claude AI');

        $task = createTestTask('test-cde567');

        // Claim the task
        $claimedTask = $service->attemptClaim($task, $agent);
        expect($claimedTask)->not()->toBeNull();
        expect($claimedTask->status())->toBe(TaskStatus::InProgress);

        // Complete the task (already started by claim)
        $completedTask = $service->attemptComplete($claimedTask, 'Workflow completed');
        expect($completedTask)->not()->toBeNull();
        expect($completedTask->status())->toBe(TaskStatus::Closed);
    });

    test('handles claim-and-start-complete workflow', function () {
        $service = new TaskLifecycleService();
        $agent = new Agent('claude-123', 'Claude AI');

        $task = createTestTask('test-fgh890');

        // Claim and start in one operation
        $startedTask = $service->claimAndStart($task, $agent);
        expect($startedTask)->not()->toBeNull();
        expect($startedTask->status())->toBe(TaskStatus::InProgress);

        // Complete the task
        $completedTask = $service->attemptComplete($startedTask, 'One-step workflow completed');
        expect($completedTask)->not()->toBeNull();
        expect($completedTask->status())->toBe(TaskStatus::Closed);
    });

    test('handles different task types consistently', function () {
        $service = new TaskLifecycleService();
        $agent = new Agent('claude-123', 'Claude AI');

        $types = [TaskType::Task, TaskType::Bug, TaskType::Feature, TaskType::Epic];

        foreach ($types as $type) {
            $task = Task::create(
                id: new TaskId("test-{$type->value}123"),
                title: "Test {$type->value}",
                type: $type,
                priority: Priority::high()
            );

            $result = $service->attemptClaim($task, $agent);

            expect($result)->not()->toBeNull()
                ->and($result->type())->toBe($type)
                ->and($result->status())->toBe(TaskStatus::InProgress);
        }
    });

    test('handles different priorities consistently', function () {
        $service = new TaskLifecycleService();
        $agent = new Agent('claude-123', 'Claude AI');

        $priorities = [
            Priority::critical(),
            Priority::high(),
            Priority::medium(),
            Priority::low(),
            Priority::backlog()
        ];

        foreach ($priorities as $priority) {
            $task = Task::create(
                id: new TaskId("test-{$priority->value}123"),
                title: "Test priority {$priority->value}",
                type: TaskType::Task,
                priority: $priority
            );

            $result = $service->attemptClaim($task, $agent);

            expect($result)->not()->toBeNull()
                ->and($result->priority())->toBe($priority)
                ->and($result->status())->toBe(TaskStatus::InProgress);
        }
    });
});