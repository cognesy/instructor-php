# Beads Integration for PartnerSpot

A clean, type-safe PHP integration for the Beads (bd) task management system, built with Domain-Driven Design principles.

## Features

- ✅ **Clean Architecture**: Domain/Infrastructure/Application/Presentation layers
- ✅ **Type-Safe**: PHPStan level 8 + Psalm level 1 compliant
- ✅ **Fluent API**: Builder pattern for ergonomic task creation
- ✅ **Laravel Integration**: Full service provider with dependency injection
- ✅ **Graph Analysis**: Leverage bv for dependency insights and execution planning
- ✅ **Agent Context**: Session recovery and workflow management

## Installation

1. Register the service provider in `config/app.php`:

```php
'providers' => [
    // ...
    Cognesy\Auxiliary\Beads\Presentation\BeadsServiceProvider::class,
],
```

2. Publish the configuration (optional):

```bash
php artisan vendor:publish --tag=beads-config
```

3. Configure environment variables in `.env`:

```env
BEADS_BD_BINARY=/usr/local/bin/bd
BEADS_BV_BINARY=/usr/local/bin/bv
BEADS_TIMEOUT=30
BEADS_MAX_RETRIES=3
```

## Quick Start

### Basic Task Creation

```php
use Cognesy\Auxiliary\Beads\Presentation\Facade\Beads;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Agent;

// Get the Beads facade
$beads = app(Beads::class);

// Set agent context
$agent = Agent::create('claude', 'Claude Code');
$beads->as($agent);

// Create a simple task
$result = $beads->task()
    ->title('Implement user authentication')
    ->asFeature()
    ->high()
    ->description('Add JWT-based authentication with refresh tokens')
    ->withLabels(['backend', 'security'])
    ->create();

if ($result->success) {
    echo "Task created: {$result->task->id()->value}\n";
}
```

### Creating an Epic with Subtasks

```php
$result = $beads->epic()
    ->title('Modernize Frontend Stack')
    ->critical()
    ->description('Migrate from Vue 2 to React 19')
    ->subtask(function ($task) {
        $task->title('Set up React 19 project')
             ->high()
             ->description('Initialize with Vite and TypeScript');
    })
    ->subtask(function ($task) {
        $task->title('Migrate authentication module')
             ->high()
             ->asFeature();
    })
    ->subtask(function ($task) {
        $task->title('Update all component tests')
             ->medium()
             ->description('Convert Jest tests to Vitest');
    })
    ->create();

if ($result->success) {
    echo "Epic created with " . $result->subtasks->count() . " subtasks\n";
}
```

### Query Operations

```php
// Get next available tasks
$nextTasks = $beads->nextTask(5);

// Get my current tasks
$myTasks = $beads->mine();

// Find specific task
$task = $beads->find('partnerspot-abc123');

// Get tasks by status
$openTasks = $beads->open();
$inProgressTasks = $beads->inProgress();
$closedTasks = $beads->closed();

// Get ready tasks (no blockers)
$readyTasks = $beads->available(10);
```

### Complete a Task

```php
$result = $beads->complete(
    'partnerspot-abc123',
    'Implemented authentication with tests passing'
);

if ($result->success) {
    echo "Task completed successfully\n";
}
```

### Session Recovery

```php
// Recover agent session
$session = $beads->recoverSession();

if ($session['has_active_tasks']) {
    echo "You have {count($session['in_progress_tasks'])} active tasks:\n";
    foreach ($session['in_progress_tasks'] as $task) {
        echo "  - {$task->title} ({$task->id})\n";
    }
}

// Get full context (session + next tasks)
$context = $beads->context(5);
print_r($context);
```

## Advanced Usage

### Using Application Services Directly

```php
use Cognesy\Auxiliary\Beads\Application\Service\TaskQueryService;
use Cognesy\Auxiliary\Beads\Application\Service\GraphAnalysisService;
use Cognesy\Auxiliary\Beads\Application\Service\AgentContextService;

// Task queries
$taskService = app(TaskQueryService::class);
$task = $taskService->getTaskById('partnerspot-abc123');
$readyTasks = $taskService->getReadyTasks(10);

// Graph analysis
$graphService = app(GraphAnalysisService::class);
$insights = $graphService->getInsights();
$executionPlan = $graphService->getExecutionPlan();
$priorities = $graphService->getPriorityRecommendations();
$highImpact = $graphService->getHighImpactTasks(5);

// Agent context
$contextService = app(AgentContextService::class);
$session = $contextService->recoverSession($agent);
$nextTasks = $contextService->getNextTasks($agent, 5);
$fullContext = $contextService->getFullContext($agent);
```

### Using Use Case Handlers

```php
use Cognesy\Auxiliary\Beads\Application\UseCase\CreateTask\CreateTaskCommand;
use Cognesy\Auxiliary\Beads\Application\UseCase\CreateTask\CreateTaskHandler;
use Cognesy\Auxiliary\Beads\Domain\Model\TaskType;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Priority;

$handler = app(CreateTaskHandler::class);

$command = new CreateTaskCommand(
    title: 'Fix memory leak in background jobs',
    type: TaskType::Bug,
    priority: Priority::critical(),
    description: 'Jobs are not releasing resources properly',
    labels: ['backend', 'urgent'],
);

$result = $handler->handle($command);

if ($result->success) {
    echo "Task {$result->task->id()->value} created\n";
}
```

