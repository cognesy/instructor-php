<?php declare(strict_types=1);

/**
 * Enhanced BdClient API for Agent Collaboration
 *
 * Fluent, expressive API designed for multi-agent workflows with superior DX.
 * Implements patterns from AGENT-COLLABORATION.md
 */

namespace BeadsApi\Enhanced;

use Cognesy\Utils\Sandbox\Sandbox;
use Cognesy\Utils\Sandbox\Config\ExecutionPolicy;
use Cognesy\Utils\Sandbox\Contracts\CanExecuteCommand;
use Cognesy\Utils\Sandbox\Data\ExecResult;
use DateInterval;
use DateTime;

// ============================================================================
// Enhanced BdClient
// ============================================================================

class BdClient
{
    private ?Agent $currentAgent = null;

    public function __construct(
        private readonly CanExecuteCommand $executor,
        private readonly string $bdBinary = '/usr/local/bin/bd',
        private readonly int $maxRetries = 3,
    ) {}

    // ============================================================================
    // Factory
    // ============================================================================

    public static function create(string $workingDir, ?string $bdBinary = null): self
    {
        $policy = ExecutionPolicy::in($workingDir)
            ->withTimeout(30)
            ->withIdleTimeout(10)
            ->withOutputCaps(1024 * 1024, 1024 * 1024);

        $executor = Sandbox::host($policy);

        return new self($executor, $bdBinary ?? '/usr/local/bin/bd');
    }

    // ============================================================================
    // Agent Identity
    // ============================================================================

    public function as(string|Agent $agent): self
    {
        $this->currentAgent = $agent instanceof Agent ? $agent : new Agent($agent);
        return $this;
    }

    public function currentAgent(): ?Agent
    {
        return $this->currentAgent;
    }

    // ============================================================================
    // Work Discovery
    // ============================================================================

    public function ready(int $limit = 10): TaskCollection
    {
        $result = $this->execute(['ready', '--json', "--limit={$limit}"]);
        return new TaskCollection($this, json_decode($result->stdout(), true));
    }

    public function available(int $limit = 10): TaskCollection
    {
        return $this->ready($limit)->unassigned();
    }

    public function mine(): TaskCollection
    {
        if (!$this->currentAgent) {
            throw new \RuntimeException('No agent identity set. Call as() first.');
        }

        $result = $this->execute([
            'list',
            '--assignee=' . $this->currentAgent->id,
            '--json',
        ]);

        return new TaskCollection($this, json_decode($result->stdout(), true));
    }

    public function myActiveWork(): TaskCollection
    {
        return $this->mine()->inProgress();
    }

    public function assignedTo(string|Agent $agent): TaskCollection
    {
        $agentId = $agent instanceof Agent ? $agent->id : $agent;

        $result = $this->execute([
            'list',
            "--assignee={$agentId}",
            '--json',
        ]);

        return new TaskCollection($this, json_decode($result->stdout(), true));
    }

    public function blocked(): TaskCollection
    {
        $result = $this->execute(['blocked', '--json']);
        return new TaskCollection($this, json_decode($result->stdout(), true));
    }

    public function nextTask(): ?Task
    {
        $available = $this->available(limit: 1);
        return $available->first();
    }

    // ============================================================================
    // Task Creation (Fluent)
    // ============================================================================

    public function task(string $title): TaskBuilder
    {
        return new TaskBuilder($this, $title);
    }

    public function epic(string $title): EpicBuilder
    {
        return new EpicBuilder($this, $title);
    }

    public function createTask(
        string $title,
        string $type = 'task',
        int $priority = 2,
    ): Task {
        return $this->task($title)
            ->type($type)
            ->priority($priority)
            ->create();
    }

    public function createTasks(array $tasks): TaskCollection
    {
        $created = [];

        foreach ($tasks as $taskData) {
            $builder = $this->task($taskData['title']);

            if (isset($taskData['type'])) $builder->type($taskData['type']);
            if (isset($taskData['priority'])) $builder->priority($taskData['priority']);
            if (isset($taskData['description'])) $builder->description($taskData['description']);
            if (isset($taskData['assignee'])) $builder->assignTo($taskData['assignee']);

            $created[] = $builder->create();
        }

        return new TaskCollection($this, $created);
    }

