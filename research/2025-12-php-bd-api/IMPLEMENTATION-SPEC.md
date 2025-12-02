# Implementation Specification: Beads Integration for PartnerSpot

**Version**: 1.0
**Date**: 2025-12-01
**Target**: `app/Integrations/Beads/`
**Architecture**: DDD, Clean Code, Type-Safe, Layered

## Executive Summary

Implement a production-ready PHP API for bd (beads) issue tracker and bv (beads viewer) graph analysis, designed for multi-agent collaboration with superior developer experience. The implementation follows Domain-Driven Design principles, uses strict typing, and leverages instructor-php Sandbox for command execution.

## Architecture Overview

```
app/Integrations/Beads/
├── Domain/                      # Domain Layer (Core Business Logic)
│   ├── Model/                   # Domain Models (Entities, Value Objects)
│   │   ├── Task.php            # Task entity with fluent operations
│   │   ├── Agent.php           # Agent identity value object
│   │   ├── Comment.php         # Comment entity
│   │   ├── TaskId.php          # Task identifier value object
│   │   ├── TaskStatus.php      # Status enum
│   │   ├── TaskType.php        # Type enum
│   │   ├── Priority.php        # Priority value object
│   │   └── DependencyType.php  # Dependency type enum
│   ├── ValueObject/             # Complex Value Objects
│   │   ├── SessionContext.php  # Session recovery context
│   │   ├── GraphInsights.php   # bv metrics
│   │   ├── ExecutionPlan.php   # bv execution plan
│   │   └── DependencyTree.php  # Task dependencies
│   ├── Collection/              # Domain Collections
│   │   ├── TaskCollection.php  # Rich task collection
│   │   └── CommentCollection.php # Comment collection
│   ├── Repository/              # Repository Interfaces
│   │   ├── TaskRepositoryInterface.php
│   │   └── GraphRepositoryInterface.php
│   ├── Service/                 # Domain Services
│   │   ├── TaskLifecycleService.php # State transitions
│   │   ├── DependencyService.php    # Dependency management
│   │   └── SessionRecoveryService.php # Context recovery
│   └── Exception/               # Domain Exceptions
│       ├── BeadsException.php
│       ├── TaskNotFoundException.php
│       ├── InvalidStateTransitionException.php
│       └── ConcurrencyException.php
├── Application/                 # Application Layer (Use Cases)
│   ├── UseCase/                 # Application Use Cases
│   │   ├── ClaimTask/
│   │   │   ├── ClaimTaskCommand.php
│   │   │   ├── ClaimTaskHandler.php
│   │   │   └── ClaimTaskResult.php
│   │   ├── CreateTask/
│   │   │   ├── CreateTaskCommand.php
│   │   │   ├── CreateTaskHandler.php
│   │   │   └── CreateTaskResult.php
│   │   ├── CompleteTask/
│   │   │   ├── CompleteTaskCommand.php
│   │   │   ├── CompleteTaskHandler.php
│   │   │   └── CompleteTaskResult.php
│   │   ├── CreateEpic/
│   │   │   ├── CreateEpicCommand.php
│   │   │   ├── CreateEpicHandler.php
│   │   │   └── CreateEpicResult.php
│   │   ├── RecoverSession/
│   │   │   ├── RecoverSessionQuery.php
│   │   │   ├── RecoverSessionHandler.php
│   │   │   └── RecoverSessionResult.php
│   │   └── GetNextTask/
│   │       ├── GetNextTaskQuery.php
│   │       ├── GetNextTaskHandler.php
│   │       └── GetNextTaskResult.php
│   ├── DTO/                     # Data Transfer Objects
│   │   ├── TaskData.php
│   │   ├── CreateTaskData.php
│   │   ├── UpdateTaskData.php
│   │   ├── CreateEpicData.php
│   │   └── SubtaskData.php
│   └── Service/                 # Application Services
│       ├── TaskQueryService.php
│       ├── GraphAnalysisService.php
│       └── AgentContextService.php
├── Infrastructure/              # Infrastructure Layer (External Integrations)
│   ├── Executor/                # Command Execution
│   │   ├── CommandExecutorInterface.php
│   │   ├── SandboxCommandExecutor.php
│   │   └── CommandResult.php
│   ├── Repository/              # Concrete Repositories
│   │   ├── BdTaskRepository.php
│   │   └── BvGraphRepository.php
│   ├── Client/                  # CLI Client Wrappers
│   │   ├── BdClient.php        # bd command wrapper
│   │   └── BvClient.php        # bv command wrapper
│   ├── Parser/                  # Response Parsers
│   │   ├── JsonParser.php
│   │   ├── TaskParser.php
│   │   └── GraphParser.php
│   └── Config/                  # Configuration
│       └── BeadsConfig.php
├── Presentation/                # Presentation Layer (API)
│   ├── Facade/                  # Laravel Facades
│   │   └── Beads.php
│   ├── Builder/                 # Fluent Builders
│   │   ├── TaskBuilder.php
│   │   └── EpicBuilder.php
│   └── Provider/                # Laravel Service Provider
│       └── BeadsServiceProvider.php
└── Tests/                       # Tests mirror structure
    ├── Unit/
    ├── Integration/
    └── Feature/
```

