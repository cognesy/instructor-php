<?php

declare(strict_types=1);

use Cognesy\Auxiliary\Beads\Infrastructure\Client\BdClient;
use Cognesy\Auxiliary\Beads\Infrastructure\Execution\CommandExecutor;
use Cognesy\Auxiliary\Beads\Infrastructure\Execution\ExecutionPolicy;
use Cognesy\Sandbox\Data\ExecResult;
use Cognesy\Sandbox\Policy\SandboxPolicy;

// Helper function to create mock executor for BdClient tests
function createMockExecutor(): CommandExecutor {
    return new class implements CommandExecutor {
        public array $executedCommands = [];
        public ExecResult $nextResult;

        public function __construct() {
            $this->nextResult = new ExecResult(
                stdout: '[]',
                stderr: '',
                exitCode: 0,
                duration: 0.001
            );
        }

        public function execute(array $argv, ?string $stdin = null): ExecResult {
            $this->executedCommands[] = $argv;
            return $this->nextResult;
        }

        public function policy(): ExecutionPolicy {
            return new ExecutionPolicy(SandboxPolicy::ENABLED);
        }

        public function setNextResult(string $stdout, string $stderr = '', int $exitCode = 0): void {
            $this->nextResult = new ExecResult(
                stdout: $stdout,
                stderr: $stderr,
                exitCode: $exitCode,
                duration: 0.001
            );
        }
    };
}

describe('BdClient Integration', function () {
    describe('basic execution', function () {
        test('executes bd commands with automatic JSON flag', function () {
            $mockExecutor = createMockExecutor();
            $client = new BdClient($mockExecutor, '/usr/bin/bd');

            $mockExecutor->setNextResult('{"result": "success"}');

            $result = $client->execute(['list']);

            expect($mockExecutor->executedCommands)->toHaveCount(1);
            expect($mockExecutor->executedCommands[0])->toBe(['/usr/bin/bd', 'list', '--json']);
            expect($result)->toBe(['result' => 'success']);
        });

        test('preserves existing JSON flag', function () {
            $mockExecutor = createMockExecutor();
            $client = new BdClient($mockExecutor, '/usr/bin/bd');

            $mockExecutor->setNextResult('[]');

            $client->execute(['list', '--json']);

            expect($mockExecutor->executedCommands[0])->toBe(['/usr/bin/bd', 'list', '--json']);
        });

        test('returns empty array for empty output', function () {
            $mockExecutor = createMockExecutor();
            $client = new BdClient($mockExecutor, '/usr/bin/bd');

            $mockExecutor->setNextResult('');

            $result = $client->execute(['list']);

            expect($result)->toBe([]);
        });

        test('throws exception on command failure', function () {
            $mockExecutor = createMockExecutor();
            $client = new BdClient($mockExecutor, '/usr/bin/bd');

            $mockExecutor->setNextResult('', 'bd: command not found', 127);

            expect(fn() => $client->execute(['list']))
                ->toThrow(RuntimeException::class, 'bd command failed: bd: command not found');
        });

        test('throws exception on invalid JSON', function () {
            $mockExecutor = createMockExecutor();
            $client = new BdClient($mockExecutor, '/usr/bin/bd');

            $mockExecutor->setNextResult('invalid json output');

            expect(fn() => $client->execute(['list']))
                ->toThrow(JsonException::class);
        });
    });

    describe('task operations', function () {
        test('lists tasks without filters', function () {
            $mockExecutor = createMockExecutor();
            $client = new BdClient($mockExecutor, '/usr/bin/bd');

            $mockResponse = [
                ['id' => 'task-1', 'title' => 'First task'],
                ['id' => 'task-2', 'title' => 'Second task']
            ];
            $mockExecutor->setNextResult(json_encode($mockResponse));

            $result = $client->list();

            expect($mockExecutor->executedCommands[0])->toBe(['/usr/bin/bd', 'list', '--json']);
            expect($result)->toBe($mockResponse);
        });

        test('gets ready tasks with custom limit', function () {
            $mockExecutor = createMockExecutor();
            $client = new BdClient($mockExecutor, '/usr/bin/bd');

            $mockExecutor->setNextResult('[]');

            $client->ready(25);

            expect($mockExecutor->executedCommands[0])->toBe(['/usr/bin/bd', 'ready', '--limit=25', '--json']);
        });

        test('shows task details', function () {
            $mockExecutor = createMockExecutor();
            $client = new BdClient($mockExecutor, '/usr/bin/bd');

            $mockTask = ['id' => 'task-123', 'title' => 'Test task'];
            $mockExecutor->setNextResult(json_encode($mockTask));

            $result = $client->show('task-123');

            expect($mockExecutor->executedCommands[0])->toBe(['/usr/bin/bd', 'show', 'task-123', '--json']);
            expect($result)->toBe($mockTask);
        });

        test('creates task with data', function () {
            $mockExecutor = createMockExecutor();
            $client = new BdClient($mockExecutor, '/usr/bin/bd');

            $mockResponse = ['id' => 'new-task-123', 'status' => 'created'];
            $mockExecutor->setNextResult(json_encode($mockResponse));

            $result = $client->create([
                'title' => 'New Task',
                'type' => 'feature',
                'priority' => 1
            ]);

            $command = $mockExecutor->executedCommands[0];
            expect($command)->toContain('/usr/bin/bd', 'create', '--title=New Task', '--type=feature', '--priority=1', '--json');
            expect($result)->toBe($mockResponse);
        });

        test('updates task with data', function () {
            $mockExecutor = createMockExecutor();
            $client = new BdClient($mockExecutor, '/usr/bin/bd');

            $mockExecutor->setNextResult('{"id": "task-123", "status": "updated"}');

            $client->update('task-123', [
                'status' => 'in_progress',
                'assignee' => 'claude'
            ]);

            $command = $mockExecutor->executedCommands[0];
            expect($command)->toContain('/usr/bin/bd', 'update', 'task-123', '--status=in_progress', '--assignee=claude', '--json');
        });

        test('closes task with reason', function () {
            $mockExecutor = createMockExecutor();
            $client = new BdClient($mockExecutor, '/usr/bin/bd');

            $mockExecutor->setNextResult('{"id": "task-123", "status": "closed"}');

            $client->close('task-123', 'Task completed successfully');

            $command = $mockExecutor->executedCommands[0];
            expect($command)->toBe(['/usr/bin/bd', 'close', 'task-123', '--reason=Task completed successfully', '--json']);
        });
    });

    describe('error handling', function () {
        test('handles bd command with non-zero exit code', function () {
            $mockExecutor = createMockExecutor();
            $client = new BdClient($mockExecutor, '/usr/bin/bd');

            $mockExecutor->setNextResult('', 'Task not found', 1);

            expect(fn() => $client->show('nonexistent-task'))
                ->toThrow(RuntimeException::class, 'bd command failed: Task not found');
        });

        test('includes exit code in exception', function () {
            $mockExecutor = createMockExecutor();
            $client = new BdClient($mockExecutor, '/usr/bin/bd');

            $mockExecutor->setNextResult('', 'Permission denied', 13);

            try {
                $client->list();
                expect(false)->toBe(true, 'Expected RuntimeException');
            } catch (RuntimeException $e) {
                expect($e->getCode())->toBe(13);
            }
        });
    });
});