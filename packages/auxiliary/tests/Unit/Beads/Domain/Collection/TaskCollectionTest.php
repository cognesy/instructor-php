<?php

declare(strict_types=1);

use Cognesy\Auxiliary\Beads\Domain\Collection\TaskCollection;
use Cognesy\Auxiliary\Beads\Domain\Model\Task;
use Cognesy\Auxiliary\Beads\Domain\Model\TaskStatus;
use Cognesy\Auxiliary\Beads\Domain\Model\TaskType;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Agent;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Priority;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\TaskId;

// Helper function to create tasks with different states
function createTaskForCollection(
    string $id,
    string $title,
    TaskType $type = TaskType::Task,
    ?Priority $priority = null,
    TaskStatus $status = TaskStatus::Open,
    ?Agent $assignee = null,
    array $labels = [],
    ?DateTimeImmutable $createdAt = null
): Task {
    $task = Task::create(
        id: new TaskId($id),
        title: $title,
        type: $type,
        priority: $priority ?? Priority::medium(),
        description: "Test description for {$title}",
        labels: $labels
    );

    // Use reflection to set custom status, assignee, and creation time if needed
    if ($status !== TaskStatus::Open || $assignee !== null || $createdAt !== null) {
        $reflection = new ReflectionClass(Task::class);
        $constructor = $reflection->getConstructor();
        

        $customTask = $reflection->newInstanceWithoutConstructor();
        $constructor->invoke(
            $customTask,
            new TaskId($id),
            $title,
            $status,
            $type,
            $priority ?? Priority::medium(),
            $assignee,
            "Test description for {$title}",
            $labels,
            $createdAt ?? new DateTimeImmutable(),
            null,
            null
        );

        return $customTask;
    }

    return $task;
}

// Helper function to create test data for TaskCollection tests
function createTaskCollectionTestData(): array {
    $agent1 = new Agent('claude-123', 'Claude AI');
    $agent2 = new Agent('user-456', 'User');

    $tasks = [
        createTaskForCollection('test-abc1', 'Open Task 1', TaskType::Task, Priority::high()),
        createTaskForCollection('test-abc2', 'In Progress Task', TaskType::Bug, Priority::critical(), TaskStatus::InProgress, $agent1),
        createTaskForCollection('test-abc3', 'Closed Task', TaskType::Feature, Priority::low(), TaskStatus::Closed, $agent1),
        createTaskForCollection('test-abc4', 'Blocked Task', TaskType::Epic, Priority::medium(), TaskStatus::Blocked),
        createTaskForCollection('test-abc5', 'Open Task 2', TaskType::Task, Priority::backlog(), TaskStatus::Open, null, ['backend', 'api']),
        createTaskForCollection('test-abc6', 'Assigned Open', TaskType::Feature, Priority::high(), TaskStatus::Open, $agent2, ['frontend']),
    ];

    return [
        'agent1' => $agent1,
        'agent2' => $agent2,
        'tasks' => $tasks,
        'collection' => TaskCollection::from($tasks)
    ];
}

