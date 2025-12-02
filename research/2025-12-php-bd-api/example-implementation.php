<?php declare(strict_types=1);

/**
 * Minimal Working Example: PHP API for bd/bv Operations
 *
 * This demonstrates a basic but functional implementation of the CLI wrapper approach.
 * Suitable for prototyping or as a starting point for a full implementation.
 *
 * Usage:
 *   php example-implementation.php
 */

namespace BeadsApi;

use RuntimeException;
use InvalidArgumentException;

// ============================================================================
// Core Abstractions
// ============================================================================

interface CanExecuteCommand
{
    public function execute(array $command, array $options = []): CommandResult;
}

readonly class CommandResult
{
    public function __construct(
        public int $exitCode,
        public string $stdout,
        public string $stderr,
        public float $executionTime,
    ) {}

    public function isSuccess(): bool
    {
        return $this->exitCode === 0;
    }

    public function json(): array
    {
        if (!$this->isSuccess()) {
            throw new BeadsException("Command failed: {$this->stderr}");
        }

        $decoded = json_decode($this->stdout, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new BeadsException('Invalid JSON response: ' . json_last_error_msg());
        }

        return $decoded;
    }

    public function throw(): void
    {
        if (!$this->isSuccess()) {
            throw new BeadsException(
                "Command failed with exit code {$this->exitCode}: {$this->stderr}"
            );
        }
    }
}

// ============================================================================
// Exceptions
// ============================================================================

class BeadsException extends RuntimeException {}
class BdCommandException extends BeadsException {}
class BdTimeoutException extends BeadsException {}
class IssueNotFoundException extends BeadsException {}

// ============================================================================
// Command Executor
// ============================================================================

class SymfonyProcessExecutor implements CanExecuteCommand
{
    public function __construct(
        private readonly int $defaultTimeout = 30,
    ) {}

    public function execute(array $command, array $options = []): CommandResult
    {
        $timeout = $options['timeout'] ?? $this->defaultTimeout;
        $cwd = $options['cwd'] ?? getcwd();
        $env = $options['env'] ?? null;

        $startTime = microtime(true);

        // Build command string
        $commandStr = implode(' ', array_map('escapeshellarg', $command));

        // Execute command
        $descriptorspec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = proc_open($commandStr, $descriptorspec, $pipes, $cwd, $env);

        if (!is_resource($process)) {
            throw new BdCommandException('Failed to execute command');
        }

        // Close stdin
        fclose($pipes[0]);

        // Read stdout and stderr with timeout
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $endTime = time() + $timeout;

        while (time() < $endTime) {
            $stdout .= stream_get_contents($pipes[1]) ?: '';
            $stderr .= stream_get_contents($pipes[2]) ?: '';

            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }

            usleep(10000); // 10ms
        }

        // Final read
        $stdout .= stream_get_contents($pipes[1]) ?: '';
        $stderr .= stream_get_contents($pipes[2]) ?: '';

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        $executionTime = microtime(true) - $startTime;

        return new CommandResult($exitCode, $stdout, $stderr, $executionTime);
    }
}

// ============================================================================
// Value Objects
// ============================================================================

readonly class Issue
{
    public function __construct(
        public string $id,
        public string $title,
        public string $status,
        public string $type,
        public int $priority,
        public ?string $assignee,
        public ?string $description,
        public array $labels,
        public string $createdAt,
        public ?string $updatedAt,
        public ?string $closedAt,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            title: $data['title'],
            status: $data['status'],
            type: $data['type'] ?? 'task',
            priority: $data['priority'] ?? 2,
            assignee: $data['assignee'] ?? null,
            description: $data['description'] ?? null,
            labels: $data['labels'] ?? [],
            createdAt: $data['created_at'],
            updatedAt: $data['updated_at'] ?? null,
            closedAt: $data['closed_at'] ?? null,
        );
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }
}

class IssueCollection
{
    private array $items;

    public function __construct(array $items)
    {
        $this->items = array_map(
            fn($item) => $item instanceof Issue ? $item : Issue::fromArray($item),
            $items
        );
    }

