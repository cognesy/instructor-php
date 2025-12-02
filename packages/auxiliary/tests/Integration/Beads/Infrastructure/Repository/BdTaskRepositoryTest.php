<?php

declare(strict_types=1);

use Cognesy\Auxiliary\Beads\Domain\Collection\CommentCollection;
use Cognesy\Auxiliary\Beads\Domain\Collection\TaskCollection;
use Cognesy\Auxiliary\Beads\Domain\Exception\TaskNotFoundException;
use Cognesy\Auxiliary\Beads\Domain\Model\Task;
use Cognesy\Auxiliary\Beads\Domain\Model\TaskStatus;
use Cognesy\Auxiliary\Beads\Domain\Model\TaskType;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Agent;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Priority;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\TaskId;
use Cognesy\Auxiliary\Beads\Infrastructure\Client\BdClient;
use Cognesy\Auxiliary\Beads\Infrastructure\Execution\CommandExecutor;
use Cognesy\Auxiliary\Beads\Infrastructure\Execution\ExecutionPolicy;
use Cognesy\Auxiliary\Beads\Infrastructure\Parser\CommentParser;
use Cognesy\Auxiliary\Beads\Infrastructure\Parser\TaskParser;
use Cognesy\Auxiliary\Beads\Infrastructure\Repository\BdTaskRepository;
use Cognesy\Utils\Sandbox\Data\ExecResult;
use Cognesy\Utils\Sandbox\Policy\SandboxPolicy;

// Helper function for creating test tasks
function createTestTaskForRepo(string $id, string $title = 'Test Task'): Task {
    return Task::create(
        id: new TaskId($id),
        title: $title,
        type: TaskType::Task,
        priority: Priority::medium(),
        description: 'Test description'
    );
}

// Helper function to create mock executor for repository tests
function createMockExecutorForRepo(): CommandExecutor {
    return new class implements CommandExecutor {
        public array $executedCommands = [];
        public array $responseQueue = [];
        public int $responseIndex = 0;

        public function __construct() {
            // Default response
            $this->responseQueue[] = new ExecResult(
                stdout: '[]',
                stderr: '',
                exitCode: 0,
                duration: 0.001
            );
        }

        public function execute(array $argv, ?string $stdin = null): ExecResult {
            $this->executedCommands[] = $argv;

            if ($this->responseIndex < count($this->responseQueue)) {
                return $this->responseQueue[$this->responseIndex++];
            }

            // Return last response if we run out
            return $this->responseQueue[count($this->responseQueue) - 1];
        }

        public function policy(): ExecutionPolicy {
            return new ExecutionPolicy(SandboxPolicy::ENABLED);
        }

        public function setNextResult(string $stdout, string $stderr = '', int $exitCode = 0): void {
            $this->responseQueue = [];
            $this->responseIndex = 0;
            $this->addResponse($stdout, $stderr, $exitCode);
        }

        public function addResponse(string $stdout, string $stderr = '', int $exitCode = 0): void {
            $this->responseQueue[] = new ExecResult(
                stdout: $stdout,
                stderr: $stderr,
                exitCode: $exitCode,
                duration: 0.001
            );
        }
    };
}