    // ============================================================================
    // Task Retrieval
    // ============================================================================

    public function find(string $id): Task
    {
        $result = $this->execute(['show', $id, '--json']);
        $data = json_decode($result->stdout(), true);

        return new Task($this, $data);
    }

    public function findMany(array $ids): TaskCollection
    {
        $tasks = array_map(fn($id) => $this->find($id), $ids);
        return new TaskCollection($this, $tasks);
    }

    public function findWithDependencies(string $id): Task
    {
        $task = $this->find($id);
        $tree = $this->dependencyTree($id);

        return $task->withDependencyTree($tree);
    }

    public function search(string $query): TaskCollection
    {
        $result = $this->execute(['list', '--json']);
        $allTasks = json_decode($result->stdout(), true);

        $filtered = array_filter($allTasks, function($task) use ($query) {
            return str_contains(strtolower($task['title']), strtolower($query))
                || str_contains(strtolower($task['description'] ?? ''), strtolower($query));
        });

        return new TaskCollection($this, array_values($filtered));
    }

    // ============================================================================
    // Commenting & Communication
    // ============================================================================

    public function comment(string $taskId, string $message): void
    {
        $this->execute(['comments', 'add', $taskId, $message, '--json']);
    }

    public function mention(string $taskId, string $agent, string $message): void
    {
        $mentionMessage = "@{$agent} {$message}";
        $this->comment($taskId, $mentionMessage);
    }

    public function comments(string $taskId): CommentCollection
    {
        $result = $this->execute(['comments', $taskId, '--json']);
        $data = json_decode($result->stdout(), true);

        return new CommentCollection($data);
    }

    public function recentComments(int $limit = 20): CommentCollection
    {
        // Get all open tasks
        $result = $this->execute(['list', '--status=open', '--json']);
        $tasks = json_decode($result->stdout(), true);

        $allComments = [];
        foreach ($tasks as $task) {
            $taskComments = $this->comments($task['id']);
            foreach ($taskComments->all() as $comment) {
                $comment->taskId = $task['id'];
                $allComments[] = $comment;
            }
        }

        // Sort by timestamp, take latest
        usort($allComments, fn($a, $b) => $b->createdAt <=> $a->createdAt);

        return new CommentCollection(array_slice($allComments, 0, $limit));
    }

    public function mentions(): TaskCollection
    {
        if (!$this->currentAgent) {
            throw new \RuntimeException('No agent identity set. Call as() first.');
        }

        $allTasks = $this->execute(['list', '--status=open', '--json']);
        $tasks = json_decode($allTasks->stdout(), true);

        $mentioned = array_filter($tasks, function($taskData) {
            $comments = $this->comments($taskData['id']);
            return $comments->mentionsAgent($this->currentAgent->id);
        });

        return new TaskCollection($this, array_values($mentioned));
    }

    // ============================================================================
    // Batch Operations
    // ============================================================================

    public function updateMany(array $taskIds, array $updates): void
    {
        foreach ($taskIds as $id) {
            $args = ['update', $id, '--json'];

            foreach ($updates as $key => $value) {
                $args[] = "--{$key}={$value}";
            }

            $this->execute($args);
        }
    }

    public function closeMany(array $taskIds, string $reason): void
    {
        foreach ($taskIds as $id) {
            $this->execute(['close', $id, "--reason={$reason}", '--json']);
        }
    }

    public function assignMany(array $taskIds, string|Agent $agent): void
    {
        $agentId = $agent instanceof Agent ? $agent->id : $agent;

        $this->updateMany($taskIds, ['assignee' => $agentId]);
    }

    // ============================================================================
    // Session Recovery
    // ============================================================================