    public function all(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function first(): ?Issue
    {
        return $this->items[0] ?? null;
    }

    public function filter(callable $callback): self
    {
        return new self(array_filter($this->items, $callback));
    }

    public function map(callable $callback): array
    {
        return array_map($callback, $this->items);
    }
}

readonly class CreateIssueRequest
{
    public function __construct(
        public string $title,
        public string $type = 'task',
        public int $priority = 2,
        public ?string $description = null,
        public ?string $assignee = null,
        public array $labels = [],
    ) {
        if (!in_array($this->type, ['task', 'bug', 'feature', 'epic'], true)) {
            throw new InvalidArgumentException("Invalid type: {$this->type}");
        }

        if ($this->priority < 0 || $this->priority > 4) {
            throw new InvalidArgumentException("Priority must be 0-4");
        }
    }
}

readonly class UpdateIssueRequest
{
    public function __construct(
        public ?string $title = null,
        public ?string $status = null,
        public ?int $priority = null,
        public ?string $description = null,
        public ?string $assignee = null,
    ) {}
}

class IssueFilter
{
    private array $filters = [];

    public static function create(): self
    {
        return new self();
    }

    public static function open(): self
    {
        return (new self())->status('open');
    }

    public static function closed(): self
    {
        return (new self())->status('closed');
    }

    public function status(string $status): self
    {
        $this->filters['status'] = $status;
        return $this;
    }

    public function priority(int $priority): self
    {
        $this->filters['priority'] = $priority;
        return $this;
    }

    public function assignee(string $assignee): self
    {
        $this->filters['assignee'] = $assignee;
        return $this;
    }

    public function type(string $type): self
    {
        $this->filters['type'] = $type;
        return $this;
    }

    public function toCommandArgs(): array
    {
        $args = [];
        foreach ($this->filters as $key => $value) {
            $args[] = "--{$key}={$value}";
        }
        return $args;
    }
}

enum DependencyType: string
{
    case Blocks = 'blocks';
    case Related = 'related';
    case Parent = 'parent';
    case DiscoveredFrom = 'discovered-from';
}

// ============================================================================
// bd Client
// ============================================================================

class BdClient
{
    private const ALLOWED_COMMANDS = [
        'list', 'show', 'create', 'update', 'close', 'delete',
        'ready', 'blocked', 'stats', 'dep', 'comments', 'sync', 'export',
    ];

    public function __construct(
        private readonly CanExecuteCommand $executor,
        private readonly string $workingDir,
        private readonly string $bdBinary = '/usr/local/bin/bd',
    ) {}

    // ============================================================================
    // Read Operations
    // ============================================================================

    public function list(?IssueFilter $filter = null): IssueCollection
    {
        $args = ['list', '--json'];

        if ($filter !== null) {
            $args = array_merge($args, $filter->toCommandArgs());
        }

        $result = $this->execute($args);
        return new IssueCollection($result->json());
    }

    public function show(string $id): Issue
    {
        $result = $this->execute(['show', $id, '--json']);
        return Issue::fromArray($result->json());
    }

    public function ready(int $limit = 10): IssueCollection
    {
        $result = $this->execute(['ready', '--json', "--limit={$limit}"]);
        return new IssueCollection($result->json());
    }

    public function blocked(): IssueCollection
    {
        $result = $this->execute(['blocked', '--json']);
        return new IssueCollection($result->json());
    }

    public function stats(): array
    {
        $result = $this->execute(['stats', '--json']);
        return $result->json();
    }

    // ============================================================================
    // Write Operations
    // ============================================================================

    public function create(CreateIssueRequest $request): Issue
    {
        $args = [
            'create',
            '--title=' . $request->title,
            '--type=' . $request->type,
            '--priority=' . $request->priority,
            '--json',
        ];

        if ($request->description !== null) {
            $args[] = '--description=' . $request->description;
        }

        if ($request->assignee !== null) {
            $args[] = '--assignee=' . $request->assignee;
        }

        foreach ($request->labels as $label) {
            $args[] = '--label=' . $label;
        }

        $result = $this->execute($args);
        return Issue::fromArray($result->json());
    }

    public function update(string $id, UpdateIssueRequest $request): Issue
    {
        $args = ['update', $id, '--json'];

        if ($request->title !== null) {
            $args[] = '--title=' . $request->title;
        }

        if ($request->status !== null) {
            $args[] = '--status=' . $request->status;
        }

        if ($request->priority !== null) {
            $args[] = '--priority=' . $request->priority;
        }

        if ($request->description !== null) {
            $args[] = '--description=' . $request->description;
        }

        if ($request->assignee !== null) {
            $args[] = '--assignee=' . $request->assignee;
        }

        $result = $this->execute($args);
        return Issue::fromArray($result->json());
    }

