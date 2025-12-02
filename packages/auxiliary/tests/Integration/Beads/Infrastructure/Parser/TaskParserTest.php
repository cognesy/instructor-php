<?php

declare(strict_types=1);

use Cognesy\Auxiliary\Beads\Domain\Model\TaskType;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Priority;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\TaskId;
use Cognesy\Auxiliary\Beads\Infrastructure\Parser\TaskParser;

describe('TaskParser Integration', function () {
    test('setup works correctly', function () {
        $parser = new TaskParser();
        expect($parser)->toBeInstanceOf(TaskParser::class);
    });

    describe('parsing single task', function () {
        test('parses minimal valid task data', function () {
            $parser = new TaskParser();
            $data = [
                'id' => 'test-abc123',
                'title' => 'Test Task',
                'issue_type' => 'task',
                'priority' => 2,
            ];

            $task = $parser->parse($data);

            expect($task->id())->toBeInstanceOf(TaskId::class)
                ->and($task->id()->value)->toBe('test-abc123')
                ->and($task->title())->toBe('Test Task')
                ->and($task->type())->toBe(TaskType::Task)
                ->and($task->priority())->toBeInstanceOf(Priority::class)
                ->and($task->priority()->value)->toBe(2)
                ->and($task->description())->toBeNull()
                ->and($task->labels())->toBe([]);
        });

        test('parses task data with optional fields', function () {
            $parser = new TaskParser();
            $data = [
                'id' => 'feature-xyz789',
                'title' => 'Add user authentication',
                'issue_type' => 'feature',
                'priority' => 0,
                'description' => 'Implement JWT-based authentication system',
                'labels' => ['backend', 'security', 'api']
            ];

            $task = $parser->parse($data);

            expect($task->id()->value)->toBe('feature-xyz789')
                ->and($task->title())->toBe('Add user authentication')
                ->and($task->type())->toBe(TaskType::Feature)
                ->and($task->priority())->toEqual(Priority::critical())
                ->and($task->description())->toBe('Implement JWT-based authentication system')
                ->and($task->labels())->toBe(['backend', 'security', 'api']);
        });

        test('handles all task types correctly', function () {
            $parser = new TaskParser();
            $types = [
                'task' => TaskType::Task,
                'bug' => TaskType::Bug,
                'feature' => TaskType::Feature,
                'epic' => TaskType::Epic,
            ];

            foreach ($types as $bdType => $expectedType) {
                $data = [
                    'id' => "test-{$bdType}123",  // Use 4+ char hash
                    'title' => "Test {$bdType}",
                    'issue_type' => $bdType,
                    'priority' => 1,
                ];

                $task = $parser->parse($data);

                expect($task->type())->toBe($expectedType);
            }
        });

        test('handles all priority levels correctly', function () {
            $parser = new TaskParser();
            $priorities = [
                0 => Priority::critical(),
                1 => Priority::high(),
                2 => Priority::medium(),
                3 => Priority::low(),
                4 => Priority::backlog(),
            ];

            foreach ($priorities as $bdPriority => $expectedPriority) {
                $data = [
                    'id' => "test-p{$bdPriority}abc",  // Use 4+ char hash
                    'title' => "Priority {$bdPriority} Task",
                    'issue_type' => 'task',
                    'priority' => $bdPriority,
                ];

                $task = $parser->parse($data);

                expect($task->priority())->toEqual($expectedPriority);
            }
        });

        test('handles empty labels array', function () {
            $parser = new TaskParser();
            $data = [
                'id' => 'test-empty123',  // Use 4+ char hash
                'title' => 'Empty Labels Task',
                'issue_type' => 'task',
                'priority' => 2,
                'labels' => []
            ];

            $task = $parser->parse($data);

            expect($task->labels())->toBe([]);
        });

        test('handles null description', function () {
            $parser = new TaskParser();
            $data = [
                'id' => 'test-null456',  // Use 4+ char hash
                'title' => 'Null Description Task',
                'issue_type' => 'task',
                'priority' => 2,
                'description' => null
            ];

            $task = $parser->parse($data);

            expect($task->description())->toBeNull();
        });
    });

    describe('parsing multiple tasks', function () {
        test('parses array of valid tasks', function () {
            $parser = new TaskParser();
            $dataArray = [
                [
                    'id' => 'task-001abc',  // Use 4+ char hash
                    'title' => 'First Task',
                    'issue_type' => 'task',
                    'priority' => 1,
                ],
                [
                    'id' => 'bug-002def',  // Use 4+ char hash
                    'title' => 'Critical Bug',
                    'issue_type' => 'bug',
                    'priority' => 0,
                    'description' => 'Critical bug fix needed'
                ],
                [
                    'id' => 'feature-003xyz',  // Use 4+ char hash
                    'title' => 'New Feature',
                    'issue_type' => 'feature',
                    'priority' => 3,
                    'labels' => ['frontend', 'ui']
                ]
            ];

            $tasks = $parser->parseMany($dataArray);

            expect($tasks)->toHaveCount(3);

            expect($tasks[0]->id()->value)->toBe('task-001abc')
                ->and($tasks[0]->type())->toBe(TaskType::Task)
                ->and($tasks[0]->priority()->value)->toBe(1);

            expect($tasks[1]->id()->value)->toBe('bug-002def')
                ->and($tasks[1]->type())->toBe(TaskType::Bug)
                ->and($tasks[1]->priority())->toEqual(Priority::critical())
                ->and($tasks[1]->description())->toBe('Critical bug fix needed');

            expect($tasks[2]->id()->value)->toBe('feature-003xyz')
                ->and($tasks[2]->type())->toBe(TaskType::Feature)
                ->and($tasks[2]->labels())->toBe(['frontend', 'ui']);
        });

        test('handles empty array', function () {
            $parser = new TaskParser();
            $tasks = $parser->parseMany([]);

            expect($tasks)->toBe([]);
        });
    });

    describe('validation', function () {
        test('throws exception when id is missing', function () {
            $parser = new TaskParser();
            $data = [
                'title' => 'Task without ID',
                'issue_type' => 'task',
                'priority' => 1,
            ];

            expect(fn() => $parser->parse($data))
                ->toThrow(InvalidArgumentException::class, 'Missing required field: id');
        });

        test('throws exception when title is missing', function () {
            $parser = new TaskParser();
            $data = [
                'id' => 'test-123',
                'issue_type' => 'task',
                'priority' => 1,
            ];

            expect(fn() => $parser->parse($data))
                ->toThrow(InvalidArgumentException::class, 'Missing required field: title');
        });

        test('throws exception when issue_type is missing', function () {
            $parser = new TaskParser();
            $data = [
                'id' => 'test-123',
                'title' => 'Task without type',
                'priority' => 1,
            ];

            expect(fn() => $parser->parse($data))
                ->toThrow(InvalidArgumentException::class, 'Missing required field: issue_type');
        });

        test('throws exception when priority is missing', function () {
            $parser = new TaskParser();
            $data = [
                'id' => 'test-123',
                'title' => 'Task without priority',
                'issue_type' => 'task',
            ];

            expect(fn() => $parser->parse($data))
                ->toThrow(InvalidArgumentException::class, 'Missing required field: priority');
        });

        test('throws exception for invalid task type', function () {
            $parser = new TaskParser();
            $data = [
                'id' => 'test-123abc',  // Use valid format
                'title' => 'Invalid type task',
                'issue_type' => 'invalid-type',
                'priority' => 1,
            ];

            expect(fn() => $parser->parse($data))
                ->toThrow(ValueError::class); // Enum validation
        });

        test('throws exception for invalid priority value', function () {
            $parser = new TaskParser();
            $data = [
                'id' => 'test-123def',  // Use valid format
                'title' => 'Invalid priority task',
                'issue_type' => 'task',
                'priority' => 999,  // Invalid priority
            ];

            expect(fn() => $parser->parse($data))
                ->toThrow(InvalidArgumentException::class);
        });
    });

    describe('real-world bd output simulation', function () {
        test('parses realistic bd list output', function () {
            $parser = new TaskParser();
            // Simulated output from `bd list --json`
            $bdOutput = [
                [
                    'id' => 'partnerspot-heow',
                    'title' => 'Implement user authentication',
                    'issue_type' => 'feature',
                    'priority' => 0,
                    'status' => 'open',
                    'assignee' => null,
                    'created_at' => '2024-01-14T10:30:00Z',
                    'updated_at' => '2024-01-14T10:30:00Z',
                    'description' => 'Add JWT-based authentication with role management',
                    'labels' => ['backend', 'authentication', 'security']
                ],
                [
                    'id' => 'partnerspot-m9ej',
                    'title' => 'Fix responsive navigation menu',
                    'issue_type' => 'bug',
                    'priority' => 1,
                    'status' => 'in_progress',
                    'assignee' => 'claude-dev',
                    'created_at' => '2024-01-14T14:20:00Z',
                    'updated_at' => '2024-01-15T09:15:00Z',
                    'description' => null,
                    'labels' => ['frontend', 'ui', 'css']
                ]
            ];

            $tasks = $parser->parseMany($bdOutput);

            expect($tasks)->toHaveCount(2);

            // Verify first task (feature)
            expect($tasks[0]->id()->value)->toBe('partnerspot-heow')
                ->and($tasks[0]->title())->toBe('Implement user authentication')
                ->and($tasks[0]->type())->toBe(TaskType::Feature)
                ->and($tasks[0]->priority())->toEqual(Priority::critical())
                ->and($tasks[0]->description())->toBe('Add JWT-based authentication with role management')
                ->and($tasks[0]->labels())->toBe(['backend', 'authentication', 'security']);

            // Verify second task (bug)
            expect($tasks[1]->id()->value)->toBe('partnerspot-m9ej')
                ->and($tasks[1]->title())->toBe('Fix responsive navigation menu')
                ->and($tasks[1]->type())->toBe(TaskType::Bug)
                ->and($tasks[1]->priority())->toEqual(Priority::high())
                ->and($tasks[1]->description())->toBeNull()
                ->and($tasks[1]->labels())->toBe(['frontend', 'ui', 'css']);
        });
    });
});