## Layer Responsibilities

### Domain Layer (Core)

**Purpose**: Pure business logic, no framework dependencies, no I/O

**Contains**:
- Entities with business rules (Task, Comment)
- Value Objects (TaskId, Priority, Agent)
- Domain Services (state transitions, business rules)
- Repository Interfaces (contracts)
- Domain Exceptions

**Rules**:
- No framework dependencies (no Laravel)
- No database access
- No external API calls
- Pure PHP with strict types
- All business logic here

### Application Layer (Use Cases)

**Purpose**: Orchestrate domain objects to fulfill use cases

**Contains**:
- Use Case Handlers (Command/Query handlers)
- DTOs for input/output
- Application Services (coordinate multiple domain services)

**Rules**:
- Uses Domain Layer only
- No direct Infrastructure dependencies
- Depends on Repository Interfaces (not implementations)
- Orchestrates, doesn't contain business logic

### Infrastructure Layer (Technical)

**Purpose**: Technical implementations, external integrations

**Contains**:
- Repository Implementations (concrete)
- bd/bv CLI client wrappers
- Sandbox command executor
- JSON parsers
- Configuration

**Rules**:
- Implements Domain interfaces
- Handles all I/O (files, commands, network)
- Framework-specific code allowed
- No business logic

### Presentation Layer (API)

**Purpose**: Expose functionality to consumers

**Contains**:
- Laravel Facade
- Fluent Builders (TaskBuilder, EpicBuilder)
- Service Provider
- HTTP Controllers (future)

**Rules**:
- Thin layer, delegates to Application
- Framework-specific
- User-facing API

## Type Safety & Clean Code Principles

### No Arrays for Structured Data

❌ **Wrong**:
```php
function createTask(array $data): array {
    return ['id' => 'bd-123', 'title' => $data['title']];
}
```

✅ **Correct**:
```php
function createTask(CreateTaskData $data): TaskData {
    return new TaskData(
        id: new TaskId('bd-123'),
        title: $data->title,
    );
}
```

### Use Value Objects for Domain Concepts

❌ **Wrong**:
```php
class Task {
    public string $id;
    public int $priority; // What does 0 vs 4 mean?
    public string $status; // What are valid values?
}
```

✅ **Correct**:
```php
class Task {
    public function __construct(
        public readonly TaskId $id,
        public readonly Priority $priority, // Self-documenting
        public readonly TaskStatus $status, // Enum, can't be invalid
    ) {}
}
```

### Use DTOs for Data Transfer

❌ **Wrong**:
```php
$repository->create([
    'title' => 'Task',
    'type' => 'task',
    'priority' => 2,
]);
```

✅ **Correct**:
```php
$repository->create(new CreateTaskData(
    title: 'Task',
    type: TaskType::Task,
    priority: Priority::medium(),
));
```