    public function close(string $id, string $reason): Issue
    {
        $result = $this->execute([
            'close',
            $id,
            '--reason=' . $reason,
            '--json',
        ]);

        return Issue::fromArray($result->json());
    }

    public function delete(string $id): void
    {
        $this->execute(['delete', $id, '--json'])->throw();
    }

    // ============================================================================
    // Dependencies
    // ============================================================================

    public function addDependency(
        string $issueId,
        string $blockedBy,
        DependencyType $type = DependencyType::Blocks
    ): void {
        $this->execute([
            'dep',
            'add',
            $issueId,
            $blockedBy,
            '--type=' . $type->value,
            '--json',
        ])->throw();
    }

    public function removeDependency(string $issueId, string $blockedBy): void
    {
        $this->execute([
            'dep',
            'remove',
            $issueId,
            $blockedBy,
            '--json',
        ])->throw();
    }

    public function dependencyTree(string $id): array
    {
        $result = $this->execute(['dep', 'tree', $id, '--json']);
        return $result->json();
    }

    // ============================================================================
    // Comments
    // ============================================================================

    public function addComment(string $id, string $comment): void
    {
        $this->execute([
            'comments',
            'add',
            $id,
            $comment,
            '--json',
        ])->throw();
    }

    public function getComments(string $id): array
    {
        $result = $this->execute(['comments', $id, '--json']);
        return $result->json();
    }

    // ============================================================================
    // Sync
    // ============================================================================

    public function sync(): array
    {
        $result = $this->execute(['sync', '--json']);
        return $result->json();
    }

    public function export(): array
    {
        $result = $this->execute(['export', '--json']);
        return $result->json();
    }

    // ============================================================================
    // Internal
    // ============================================================================

    private function execute(array $args, int $maxAttempts = 3): CommandResult
    {
        $this->validateCommand($args[0] ?? '');

        $command = array_merge([$this->bdBinary], $args);

        $attempt = 0;
        while ($attempt < $maxAttempts) {
            try {
                $result = $this->executor->execute($command, [
                    'cwd' => $this->workingDir,
                ]);

                if ($result->isSuccess()) {
                    return $result;
                }

                // Check if it's a lock error (retry-able)
                if ($result->exitCode === 5 && $attempt < $maxAttempts - 1) {
                    $attempt++;
                    usleep(100000 * $attempt); // Exponential backoff
                    continue;
                }

                // Non-retryable error
                throw new BdCommandException($result->stderr);
            } catch (BdTimeoutException $e) {
                throw $e; // Don't retry timeouts
            }
        }

        throw new BdCommandException('Max retry attempts exceeded');
    }

    private function validateCommand(string $command): void
    {
        if (!in_array($command, self::ALLOWED_COMMANDS, true)) {
            throw new InvalidArgumentException("Invalid bd command: {$command}");
        }
    }
}

// ============================================================================
// bv Client
// ============================================================================

class BvClient
{
    public function __construct(
        private readonly CanExecuteCommand $executor,
        private readonly string $workingDir,
        private readonly string $bvBinary = '/usr/local/bin/bv',
    ) {}

    public function insights(): array
    {
        $result = $this->execute(['--robot-insights']);
        return $result->json();
    }

    public function plan(): array
    {
        $result = $this->execute(['--robot-plan']);
        return $result->json();
    }

    public function priority(): array
    {
        $result = $this->execute(['--robot-priority']);
        return $result->json();
    }

    public function recipes(): array
    {
        $result = $this->execute(['--robot-recipes']);
        return $result->json();
    }

    public function diff(string $since): array
    {
        $result = $this->execute(['--robot-diff', '--diff-since', $since]);
        return $result->json();
    }

    private function execute(array $args): CommandResult
    {
        $command = array_merge([$this->bvBinary], $args);

        $result = $this->executor->execute($command, [
            'cwd' => $this->workingDir,
        ]);

        if (!$result->isSuccess()) {
            throw new BeadsException("bv command failed: {$result->stderr}");
        }

        return $result;
    }
}

// ============================================================================
// Unified Facade
// ============================================================================

class Beads
{
    private BdClient $bd;
    private BvClient $bv;