    public function sessionContext(): SessionContext
    {
        if (!$this->currentAgent) {
            return new SessionContext(null, new TaskCollection($this, []), new TaskCollection($this, []), new TaskCollection($this, []), new CommentCollection([]), null);
        }

        $activeTasks = $this->myActiveWork();
        $currentTask = $activeTasks->first();

        $recentlyCompleted = $this->mine()
            ->closed()
            ->sortBy('closedAt', descending: true)
            ->take(5);

        $mentions = $this->mentions();
        $recentComments = $this->recentComments(10);

        return new SessionContext(
            $currentTask,
            $activeTasks,
            $recentlyCompleted,
            $mentions,
            $recentComments,
            null // TODO: Implement checkpoint system
        );
    }

    // ============================================================================
    // Dependencies
    // ============================================================================

    public function addDependency(string $issueId, string $blockedBy, string $type = 'blocks'): void
    {
        $this->execute([
            'dep',
            'add',
            $issueId,
            $blockedBy,
            "--type={$type}",
            '--json',
        ]);
    }

    public function removeDependency(string $issueId, string $blockedBy): void
    {
        $this->execute(['dep', 'remove', $issueId, $blockedBy, '--json']);
    }

    public function dependencyTree(string $id): array
    {
        $result = $this->execute(['dep', 'tree', $id, '--json']);
        return json_decode($result->stdout(), true);
    }

    // ============================================================================
    // Low-level Operations
    // ============================================================================

    public function _execute(array $args): ExecResult
    {
        return $this->execute($args);
    }

    // ============================================================================
    // Internal
    // ============================================================================

    private function execute(array $args, int $attempt = 0): ExecResult
    {
        $command = array_merge([$this->bdBinary], $args);

        $result = $this->executor->execute($command);

        if ($result->success()) {
            return $result;
        }

        if ($result->timedOut()) {
            throw new BdTimeoutException(
                "Command timed out after {$result->duration()}s"
            );
        }

        // Retry on database lock
        if ($result->exitCode() === 5 && $attempt < $this->maxRetries) {
            usleep(100000 * ($attempt + 1));
            return $this->execute($args, $attempt + 1);
        }

        throw new BdException(
            "bd command failed (exit {$result->exitCode()}): {$result->stderr()}"
        );
    }
}

// ============================================================================
// Enhanced Task
// ============================================================================

class Task
{
    private ?array $dependencyTree = null;

    public function __construct(
        private readonly BdClient $client,
        private array $data,
    ) {}

    // ============================================================================
    // Properties
    // ============================================================================

    public function __get(string $name)
    {
        return $this->data[$name] ?? null;
    }

    // ============================================================================
    // State Checks
    // ============================================================================

    public function isOpen(): bool
    {
        return $this->data['status'] === 'open';
    }

    public function isClosed(): bool
    {
        return $this->data['status'] === 'closed';
    }

    public function isInProgress(): bool
    {
        return $this->data['status'] === 'in_progress';
    }

    public function isBlocked(): bool
    {
        // TODO: Check if has blockers
        return false;
    }

    public function isReady(): bool
    {
        return !$this->isBlocked() && ($this->isOpen() || $this->isInProgress());
    }

    public function isAssigned(): bool
    {
        return !empty($this->data['assignee']);
    }

    public function isAssignedTo(string|Agent $agent): bool
    {
        $agentId = $agent instanceof Agent ? $agent->id : $agent;
        return $this->data['assignee'] === $agentId;
    }

    public function isMine(): bool
    {
        $currentAgent = $this->client->currentAgent();
        return $currentAgent && $this->isAssignedTo($currentAgent);
    }

    // ============================================================================
    // Fluent State Transitions
    // ============================================================================

    public function claim(): self
    {
        $agent = $this->client->currentAgent();
        if (!$agent) {
            throw new \RuntimeException('No agent identity set. Call as() first.');
        }

        $this->client->_execute([
            'update',
            $this->data['id'],
            '--assignee=' . $agent->id,
            '--status=in_progress',
            '--json',
        ]);

        return $this->refresh();
    }