### Use Enums for Fixed Sets

❌ **Wrong**:
```php
const STATUS_OPEN = 'open';
const STATUS_CLOSED = 'closed';
```

✅ **Correct**:
```php
enum TaskStatus: string {
    case Open = 'open';
    case InProgress = 'in_progress';
    case Closed = 'closed';
}
```

## Domain Model Design

### Task Entity

```php
<?php

namespace App\Integrations\Beads\Domain\Model;

use App\Integrations\Beads\Domain\ValueObject\TaskId;
use App\Integrations\Beads\Domain\ValueObject\Priority;
use App\Integrations\Beads\Domain\ValueObject\Agent;

final class Task
{
    private function __construct(
        private TaskId $id,
        private string $title,
        private TaskStatus $status,
        private TaskType $type,
        private Priority $priority,
        private ?Agent $assignee,
        private ?string $description,
        private array $labels,
        private \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt,
        private ?\DateTimeImmutable $closedAt,
    ) {}

    // Factory methods
    public static function create(
        TaskId $id,
        string $title,
        TaskType $type,
        Priority $priority,
    ): self;

    public static function reconstitute(TaskData $data): self;

    // State transitions (business rules enforced)
    public function claim(Agent $agent): self;
    public function start(): self;
    public function complete(string $reason): self;
    public function block(string $reason): self;
    public function abandon(): self;

    // Queries
    public function isOpen(): bool;
    public function isClosed(): bool;
    public function isInProgress(): bool;
    public function isAssignedTo(Agent $agent): bool;
    public function canBeClaimed(): bool;
    public function canBeStarted(): bool;
    public function canBeCompleted(): bool;

    // Getters (immutable)
    public function id(): TaskId;
    public function title(): string;
    public function status(): TaskStatus;
    public function priority(): Priority;
    public function assignee(): ?Agent;
}
```

### Value Objects

```php
<?php

namespace App\Integrations\Beads\Domain\ValueObject;

// Task Identifier
final readonly class TaskId
{
    public function __construct(
        public string $value,
    ) {
        if (!preg_match('/^bd-[a-z0-9]+$/', $value)) {
            throw new \InvalidArgumentException("Invalid task ID: {$value}");
        }
    }

    public function equals(TaskId $other): bool;
    public function __toString(): string;
}

// Priority (0-4 with semantic meaning)
final readonly class Priority
{
    private function __construct(
        public int $value,
        public string $label,
    ) {
        if ($value < 0 || $value > 4) {
            throw new \InvalidArgumentException("Priority must be 0-4");
        }
    }

    public static function critical(): self { return new self(0, 'Critical'); }
    public static function high(): self { return new self(1, 'High'); }
    public static function medium(): self { return new self(2, 'Medium'); }
    public static function low(): self { return new self(3, 'Low'); }
    public static function backlog(): self { return new self(4, 'Backlog'); }

    public static function fromInt(int $value): self;
    public function isCritical(): bool;
    public function isHigherThan(Priority $other): bool;
}

// Agent Identity
final readonly class Agent
{
    public function __construct(
        public string $id,
        public ?string $name = null,
    ) {
        if (empty($id)) {
            throw new \InvalidArgumentException("Agent ID cannot be empty");
        }
    }

    public function equals(Agent $other): bool;
    public function __toString(): string;
}
```

### Enums

```php
<?php

namespace App\Integrations\Beads\Domain\Model;

enum TaskStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Closed = 'closed';

    public function isOpen(): bool { return $this === self::Open; }
    public function isClosed(): bool { return $this === self::Closed; }
    public function isInProgress(): bool { return $this === self::InProgress; }
}

enum TaskType: string
{
    case Task = 'task';
    case Bug = 'bug';
    case Feature = 'feature';
    case Epic = 'epic';

    public function isEpic(): bool { return $this === self::Epic; }
}

enum DependencyType: string
{
    case Blocks = 'blocks';
    case Related = 'related';
    case Parent = 'parent';
    case DiscoveredFrom = 'discovered-from';
}
```