    public function __construct(
        CanExecuteCommand $executor,
        string $workingDir,
        string $bdBinary = '/usr/local/bin/bd',
        string $bvBinary = '/usr/local/bin/bv',
    ) {
        $this->bd = new BdClient($executor, $workingDir, $bdBinary);
        $this->bv = new BvClient($executor, $workingDir, $bvBinary);
    }

    // ============================================================================
    // Convenience Methods
    // ============================================================================

    public function issues(?IssueFilter $filter = null): IssueCollection
    {
        return $this->bd->list($filter);
    }

    public function openIssues(): IssueCollection
    {
        return $this->bd->list(IssueFilter::open());
    }

    public function closedIssues(): IssueCollection
    {
        return $this->bd->list(IssueFilter::closed());
    }

    public function readyWork(int $limit = 10): IssueCollection
    {
        return $this->bd->ready($limit);
    }

    public function blockedIssues(): IssueCollection
    {
        return $this->bd->blocked();
    }

    public function find(string $id): Issue
    {
        return $this->bd->show($id);
    }

    public function create(CreateIssueRequest $request): Issue
    {
        return $this->bd->create($request);
    }

    public function update(string $id, UpdateIssueRequest $request): Issue
    {
        return $this->bd->update($id, $request);
    }

    public function close(string $id, string $reason): Issue
    {
        return $this->bd->close($id, $reason);
    }

    public function insights(): array
    {
        return $this->bv->insights();
    }

    public function plan(): array
    {
        return $this->bv->plan();
    }

    public function priority(): array
    {
        return $this->bv->priority();
    }

    public function stats(): array
    {
        return $this->bd->stats();
    }

    // ============================================================================
    // Direct Access to Clients
    // ============================================================================

    public function bd(): BdClient
    {
        return $this->bd;
    }

    public function bv(): BvClient
    {
        return $this->bv;
    }
}

// ============================================================================
// Usage Examples
// ============================================================================

function demonstrateUsage(): void
{
    $executor = new SymfonyProcessExecutor();
    $beads = new Beads($executor, getcwd());

    echo "=== bd/bv PHP API Demo ===\n\n";

    try {
        // List open issues
        echo "Open Issues:\n";
        $openIssues = $beads->openIssues();
        foreach ($openIssues->all() as $issue) {
            echo "  [{$issue->id}] {$issue->title} (P{$issue->priority})\n";
        }
        echo "\n";

        // Show ready work
        echo "Ready to Work:\n";
        $ready = $beads->readyWork(5);
        foreach ($ready->all() as $issue) {
            echo "  [{$issue->id}] {$issue->title}\n";
        }
        echo "\n";

        // Get statistics
        echo "Project Stats:\n";
        $stats = $beads->stats();
        echo "  Open: {$stats['open']}\n";
        echo "  Closed: {$stats['closed']}\n";
        echo "  In Progress: {$stats['in_progress']}\n";
        echo "\n";

        // Get insights (graph metrics)
        echo "Graph Insights:\n";
        $insights = $beads->insights();
        echo "  Density: {$insights['density']}\n";
        echo "  Cycles: " . count($insights['cycles']) . "\n";
        echo "\n";

        // Get execution plan
        echo "Execution Plan:\n";
        $plan = $beads->plan();
        echo "  Parallel Tracks: " . count($plan['tracks']) . "\n";
        echo "  Summary: {$plan['summary']}\n";
        echo "\n";

        // Create a new issue (commented out to avoid pollution)
        /*
        echo "Creating Issue:\n";
        $issue = $beads->create(new CreateIssueRequest(
            title: '[test] PHP API test issue',
            type: 'task',
            priority: 2,
            description: 'Testing the PHP API wrapper',
        ));
        echo "  Created: [{$issue->id}] {$issue->title}\n";
        echo "\n";

        // Update the issue
        echo "Updating Issue:\n";
        $updated = $beads->update($issue->id, new UpdateIssueRequest(
            status: 'in_progress'
        ));
        echo "  Status: {$updated->status}\n";
        echo "\n";

        // Close the issue
        echo "Closing Issue:\n";
        $closed = $beads->close($issue->id, 'Testing complete');
        echo "  Closed: {$closed->closedAt}\n";
        echo "\n";
        */

    } catch (BeadsException $e) {
        echo "Error: {$e->getMessage()}\n";
        exit(1);
    }

    echo "=== Demo Complete ===\n";
}

// Run the demo if executed directly
if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    demonstrateUsage();
}