    public function start(): self
    {
        $this->client->_execute([
            'update',
            $this->data['id'],
            '--status=in_progress',
            '--json',
        ]);

        return $this->refresh();
    }

    public function complete(string $reason): self
    {
        $this->client->_execute([
            'close',
            $this->data['id'],
            "--reason={$reason}",
            '--json',
        ]);

        return $this->refresh();
    }

    public function close(string $reason): self
    {
        return $this->complete($reason);
    }

    public function abandon(string $reason = ''): self
    {
        $args = [
            'update',
            $this->data['id'],
            '--assignee=',
            '--status=open',
            '--json',
        ];

        $this->client->_execute($args);

        if ($reason) {
            $this->comment("Abandoned: {$reason}");
        }

        return $this->refresh();
    }

    public function block(string $reason = ''): self
    {
        if ($reason) {
            $this->comment("Blocked: {$reason}");
        }

        // TODO: Add blocked status when bd supports it
        return $this;
    }

    public function unblock(): self
    {
        $status = $this->isAssigned() ? 'in_progress' : 'open';

        $this->client->_execute([
            'update',
            $this->data['id'],
            "--status={$status}",
            '--json',
        ]);

        return $this->refresh();
    }

    public function handoff(string|Agent $agent, string $message = ''): self
    {
        $agentId = $agent instanceof Agent ? $agent->id : $agent;

        if ($message) {
            $this->mention($agentId, "Handing off to you. {$message}");
        }

        $this->client->_execute([
            'update',
            $this->data['id'],
            "--assignee={$agentId}",
            '--json',
        ]);

        return $this->refresh();
    }

    public function assign(string|Agent $agent): self
    {
        $agentId = $agent instanceof Agent ? $agent->id : $agent;

        $this->client->_execute([
            'update',
            $this->data['id'],
            "--assignee={$agentId}",
            '--json',
        ]);

        return $this->refresh();
    }

    public function setPriority(int $priority): self
    {
        $this->client->_execute([
            'update',
            $this->data['id'],
            "--priority={$priority}",
            '--json',
        ]);

        return $this->refresh();
    }

    public function addLabel(string $label): self
    {
        $this->client->_execute([
            'label',
            'add',
            $this->data['id'],
            $label,
            '--json',
        ]);

        return $this->refresh();
    }

    public function removeLabel(string $label): self
    {
        $this->client->_execute([
            'label',
            'remove',
            $this->data['id'],
            $label,
            '--json',
        ]);

        return $this->refresh();
    }

    // ============================================================================
    // Comments & Communication
    // ============================================================================

    public function comment(string $message): self
    {
        $this->client->comment($this->data['id'], $message);
        return $this;
    }

    public function mention(string|Agent $agent, string $message): self
    {
        $agentId = $agent instanceof Agent ? $agent->id : $agent;
        $this->client->mention($this->data['id'], $agentId, $message);
        return $this;
    }

    public function comments(): CommentCollection
    {
        return $this->client->comments($this->data['id']);
    }

    public function latestComment(): ?Comment
    {
        return $this->comments()->last();
    }

    public function hasUnreadComments(): bool
    {
        // TODO: Implement read tracking
        return false;
    }

    // ============================================================================
    // Subtasks & Dependencies
    // ============================================================================

    public function createSubtask(string $title): TaskBuilder
    {
        return $this->client->task($title)->childOf($this);
    }

    public function subtasks(): TaskCollection
    {
        // TODO: Query children
        return new TaskCollection($this->client, []);
    }

    public function parent(): ?Task
    {
        // TODO: Get parent from dependency tree
        return null;
    }

    public function dependsOn(string|Task $other): self
    {
        $otherId = $other instanceof Task ? $other->id : $other;
        $this->client->addDependency($this->data['id'], $otherId, 'blocks');
        return $this;
    }

    public function blocks(string|Task $other): self
    {
        $otherId = $other instanceof Task ? $other->id : $other;
        $this->client->addDependency($otherId, $this->data['id'], 'blocks');
        return $this;
    }