## Use Case Pattern

### Command/Query Separation

**Commands** (write operations):
- ClaimTaskCommand
- CreateTaskCommand
- CompleteTaskCommand
- UpdateTaskCommand

**Queries** (read operations):
- GetNextTaskQuery
- FindTaskQuery
- RecoverSessionQuery
- GetGraphInsightsQuery

### Example Use Case

```php
<?php

namespace App\Integrations\Beads\Application\UseCase\ClaimTask;

// Command (input)
final readonly class ClaimTaskCommand
{
    public function __construct(
        public TaskId $taskId,
        public Agent $agent,
    ) {}
}

// Result (output)
final readonly class ClaimTaskResult
{
    public function __construct(
        public TaskData $task,
        public bool $success,
        public ?string $errorMessage = null,
    ) {}

    public static function success(TaskData $task): self;
    public static function failure(string $message): self;
}

// Handler (use case logic)
final class ClaimTaskHandler
{
    public function __construct(
        private TaskRepositoryInterface $repository,
        private TaskLifecycleService $lifecycleService,
    ) {}

    public function handle(ClaimTaskCommand $command): ClaimTaskResult
    {
        try {
            // Load task
            $task = $this->repository->findById($command->taskId);

            if ($task === null) {
                return ClaimTaskResult::failure("Task not found");
            }

            // Business rule: Can task be claimed?
            if (!$task->canBeClaimed()) {
                return ClaimTaskResult::failure("Task cannot be claimed");
            }

            // Apply domain operation
            $claimedTask = $task->claim($command->agent);

            // Persist
            $this->repository->save($claimedTask);

            // Return result
            return ClaimTaskResult::success(
                TaskData::fromDomain($claimedTask)
            );

        } catch (\Exception $e) {
            return ClaimTaskResult::failure($e->getMessage());
        }
    }
}
```

## Repository Pattern

### Interface (Domain Layer)

```php
<?php

namespace App\Integrations\Beads\Domain\Repository;

interface TaskRepositoryInterface
{
    public function findById(TaskId $id): ?Task;
    public function findMany(array $ids): TaskCollection;
    public function findByStatus(TaskStatus $status): TaskCollection;
    public function findByAssignee(Agent $agent): TaskCollection;
    public function findReady(int $limit): TaskCollection;
    public function save(Task $task): void;
    public function delete(TaskId $id): void;
}
```

### Implementation (Infrastructure Layer)

```php
<?php

namespace App\Integrations\Beads\Infrastructure\Repository;

final class BdTaskRepository implements TaskRepositoryInterface
{
    public function __construct(
        private BdClient $client,
        private TaskParser $parser,
    ) {}

    public function findById(TaskId $id): ?Task
    {
        $result = $this->client->show($id->value);

        if (!$result->isSuccess()) {
            return null;
        }

        $data = $this->parser->parse($result->stdout());

        return Task::reconstitute($data);
    }

    public function save(Task $task): void
    {
        $data = TaskData::fromDomain($task);

        $this->client->update($data);
    }
}
```

## Fluent Builder Pattern

```php
<?php

namespace App\Integrations\Beads\Presentation\Builder;

final class TaskBuilder
{
    private TaskType $type = TaskType::Task;
    private Priority $priority = Priority::medium();
    private ?string $description = null;
    private ?Agent $assignee = null;
    private array $labels = [];

    public function __construct(
        private CreateTaskHandler $handler,
        private string $title,
        private ?Agent $currentAgent = null,
    ) {}

    public function type(TaskType $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function priority(Priority $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function assignTo(Agent $agent): self
    {
        $this->assignee = $agent;
        return $this;
    }

    public function assignToMe(): self
    {
        if ($this->currentAgent === null) {
            throw new \RuntimeException('No current agent set');
        }

        $this->assignee = $this->currentAgent;
        return $this;
    }

    public function create(): Task
    {
        $command = new CreateTaskCommand(
            title: $this->title,
            type: $this->type,
            priority: $this->priority,
            description: $this->description,
            assignee: $this->assignee,
            labels: $this->labels,
        );

        $result = $this->handler->handle($command);

        if (!$result->success) {
            throw new BeadsException($result->errorMessage);
        }

        return Task::reconstitute($result->task);
    }

    public function createAndClaim(): Task
    {
        $this->assignToMe();
        $task = $this->create();

        $claimCommand = new ClaimTaskCommand(
            taskId: $task->id(),
            agent: $this->currentAgent,
        );

        // Delegate to claim handler
        // ...

        return $task;
    }
}
```