describe('BdTaskRepository Integration', function () {
    test('findById returns task when found', function () {
        // Use real parsers for integration test
        $mockExecutor = createMockExecutorForRepo();
        $client = new BdClient($mockExecutor, '/usr/bin/bd');
        $taskParser = new TaskParser();
        $commentParser = new CommentParser();

        $repository = new BdTaskRepository($client, $taskParser, $commentParser);

        $taskData = [
            'id' => 'test-abc123',
            'title' => 'Test Task',
            'issue_type' => 'task',
            'priority' => 2
        ];

        // Mock the command execution
        $mockExecutor->setNextResult(json_encode($taskData));

        $result = $repository->findById(new TaskId('test-abc123'));

        expect($result)->not()->toBeNull()
            ->and($result->id()->value)->toBe('test-abc123')
            ->and($result->title())->toBe('Test Task');
    });

    test('findById returns null when task not found', function () {
        $mockExecutor = createMockExecutorForRepo();
        $client = new BdClient($mockExecutor, '/usr/bin/bd');
        $taskParser = new TaskParser();
        $commentParser = new CommentParser();

        $repository = new BdTaskRepository($client, $taskParser, $commentParser);

        // Mock empty response
        $mockExecutor->setNextResult('[]');

        $result = $repository->findById(new TaskId('test-missing'));

        expect($result)->toBeNull();
    });

    test('findById handles client exceptions gracefully', function () {
        $mockExecutor = createMockExecutorForRepo();
        $client = new BdClient($mockExecutor, '/usr/bin/bd');
        $taskParser = new TaskParser();
        $commentParser = new CommentParser();

        $repository = new BdTaskRepository($client, $taskParser, $commentParser);

        // Mock command failure
        $mockExecutor->setNextResult('', 'bd: command not found', 127);

        $result = $repository->findById(new TaskId('test-error'));

        expect($result)->toBeNull();
    });

    test('findByStatus delegates to client correctly', function () {
        $mockExecutor = createMockExecutorForRepo();
        $client = new BdClient($mockExecutor, '/usr/bin/bd');
        $taskParser = new TaskParser();
        $commentParser = new CommentParser();

        $repository = new BdTaskRepository($client, $taskParser, $commentParser);

        $mockData = [
            ['id' => 'test-abc123', 'title' => 'Task 1', 'issue_type' => 'task', 'priority' => 1, 'status' => 'open'],
            ['id' => 'test-def456', 'title' => 'Task 2', 'issue_type' => 'task', 'priority' => 2, 'status' => 'open']
        ];

        $mockExecutor->setNextResult(json_encode($mockData));

        $result = $repository->findByStatus(TaskStatus::Open);

        expect($result)->toBeInstanceOf(TaskCollection::class)
            ->and($result->count())->toBe(2);
    });

    test('findReady delegates to client with correct limit', function () {
        $mockExecutor = createMockExecutorForRepo();
        $client = new BdClient($mockExecutor, '/usr/bin/bd');
        $taskParser = new TaskParser();
        $commentParser = new CommentParser();

        $repository = new BdTaskRepository($client, $taskParser, $commentParser);

        $mockData = [['id' => 'test-abc123', 'title' => 'Ready Task', 'issue_type' => 'task', 'priority' => 1, 'status' => 'open']];

        $mockExecutor->setNextResult(json_encode($mockData));

        $result = $repository->findReady(25);

        expect($result)->toBeInstanceOf(TaskCollection::class);
        expect($mockExecutor->executedCommands[0])->toContain('/usr/bin/bd', 'ready', '--limit=25', '--json');
    });

    test('save updates task correctly', function () {
        $mockExecutor = createMockExecutorForRepo();
        $client = new BdClient($mockExecutor, '/usr/bin/bd');
        $taskParser = new TaskParser();
        $commentParser = new CommentParser();

        $repository = new BdTaskRepository($client, $taskParser, $commentParser);

        $agent = new Agent('claude-123', 'Claude AI');

        // Create task with updates using reflection
        $reflection = new ReflectionClass(Task::class);
        $constructor = $reflection->getConstructor();
        $constructor->setAccessible(true);

        $task = $reflection->newInstanceWithoutConstructor();
        $constructor->invoke(
            $task,
            new TaskId('test-abc123'),
            'Updated Task',
            TaskStatus::InProgress,
            TaskType::Task,
            Priority::high(),
            $agent,
            'Test description',
            [],
            new DateTimeImmutable(),
            new DateTimeImmutable(),
            null
        );

        $mockExecutor->setNextResult('{"status": "updated"}');

        $repository->save($task);

        // Test passes if no exception thrown
        expect($mockExecutor->executedCommands[0])->toContain('/usr/bin/bd', 'update', 'test-abc123');
    });

    test('delete closes non-closed task', function () {
        $mockExecutor = createMockExecutorForRepo();
        $client = new BdClient($mockExecutor, '/usr/bin/bd');
        $taskParser = new TaskParser();
        $commentParser = new CommentParser();

        $repository = new BdTaskRepository($client, $taskParser, $commentParser);

        $taskData = ['id' => 'test-abc123', 'title' => 'Task to delete', 'issue_type' => 'task', 'priority' => 1, 'status' => 'open'];

        // Set up sequence of responses: first findById returns task, then close succeeds
        $mockExecutor->setNextResult(json_encode($taskData));
        $mockExecutor->addResponse('{"status": "closed"}');

        $repository->delete(new TaskId('test-abc123'));

        // Should have called both show and close
        expect($mockExecutor->executedCommands)->toHaveCount(2);
    });

    test('delete throws exception for non-existent task', function () {
        $mockExecutor = createMockExecutorForRepo();
        $client = new BdClient($mockExecutor, '/usr/bin/bd');
        $taskParser = new TaskParser();
        $commentParser = new CommentParser();

        $repository = new BdTaskRepository($client, $taskParser, $commentParser);

        $mockExecutor->setNextResult('[]');

        expect(fn() => $repository->delete(new TaskId('test-missing')))
            ->toThrow(TaskNotFoundException::class);
    });

    test('getComments delegates to client and comment parser', function () {
        $mockExecutor = createMockExecutorForRepo();
        $client = new BdClient($mockExecutor, '/usr/bin/bd');
        $taskParser = new TaskParser();
        $commentParser = new CommentParser();

        $repository = new BdTaskRepository($client, $taskParser, $commentParser);

        $mockCommentData = [
            [
                'id' => 1,  // Use numeric IDs
                'issue_id' => 'test-abc123',
                'author' => 'claude',
                'text' => 'First comment',
                'created_at' => '2024-01-01T10:00:00Z'
            ],
            [
                'id' => 2,  // Use numeric IDs
                'issue_id' => 'test-abc123',
                'author' => 'user',
                'text' => 'Second comment',
                'created_at' => '2024-01-01T11:00:00Z'
            ]
        ];

        $mockExecutor->setNextResult(json_encode($mockCommentData));

        $result = $repository->getComments(new TaskId('test-abc123'));

        expect($result)->toBeInstanceOf(CommentCollection::class);
        expect($mockExecutor->executedCommands[0])->toContain('/usr/bin/bd', 'comments', 'test-abc123');
    });
});