    public function discoveredFrom(string|Task $other): self
    {
        $otherId = $other instanceof Task ? $other->id : $other;
        $this->client->addDependency($this->data['id'], $otherId, 'discovered-from');
        return $this;
    }

    public function blockers(): TaskCollection
    {
        // TODO: Get from dependency tree
        return new TaskCollection($this->client, []);
    }

    public function blockedTasks(): TaskCollection
    {
        // TODO: Get from dependency tree
        return new TaskCollection($this->client, []);
    }

    public function related(): TaskCollection
    {
        // TODO: Get from dependency tree
        return new TaskCollection($this->client, []);
    }

    public function dependencyTree(): array
    {
        if ($this->dependencyTree === null) {
            $this->dependencyTree = $this->client->dependencyTree($this->data['id']);
        }

        return $this->dependencyTree;
    }

    // ============================================================================
    // Context & History
    // ============================================================================

    public function timeSinceUpdate(): DateInterval
    {
        $updated = new DateTime($this->data['updated_at'] ?? $this->data['created_at']);
        $now = new DateTime();

        return $now->diff($updated);
    }

    public function isStale(int $days = 7): bool
    {
        $interval = $this->timeSinceUpdate();
        return $interval->days >= $days;
    }

    public function log(string $message): self
    {
        $timestamp = date('Y-m-d H:i:s');
        return $this->comment("[{$timestamp}] {$message}");
    }

    // ============================================================================
    // Refresh & Reload
    // ============================================================================

    public function refresh(): self
    {
        $refreshed = $this->client->find($this->data['id']);
        $this->data = $refreshed->data;
        return $this;
    }

    public function withDependencyTree(array $tree): self
    {
        $this->dependencyTree = $tree;
        return $this;
    }

    // ============================================================================
    // Array Access
    // ============================================================================

    public function toArray(): array
    {
        return $this->data;
    }
}

// ============================================================================
// TaskBuilder
// ============================================================================

class TaskBuilder
{
    private string $type = 'task';
    private int $priority = 2;
    private ?string $description = null;
    private ?string $assignee = null;
    private array $labels = [];
    private array $dependencies = [];
    private array $comments = [];

    public function __construct(
        private readonly BdClient $client,
        private readonly string $title,
    ) {}