## Error Handling

### Exception Hierarchy

```php
<?php

namespace App\Integrations\Beads\Domain\Exception;

// Base exception
class BeadsException extends \RuntimeException {}

// Domain exceptions
class TaskNotFoundException extends BeadsException {}
class InvalidStateTransitionException extends BeadsException {}
class ConcurrencyException extends BeadsException {}
class DependencyCycleException extends BeadsException {}

// Infrastructure exceptions
class CommandExecutionException extends BeadsException {}
class CommandTimeoutException extends BeadsException {}
class ParseException extends BeadsException {}
```

### Result Pattern (No Exceptions for Flow Control)

```php
<?php

// Use Result objects for expected failures
final readonly class Result
{
    private function __construct(
        public bool $success,
        public mixed $value,
        public ?string $error,
    ) {}

    public static function success(mixed $value): self;
    public static function failure(string $error): self;

    public function isSuccess(): bool;
    public function isFailure(): bool;
    public function unwrap(): mixed; // Throws if failure
    public function unwrapOr(mixed $default): mixed;
}
```

## Testing Strategy

### Unit Tests (Domain Layer)

Test domain logic in isolation:
- Task state transitions
- Value object validation
- Business rules
- Collection operations

```php
<?php

namespace Tests\Unit\Beads\Domain\Model;

class TaskTest extends TestCase
{
    public function test_claim_assigns_agent_and_marks_in_progress(): void
    {
        $task = Task::create(
            id: new TaskId('bd-test'),
            title: 'Test Task',
            type: TaskType::Task,
            priority: Priority::medium(),
        );

        $agent = new Agent('agent-1');
        $claimedTask = $task->claim($agent);

        $this->assertTrue($claimedTask->isInProgress());
        $this->assertEquals($agent, $claimedTask->assignee());
    }

    public function test_cannot_claim_closed_task(): void
    {
        $task = $this->createClosedTask();

        $this->expectException(InvalidStateTransitionException::class);

        $task->claim(new Agent('agent-1'));
    }
}
```

### Integration Tests (Infrastructure Layer)

Test bd/bv integration with real commands:

```php
<?php

namespace Tests\Integration\Beads\Infrastructure;

class BdTaskRepositoryTest extends TestCase
{
    private BdTaskRepository $repository;
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/bd-test-' . uniqid();
        mkdir($this->testDir . '/.beads', 0755, true);

        // Initialize test bd
        exec("cd {$this->testDir} && bd init");

        $this->repository = $this->createRepository();
    }

    protected function tearDown(): void
    {
        exec("rm -rf {$this->testDir}");
    }

    public function test_can_create_and_retrieve_task(): void
    {
        $task = Task::create(
            id: new TaskId('bd-test-123'),
            title: 'Test Task',
            type: TaskType::Task,
            priority: Priority::medium(),
        );

        $this->repository->save($task);

        $retrieved = $this->repository->findById($task->id());

        $this->assertNotNull($retrieved);
        $this->assertEquals($task->id(), $retrieved->id());
        $this->assertEquals($task->title(), $retrieved->title());
    }
}
```

### Feature Tests (End-to-End)

Test through Facade:

```php
<?php

namespace Tests\Feature\Beads;

class BeadsFacadeTest extends TestCase
{
    public function test_can_claim_and_complete_task(): void
    {
        Beads::as(new Agent('test-agent'));

        $task = Beads::task('Test Feature')
            ->priority(Priority::high())
            ->create();

        $claimed = $task->claim();
        $this->assertTrue($claimed->isInProgress());

        $completed = $claimed->complete('Test complete');
        $this->assertTrue($completed->isClosed());
    }
}
```

## Configuration

```php
<?php

// config/beads.php
return [
    'bd_binary' => env('BD_BINARY', '/usr/local/bin/bd'),
    'bv_binary' => env('BV_BINARY', '/usr/local/bin/bv'),
    'working_dir' => env('BD_WORKING_DIR', base_path()),

    'executor' => [
        'driver' => env('BD_DRIVER', 'host'), // host|docker|firejail
        'timeout' => (int) env('BD_TIMEOUT', 30),
        'idle_timeout' => (int) env('BD_IDLE_TIMEOUT', 10),
        'stdout_limit' => 1024 * 1024, // 1MB
        'stderr_limit' => 1024 * 1024,
    ],

    'retry' => [
        'max_attempts' => (int) env('BD_MAX_RETRIES', 3),
        'delay_ms' => 100,
    ],

    'cache' => [
        'enabled' => env('BD_CACHE_ENABLED', true),
        'ttl' => (int) env('BD_CACHE_TTL', 300), // 5 minutes
        'store' => env('BD_CACHE_STORE', 'redis'),
    ],
];
```

## Laravel Service Provider

```php
<?php

namespace App\Integrations\Beads\Presentation\Provider;

use Illuminate\Support\ServiceProvider;

class BeadsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register executor
        $this->app->singleton(CommandExecutorInterface::class, function ($app) {
            return new SandboxCommandExecutor(
                policy: $this->createExecutionPolicy(),
                bdBinary: config('beads.bd_binary'),
                bvBinary: config('beads.bv_binary'),
            );
        });

        // Register repositories
        $this->app->singleton(TaskRepositoryInterface::class, BdTaskRepository::class);
        $this->app->singleton(GraphRepositoryInterface::class, BvGraphRepository::class);

        // Register use case handlers
        $this->registerHandlers();

        // Register facade
        $this->app->singleton('beads', BeadsFacade::class);
    }

    private function registerHandlers(): void
    {
        $this->app->singleton(ClaimTaskHandler::class);
        $this->app->singleton(CreateTaskHandler::class);
        $this->app->singleton(CompleteTaskHandler::class);
        // ... register all handlers
    }
}
```

## Success Criteria

1. ✅ **Type Safety**: Zero use of arrays for structured data
2. ✅ **Domain Purity**: Domain layer has zero framework dependencies
3. ✅ **Clean Architecture**: Clear layer separation, dependencies flow inward
4. ✅ **Testability**: 90%+ test coverage
5. ✅ **Performance**: <100ms for read operations, <200ms for writes
6. ✅ **DX**: Fluent API matches DX-SHOWCASE.md examples
7. ✅ **Static Analysis**: PHPStan level 8, Psalm level 1
8. ✅ **Code Style**: Laravel Pint passes, PSR-12 compliant

## Implementation Order

1. **Domain Layer** (foundation, pure business logic)
2. **Infrastructure Layer** (technical implementations)
3. **Application Layer** (use cases, orchestration)
4. **Presentation Layer** (facade, builders, API)
5. **Tests** (unit, integration, feature)
6. **Documentation** (inline PHPDoc, usage examples)

## References

- Research Study: `research/studies/2025-12-php-bd-api/README.md`
- DX Showcase: `research/studies/2025-12-php-bd-api/DX-SHOWCASE.md`
- Agent Collaboration: `research/studies/2025-12-php-bd-api/AGENT-COLLABORATION.md`
- Enhanced API: `research/studies/2025-12-php-bd-api/enhanced-api.php`
- Sandbox Analysis: `research/studies/2025-12-php-bd-api/ADDENDUM.md`
- Laravel Integration: `research/studies/2025-12-php-bd-api/laravel-integration.md`