### Direct Repository Access

```php
use Cognesy\Auxiliary\Beads\Domain\Repository\TaskRepositoryInterface;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\TaskId;

$repo = app(TaskRepositoryInterface::class);

// Find by ID
$task = $repo->findById(new TaskId('partnerspot-abc123'));

// Find by status
$openTasks = $repo->findByStatus(TaskStatus::Open);

// Find by assignee
$myTasks = $repo->findByAssignee($agent);

// Get ready tasks
$readyTasks = $repo->findReady(10);
```

## Architecture

### Domain Layer

Pure business logic with zero dependencies:

- **Entities**: Task, Comment
- **Value Objects**: TaskId, Priority, Agent
- **Enums**: TaskType, TaskStatus, DependencyType
- **Collections**: TaskCollection, CommentCollection
- **Services**: TaskLifecycleService, DependencyService

### Infrastructure Layer

External integrations and adapters:

- **CLI Clients**: BdClient, BvClient (thin wrappers)
- **Execution**: SandboxCommandExecutor with retry logic
- **Parsers**: TaskParser, CommentParser, GraphParser
- **Repositories**: BdTaskRepository, BvGraphRepository
- **Factory**: TaskFactory (handles ID assignment)

### Application Layer

Use cases and DTOs:

- **Use Cases**: CreateTask, CompleteTask, ClaimTask, CreateEpic, GetNextTask, RecoverSession
- **DTOs**: TaskData, CreateTaskData, UpdateTaskData, SubtaskData, CreateEpicData
- **Services**: TaskQueryService, GraphAnalysisService, AgentContextService

### Presentation Layer

User-facing APIs:

- **Builders**: TaskBuilder, EpicBuilder (fluent interfaces)
- **Facade**: Beads (main entry point)
- **ServiceProvider**: Laravel DI integration

## Configuration

```php
// config/beads.php

return [
    'binaries' => [
        'bd' => env('BEADS_BD_BINARY', null),  // Auto-detected if null
        'bv' => env('BEADS_BV_BINARY', null),  // Auto-detected if null
    ],

    'execution' => [
        'timeout_seconds' => (int) env('BEADS_TIMEOUT', 30),
        'stdout_limit_mb' => (int) env('BEADS_STDOUT_LIMIT_MB', 10),
        'stderr_limit_mb' => (int) env('BEADS_STDERR_LIMIT_MB', 1),
        'network_enabled' => env('BEADS_NETWORK_ENABLED', true),
    ],

    'retry' => [
        'max_attempts' => (int) env('BEADS_MAX_RETRIES', 0),  // 0 = no retries
    ],

    'project' => [
        'base_dir' => env('BEADS_BASE_DIR', base_path()),
        'beads_dir' => env('BEADS_DIR', base_path('.beads')),
    ],
];
```

## Testing

### Running Tests

```bash
# PHPStan level 8
./vendor/bin/phpstan analyse app/Integrations/Beads/ --level 8

# Psalm level 1
./vendor/bin/psalm app/Integrations/Beads/

# Laravel Pint (formatting)
./vendor/bin/pint app/Integrations/Beads/

# Unit tests (when available)
./vendor/bin/pest tests/Unit/Integrations/Beads/

# Integration tests (when available)
./vendor/bin/pest tests/Integration/Integrations/Beads/

# Feature tests (when available)
./vendor/bin/pest tests/Feature/Integrations/Beads/
```

## Code Quality

- ✅ **PHPStan Level 8**: 0 errors on 62 files
- ✅ **Psalm Level 1**: 0 errors, 87.3% type inference
- ✅ **Laravel Pint**: PSR-12 compliant
- ✅ **Type Safety**: All public APIs fully typed
- ✅ **Immutability**: Readonly classes throughout

## Design Patterns

- **Command/Handler/Result**: Application use cases
- **Factory Pattern**: Task creation with external ID assignment
- **Repository Pattern**: Data persistence abstraction
- **Builder Pattern**: Fluent object construction
- **Facade Pattern**: Unified API entry point
- **Value Object Pattern**: Domain primitives
- **Collection Pattern**: Rich domain collections

## Best Practices

1. **Always use the Facade** for simple operations
2. **Use Builders** for complex task/epic creation
3. **Use Use Case Handlers** for business logic
4. **Use Repositories** for direct data access
5. **Set Agent Context** before operations
6. **Check Result Objects** for errors
7. **Use Type-Safe Enums** for status/type/priority

## Troubleshooting

### Binary Not Found

If bd/bv binaries aren't auto-detected:

```env
BEADS_BD_BINARY=/path/to/bd
BEADS_BV_BINARY=/path/to/bv
```

### Command Timeout

Increase timeout for long-running operations:

```env
BEADS_TIMEOUT=60
```

### Retry Configuration

Enable retries for flaky operations:

```env
BEADS_MAX_RETRIES=3
```

## License

Internal use only - PartnerSpot project.

## Credits

Built with Domain-Driven Design principles using:
- Laravel 12
- PHP 8.2+
- instructor-php Sandbox
- Beads (bd) CLI
- Beads Viewer (bv)