    public function type(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function priority(int $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function assignTo(string|Agent $agent): self
    {
        $this->assignee = $agent instanceof Agent ? $agent->id : $agent;
        return $this;
    }

    public function assignToMe(): self
    {
        $agent = $this->client->currentAgent();
        if (!$agent) {
            throw new \RuntimeException('No agent identity set. Call as() first.');
        }

        $this->assignee = $agent->id;
        return $this;
    }

    public function label(string $label): self
    {
        $this->labels[] = $label;
        return $this;
    }

    public function labels(array $labels): self
    {
        $this->labels = array_merge($this->labels, $labels);
        return $this;
    }

    public function dependsOn(string|Task ...$tasks): self
    {
        foreach ($tasks as $task) {
            $this->dependencies[] = [
                'type' => 'blocks',
                'target' => $task instanceof Task ? $task->id : $task,
            ];
        }
        return $this;
    }

    public function blocks(string|Task ...$tasks): self
    {
        // Will be handled after creation
        return $this;
    }

    public function discoveredFrom(string|Task $task): self
    {
        $this->dependencies[] = [
            'type' => 'discovered-from',
            'target' => $task instanceof Task ? $task->id : $task,
        ];
        return $this;
    }

    public function childOf(string|Task $task): self
    {
        $this->dependencies[] = [
            'type' => 'parent',
            'target' => $task instanceof Task ? $task->id : $task,
        ];
        return $this;
    }

    public function withComment(string $message): self
    {
        $this->comments[] = $message;
        return $this;
    }

    public function withSpecification(string $spec): self
    {
        $this->comments[] = "# Specification\n\n{$spec}";
        return $this;
    }

    public function create(): Task
    {
        $args = [
            'create',
            '--title=' . $this->title,
            '--type=' . $this->type,
            '--priority=' . $this->priority,
            '--json',
        ];

        if ($this->description) {
            $args[] = '--description=' . $this->description;
        }

        if ($this->assignee) {
            $args[] = '--assignee=' . $this->assignee;
        }

        foreach ($this->labels as $label) {
            $args[] = '--label=' . $label;
        }

        $result = $this->client->_execute($args);
        $data = json_decode($result->stdout(), true);
        $task = new Task($this->client, $data);

        // Add dependencies
        foreach ($this->dependencies as $dep) {
            $this->client->addDependency($task->id, $dep['target'], $dep['type']);
        }

        // Add comments
        foreach ($this->comments as $comment) {
            $task->comment($comment);
        }

        return $task;
    }

    public function createAndClaim(): Task
    {
        $this->assignToMe();
        $task = $this->create();
        return $task->start();
    }

    public function createAndStart(string $initialComment = ''): Task
    {
        $task = $this->createAndClaim();

        if ($initialComment) {
            $task->comment($initialComment);
        }

        return $task;
    }
}

// ============================================================================
// EpicBuilder
// ============================================================================

class EpicBuilder
{
    private ?string $description = null;
    private ?string $assignee = null;
    private array $subtasks = [];

    public function __construct(
        private readonly BdClient $client,
        private readonly string $title,
    ) {}

    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function assignTo(string|Agent $agent): self
    {
        $this->assignee = $agent instanceof Agent ? $agent->id : $agent;
        return $this;
    }

    public function task(string $title): self
    {
        $this->subtasks[] = ['title' => $title];
        return $this;
    }

    public function parallelTasks(array $titles): self
    {
        foreach ($titles as $title) {
            $this->task($title);
        }
        return $this;
    }

    public function sequentialTasks(array $titles): self
    {
        $previousId = null;

        foreach ($titles as $title) {
            $subtask = ['title' => $title];

            if ($previousId) {
                $subtask['depends_on'] = $previousId;
            }

            $this->subtasks[] = $subtask;
            $previousId = count($this->subtasks) - 1;
        }

        return $this;
    }

    public function create(): Epic
    {
        // Create epic task
        $epicBuilder = $this->client->task($this->title)
            ->type('epic')
            ->priority(1);

        if ($this->description) {
            $epicBuilder->description($this->description);
        }

        if ($this->assignee) {
            $epicBuilder->assignTo($this->assignee);
        }

        $epicTask = $epicBuilder->create();

        // Create subtasks
        $createdSubtasks = [];

        foreach ($this->subtasks as $subtaskData) {
            $builder = $this->client->task($subtaskData['title'])
                ->childOf($epicTask);

            if (isset($subtaskData['depends_on'])) {
                $dependsOnTask = $createdSubtasks[$subtaskData['depends_on']];
                $builder->dependsOn($dependsOnTask);
            }

            $createdSubtasks[] = $builder->create();
        }

        return new Epic($epicTask, new TaskCollection($this->client, $createdSubtasks));
    }
}

// ============================================================================
// Supporting Classes
// ============================================================================

class Epic
{
    public function __construct(
        public readonly Task $task,
        public readonly TaskCollection $subtasks,
    ) {}
}

class Agent
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $name = null,
    ) {}
}

class TaskCollection
{
    private array $tasks;

    public function __construct(
        private readonly BdClient $client,
        array $tasks,
    ) {
        $this->tasks = array_map(
            fn($task) => $task instanceof Task ? $task : new Task($this->client, $task),
            $tasks
        );
    }

    public function all(): array
    {
        return $this->tasks;
    }

    public function first(): ?Task
    {
        return $this->tasks[0] ?? null;
    }

    public function last(): ?Task
    {
        return $this->tasks[count($this->tasks) - 1] ?? null;
    }

    public function count(): int
    {
        return count($this->tasks);
    }