describe('TaskCollection', function () {
    describe('construction', function () {
        it('creates empty collection', function () {
            $empty = TaskCollection::empty();

            expect($empty->count())->toBe(0)
                ->and($empty->isEmpty())->toBeTrue()
                ->and($empty->isNotEmpty())->toBeFalse()
                ->and($empty->toArray())->toBe([]);
        });

        it('creates collection from array', function () {
            $tasks = [createTaskForCollection('test-abc123', 'Test Task')];
            $collection = TaskCollection::from($tasks);

            expect($collection->count())->toBe(1)
                ->and($collection->toArray())->toBe($tasks);
        });

        it('creates collection with constructor', function () {
            $tasks = [createTaskForCollection('test-abc456', 'Another Task')];
            $collection = new TaskCollection($tasks);

            expect($collection->count())->toBe(1);
        });
    });

    describe('basic operations', function () {
        it('adds tasks to collection', function () {
            $data = createTaskCollectionTestData();
            $collection = $data['collection'];

            $newTask = createTaskForCollection('test-abc7', 'New Task');
            $newCollection = $collection->add($newTask);

            expect($newCollection->count())->toBe($collection->count() + 1)
                ->and($newCollection->contains($newTask))->toBeTrue()
                ->and($collection->contains($newTask))->toBeFalse(); // Original unchanged
        });

        it('finds tasks by ID', function () {
            $data = createTaskCollectionTestData();
            $collection = $data['collection'];

            $targetId = new TaskId('test-abc2');
            $found = $collection->find($targetId);

            expect($found)->not()->toBeNull()
                ->and($found->id())->toEqual($targetId)
                ->and($found->title())->toBe('In Progress Task');
        });

        it('returns null when task not found', function () {
            $data = createTaskCollectionTestData();
            $collection = $data['collection'];

            $missingId = new TaskId('test-xyz999');

            expect($collection->find($missingId))->toBeNull();
        });

        it('checks if collection contains task', function () {
            $data = createTaskCollectionTestData();
            $collection = $data['collection'];
            $tasks = $data['tasks'];

            $existingTask = $tasks[0];
            $newTask = createTaskForCollection('test-xyz999', 'Non-existent');

            expect($collection->contains($existingTask))->toBeTrue()
                ->and($collection->contains($newTask))->toBeFalse();
        });

        it('gets first and last tasks', function () {
            $data = createTaskCollectionTestData();
            $collection = $data['collection'];
            $tasks = $data['tasks'];

            expect($collection->first())->toBe($tasks[0])
                ->and($collection->last())->toBe($tasks[5]);

            $empty = TaskCollection::empty();
            expect($empty->first())->toBeNull()
                ->and($empty->last())->toBeNull();
        });
    });

    describe('status filtering', function () {
        it('filters open tasks', function () {
            $data = createTaskCollectionTestData();
            $collection = $data['collection'];

            $openTasks = $collection->open();

            expect($openTasks->count())->toBe(3); // task-001, task-005, task-006
            foreach ($openTasks as $task) {
                expect($task->isOpen())->toBeTrue();
            }
        });

        it('filters in progress tasks', function () {
            $agent = new Agent('test-123', 'Test Agent');
            $inProgressTask = createTaskForCollection('test-abc2', 'In Progress Task', TaskType::Bug, Priority::critical(), TaskStatus::InProgress, $agent);

            $collection = TaskCollection::from([$inProgressTask]);
            $inProgressTasks = $collection->inProgress();

            expect($inProgressTasks->count())->toBe(1);
            expect($inProgressTasks->first()->title())->toBe('In Progress Task');
        });

        it('filters closed tasks', function () {
            $agent = new Agent('test-123', 'Test Agent');
            $closedTask = createTaskForCollection('test-abc3', 'Closed Task', TaskType::Feature, Priority::low(), TaskStatus::Closed, $agent);

            $collection = TaskCollection::from([$closedTask]);
            $closedTasks = $collection->closed();

            expect($closedTasks->count())->toBe(1);
            expect($closedTasks->first()->title())->toBe('Closed Task');
        });

        it('filters blocked tasks', function () {
            $blockedTask = createTaskForCollection('test-abc4', 'Blocked Task', TaskType::Epic, Priority::medium(), TaskStatus::Blocked);

            $collection = TaskCollection::from([$blockedTask]);
            $blockedTasks = $collection->blocked();

            expect($blockedTasks->count())->toBe(1);
            expect($blockedTasks->first()->title())->toBe('Blocked Task');
        });

        it('filters by specific status', function () {
            $data = createTaskCollectionTestData();
            $collection = $data['collection'];

            $openTasks = $collection->withStatus(TaskStatus::Open);
            $inProgressTasks = $collection->withStatus(TaskStatus::InProgress);

            expect($openTasks->count())->toBe(3)
                ->and($inProgressTasks->count())->toBe(1);
        });
    });

    describe('priority filtering', function () {
        it('filters high priority tasks', function () {
            $data = createTaskCollectionTestData();
            $collection = $data['collection'];

            $highPriority = $collection->highPriority();

            expect($highPriority->count())->toBe(3); // critical + high priority tasks
            foreach ($highPriority as $task) {
                expect($task->priority()->value)->toBeLessThanOrEqual(1);
            }
        });

        it('filters by minimum priority level', function () {
            $data = createTaskCollectionTestData();
            $collection = $data['collection'];

            $criticalOnly = $collection->withPriority(Priority::critical());
            $highAndAbove = $collection->withPriority(Priority::high());
            $mediumAndAbove = $collection->withPriority(Priority::medium());

            expect($criticalOnly->count())->toBe(1) // Only critical
                ->and($highAndAbove->count())->toBe(3) // Critical + High
                ->and($mediumAndAbove->count())->toBe(4); // Critical + High + Medium
        });
    });

    describe('assignment filtering', function () {
        it('filters tasks assigned to specific agent', function () {
            $data = createTaskCollectionTestData();
            $collection = $data['collection'];
            $agent1 = $data['agent1'];
            $agent2 = $data['agent2'];

            $agent1Tasks = $collection->assignedTo($agent1);
            $agent2Tasks = $collection->assignedTo($agent2);

            expect($agent1Tasks->count())->toBe(2) // task-002, task-003
                ->and($agent2Tasks->count())->toBe(1); // task-006
        });

        it('filters unassigned tasks', function () {
            $data = createTaskCollectionTestData();
            $collection = $data['collection'];

            $unassigned = $collection->unassigned();

            expect($unassigned->count())->toBe(3); // task-001, task-004, task-005
            foreach ($unassigned as $task) {
                expect($task->assignee())->toBeNull();
            }
        });

        it('filters claimable tasks', function () {
            $data = createTaskCollectionTestData();
            $collection = $data['collection'];

            $claimable = $collection->claimable();

            expect($claimable->count())->toBe(2); // task-001, task-005 (open + unassigned)
            foreach ($claimable as $task) {
                expect($task->canBeClaimed())->toBeTrue();
            }
        });
    });

    describe('label filtering', function () {
        it('filters tasks by label', function () {
            $data = createTaskCollectionTestData();
            $collection = $data['collection'];

            $backendTasks = $collection->withLabel('backend');
            $frontendTasks = $collection->withLabel('frontend');
            $apiTasks = $collection->withLabel('api');

            expect($backendTasks->count())->toBe(1) // task-005
                ->and($frontendTasks->count())->toBe(1) // task-006
                ->and($apiTasks->count())->toBe(1); // task-005
        });

        it('returns empty collection for non-existent label', function () {
            $data = createTaskCollectionTestData();
            $collection = $data['collection'];

            $nonExistent = $collection->withLabel('non-existent');

            expect($nonExistent->isEmpty())->toBeTrue();
        });
    });

    describe('sorting', function () {
        it('sorts by priority (highest first)', function () {
            $data = createTaskCollectionTestData();
            $collection = $data['collection'];

            $sorted = $collection->sortByPriority();
            $priorities = $sorted->map(fn($task) => $task->priority()->value);

            expect($priorities)->toBe([0, 1, 1, 2, 3, 4]); // Critical to Backlog
        });

        it('sorts by newest first', function () {
            // Create tasks with specific timestamps
            $old = createTaskForCollection('old-abc123', 'Old Task', TaskType::Task, Priority::medium(), TaskStatus::Open, null, [], new DateTimeImmutable('2023-01-01'));
            $new = createTaskForCollection('new-abc123', 'New Task', TaskType::Task, Priority::medium(), TaskStatus::Open, null, [], new DateTimeImmutable('2023-12-31'));

            $collection = TaskCollection::from([$old, $new]);
            $sorted = $collection->sortByNewest();

            expect($sorted->first()->title())->toBe('New Task')
                ->and($sorted->last()->title())->toBe('Old Task');
        });

        it('sorts by oldest first', function () {
            // Create tasks with specific timestamps
            $old = createTaskForCollection('old-abc456', 'Old Task', TaskType::Task, Priority::medium(), TaskStatus::Open, null, [], new DateTimeImmutable('2023-01-01'));
            $new = createTaskForCollection('new-abc456', 'New Task', TaskType::Task, Priority::medium(), TaskStatus::Open, null, [], new DateTimeImmutable('2023-12-31'));

            $collection = TaskCollection::from([$new, $old]);
            $sorted = $collection->sortByOldest();

            expect($sorted->first()->title())->toBe('Old Task')
                ->and($sorted->last()->title())->toBe('New Task');
        });
    });

    describe('mapping and custom filtering', function () {
        it('maps tasks to array', function () {
            $data = createTaskCollectionTestData();
            $collection = $data['collection'];

            $titles = $collection->map(fn($task) => $task->title());
            $ids = $collection->map(fn($task) => $task->id()->value);

            expect($titles)->toContain('Open Task 1', 'In Progress Task', 'Closed Task')
                ->and($ids)->toContain('test-abc1', 'test-abc2', 'test-abc3');
        });

        it('extracts task IDs', function () {
            $data = createTaskCollectionTestData();
            $collection = $data['collection'];

            $ids = $collection->ids();

            expect($ids)->toHaveCount(6);
            foreach ($ids as $id) {
                expect($id)->toBeInstanceOf(TaskId::class);
            }
        });

        it('filters with custom predicate', function () {
            $data = createTaskCollectionTestData();
            $collection = $data['collection'];

            $bugTasks = $collection->filter(fn($task) => $task->type() === TaskType::Bug);
            $longTitles = $collection->filter(fn($task) => strlen($task->title()) > 10);

            expect($bugTasks->count())->toBe(1)
                ->and($longTitles->count())->toBeGreaterThan(0);
        });
    });

    describe('chaining operations', function () {
        it('chains multiple filters', function () {
            $data = createTaskCollectionTestData();
            $collection = $data['collection'];

            $result = $collection
                ->open()
                ->highPriority()
                ->unassigned();

            expect($result->count())->toBe(1); // Only task-001 meets all criteria
            expect($result->first()->title())->toBe('Open Task 1');
        });

        it('chains filtering and sorting', function () {
            $data = createTaskCollectionTestData();
            $collection = $data['collection'];

            $result = $collection
                ->open()
                ->sortByPriority();

            $priorities = $result->map(fn($task) => $task->priority()->value);
            expect($priorities[0])->toBeLessThanOrEqual($priorities[1]);
        });

        it('chains filtering, sorting, and mapping', function () {
            $data = createTaskCollectionTestData();
            $collection = $data['collection'];

            $titles = $collection
                ->withStatus(TaskStatus::Open)
                ->sortByPriority()
                ->map(fn($task) => $task->title());

            expect($titles)->toHaveCount(3);
            expect($titles[0])->toBe('Open Task 1'); // Highest priority open task
        });
    });

    describe('iteration and countable', function () {
        it('is countable', function () {
            $data = createTaskCollectionTestData();
            $collection = $data['collection'];

            expect(count($collection))->toBe(6)
                ->and($collection->count())->toBe(6);
        });

        it('is iterable', function () {
            $data = createTaskCollectionTestData();
            $collection = $data['collection'];

            $count = 0;
            foreach ($collection as $task) {
                expect($task)->toBeInstanceOf(Task::class);
                $count++;
            }

            expect($count)->toBe(6);
        });

        it('supports array access via iteration', function () {
            $data = createTaskCollectionTestData();
            $collection = $data['collection'];
            $tasks = $data['tasks'];

            $iteratedTasks = [];
            foreach ($collection as $index => $task) {
                $iteratedTasks[$index] = $task;
            }

            expect($iteratedTasks[0])->toBe($tasks[0])
                ->and($iteratedTasks[5])->toBe($tasks[5]);
        });
    });

    describe('immutability', function () {
        it('returns new instances from operations', function () {
            $data = createTaskCollectionTestData();
            $original = $data['collection'];

            $filtered = $original->open();
            $sorted = $original->sortByPriority();
            $withNew = $original->add(createTaskForCollection('new-xyz999', 'New'));

            expect($filtered)->not()->toBe($original)
                ->and($sorted)->not()->toBe($original)
                ->and($withNew)->not()->toBe($original);

            // Original should remain unchanged
            expect($original->count())->toBe(6);
        });

        it('preserves original when adding items', function () {
            $data = createTaskCollectionTestData();
            $collection = $data['collection'];

            $originalCount = $collection->count();
            $newTask = createTaskForCollection('preserve-abc123', 'Test');

            $collection->add($newTask);

            expect($collection->count())->toBe($originalCount);
        });
    });

    describe('edge cases', function () {
        it('handles empty collections gracefully', function () {
            $empty = TaskCollection::empty();

            expect($empty->open()->isEmpty())->toBeTrue()
                ->and($empty->highPriority()->isEmpty())->toBeTrue()
                ->and($empty->sortByPriority()->isEmpty())->toBeTrue()
                ->and($empty->map(fn($task) => $task->title()))->toBe([]);
        });

        it('handles single item collections', function () {
            $single = TaskCollection::from([createTaskForCollection('single-abc123', 'Single Task')]);

            expect($single->count())->toBe(1)
                ->and($single->first())->toBe($single->last())
                ->and($single->open()->count())->toBe(1);
        });

        it('maintains type consistency in filtered results', function () {
            $data = createTaskCollectionTestData();
            $collection = $data['collection'];

            $filtered = $collection->open();

            foreach ($filtered as $task) {
                expect($task)->toBeInstanceOf(Task::class)
                    ->and($task->isOpen())->toBeTrue();
            }
        });
    });
});