    public function isEmpty(): bool
    {
        return empty($this->tasks);
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    // Filtering
    public function filter(callable $callback): self
    {
        return new self($this->client, array_filter($this->tasks, $callback));
    }

    public function open(): self
    {
        return $this->filter(fn($t) => $t->isOpen());
    }

    public function closed(): self
    {
        return $this->filter(fn($t) => $t->isClosed());
    }

    public function inProgress(): self
    {
        return $this->filter(fn($t) => $t->isInProgress());
    }

    public function assigned(): self
    {
        return $this->filter(fn($t) => $t->isAssigned());
    }

    public function unassigned(): self
    {
        return $this->filter(fn($t) => !$t->isAssigned());
    }

    public function highPriority(): self
    {
        return $this->filter(fn($t) => $t->priority <= 1);
    }

    public function withLabel(string $label): self
    {
        return $this->filter(fn($t) => in_array($label, $t->labels ?? [], true));
    }

    // Mapping
    public function map(callable $callback): array
    {
        return array_map($callback, $this->tasks);
    }

    public function ids(): array
    {
        return $this->map(fn($t) => $t->id);
    }

    // Sorting
    public function sortBy(string $field, bool $descending = false): self
    {
        $sorted = $this->tasks;

        usort($sorted, function($a, $b) use ($field, $descending) {
            $result = $a->$field <=> $b->$field;
            return $descending ? -$result : $result;
        });

        return new self($this->client, $sorted);
    }

    public function take(int $count): self
    {
        return new self($this->client, array_slice($this->tasks, 0, $count));
    }
}

class CommentCollection
{
    private array $comments;

    public function __construct(array $comments)
    {
        $this->comments = array_map(
            fn($c) => $c instanceof Comment ? $c : new Comment($c),
            $comments
        );
    }

    public function all(): array
    {
        return $this->comments;
    }

    public function last(): ?Comment
    {
        return $this->comments[count($this->comments) - 1] ?? null;
    }

    public function isEmpty(): bool
    {
        return empty($this->comments);
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    public function mentionsAgent(string $agentId): bool
    {
        foreach ($this->comments as $comment) {
            if (str_contains($comment->message, "@{$agentId}")) {
                return true;
            }
        }

        return false;
    }

    public function unread(): self
    {
        // TODO: Implement read tracking
        return $this;
    }
}

class Comment
{
    public ?string $taskId = null;

    public function __construct(private array $data)
    {}

    public function __get(string $name)
    {
        return $this->data[$name] ?? null;
    }
}

class SessionContext
{
    public function __construct(
        public readonly ?Task $currentTask,
        public readonly TaskCollection $activeTasks,
        public readonly TaskCollection $recentlyCompleted,
        public readonly TaskCollection $mentions,
        public readonly CommentCollection $recentComments,
        public readonly ?Checkpoint $lastCheckpoint,
    ) {}

    public function hasActiveWork(): bool
    {
        return $this->activeTasks->isNotEmpty();
    }

    public function summary(): string
    {
        $summary = "Session Context:\n";

        if ($this->currentTask) {
            $summary .= "  Current Task: [{$this->currentTask->id}] {$this->currentTask->title}\n";
        }

        if ($this->activeTasks->count() > 1) {
            $summary .= "  Active Tasks: {$this->activeTasks->count()}\n";
        }

        if ($this->mentions->isNotEmpty()) {
            $summary .= "  Mentions: {$this->mentions->count()}\n";
        }

        if ($this->recentlyCompleted->isNotEmpty()) {
            $summary .= "  Recently Completed: {$this->recentlyCompleted->count()}\n";
        }

        return $summary;
    }

    public function recommendedAction(): string
    {
        if ($this->mentions->isNotEmpty()) {
            return 'respond_to_mentions';
        }

        if ($this->currentTask) {
            return 'resume_current_task';
        }

        if ($this->activeTasks->isNotEmpty()) {
            return 'resume_active_work';
        }

        return 'find_new_task';
    }
}

class Checkpoint
{
    // TODO: Implement checkpoint system
}

// ============================================================================
// Exceptions
// ============================================================================

class BdException extends \RuntimeException {}
class BdTimeoutException extends BdException {}
