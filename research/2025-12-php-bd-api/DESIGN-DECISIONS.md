# Design Decisions: Beads Integration

**Date**: 2025-12-01
**Status**: Final
**Principle**: Pragmatic, YAGNI, 80/20 rule

## Purpose

This document records design decisions made after reviewing feedback on IMPLEMENTATION-SPEC.md. We prioritize **simple, solid solutions** that work for 80% of cases, avoiding over-engineering for edge cases.

---

## Decision Log

### ✅ Decision 1: Task ID Validation (High Impact)

**Feedback**: Spec hard-codes `bd-[a-z0-9]+` but project uses `partnerspot-[a-z0-9]+` hash IDs.

**Decision**: **Accept dynamic prefix, validate structure**

**Rationale**:
- Real IDs: `partnerspot-xxxx`, `bd-xxxx`, project-specific prefixes
- Pattern: `{prefix}-{hash}` where hash is 4+ alphanumeric chars
- 80% case: Project-specific prefix, consistent format

**Implementation**:
```php
final readonly class TaskId
{
    public function __construct(
        public string $value,
    ) {
        // Pragmatic: Accept any valid bd hash ID format
        // Pattern: {project}-{hash} where hash is 4+ chars
        if (!preg_match('/^[a-z0-9]+-[a-z0-9]{4,}$/', $value)) {
            throw new \InvalidArgumentException(
                "Invalid task ID format: {$value}. Expected: project-hash"
            );
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    // Helper: Extract project prefix
    public function prefix(): string
    {
        return explode('-', $this->value, 2)[0];
    }

    // Helper: Extract hash
    public function hash(): string
    {
        return explode('-', $this->value, 2)[1];
    }
}
```

**Edge cases (defer to 20%)**:
- Multiple hyphens in project name: Not supported in bd, skip
- UUID-style IDs: bd doesn't generate these, skip
- Numeric-only hashes: bd uses alphanumeric, no need to support

---

### ✅ Decision 2: Task Status Model (High Impact)

**Feedback**: Domain omits `blocked` status but has `block()` method.

**Decision**: **Add `Blocked` status, keep simple state machine**

**Rationale**:
- bd supports: `open`, `in_progress`, `blocked`, `closed`
- Domain must match bd reality
- 80% case: Simple transitions, minimal business rules

**Implementation**:
```php
enum TaskStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';  // Added
    case Closed = 'closed';

    public function isOpen(): bool { return $this === self::Open; }
    public function isClosed(): bool { return $this === self::Closed; }
    public function isInProgress(): bool { return $this === self::InProgress; }
    public function isBlocked(): bool { return $this === self::Blocked; }  // Added

    // Can transition to
    public function canTransitionTo(TaskStatus $target): bool
    {
        return match($this) {
            self::Open => in_array($target, [self::InProgress, self::Blocked, self::Closed]),
            self::InProgress => in_array($target, [self::Blocked, self::Closed, self::Open]),
            self::Blocked => in_array($target, [self::Open, self::InProgress, self::Closed]),
            self::Closed => $target === self::Open, // Can reopen
        };
    }
}

// Task entity
class Task
{
    public function block(string $reason): self
    {
        if (!$this->status->canTransitionTo(TaskStatus::Blocked)) {
            throw new InvalidStateTransitionException(
                "Cannot block task from {$this->status->value} state"
            );
        }

        return new self(
            /* ... */
            status: TaskStatus::Blocked,
            /* ... */
        );
    }

    public function unblock(): self
    {
        if ($this->status !== TaskStatus::Blocked) {
            throw new InvalidStateTransitionException("Task is not blocked");
        }

        // Return to in_progress if assigned, otherwise open
        $newStatus = $this->assignee !== null
            ? TaskStatus::InProgress
            : TaskStatus::Open;

        return new self(/* ... status: $newStatus ... */);
    }
}
```

**Edge cases (defer to 20%)**:
- Complex state machines with sub-states: Not needed, bd is simple
- Audit trail of state changes: Use comments, not complex history
- Workflow rules (can't skip states): bd allows any transition, keep flexible

---

### ✅ Decision 3: CLI Command Mapping (Critical)

**Feedback**: Repository assumes custom API, doesn't map to real bd CLI.

**Decision**: **Direct bd CLI mapping, thin wrapper**

**Rationale**:
- bd CLI is the contract, not our abstraction
- 80% case: Simple CRUD operations
- Keep it thin, predictable

**Implementation**:
```php
class BdClient
{
    public function create(CreateTaskData $data): TaskId
    {
        // Direct bd command
        $args = [
            'create',
            '--title=' . $data->title,
            '--type=' . $data->type->value,
            '--priority=' . $data->priority->value,
            '--json',
        ];

        if ($data->description) {
            $args[] = '--description=' . $data->description;
        }

        if ($data->assignee) {
            $args[] = '--assignee=' . $data->assignee->id;
        }

        $result = $this->executor->execute($args);
        $json = json_decode($result->stdout(), true);

        return TaskId::fromString($json['id']);
    }

    public function update(TaskId $id, UpdateTaskData $data): void
    {
        $args = ['update', $id->value, '--json'];

        if ($data->status) {
            $args[] = '--status=' . $data->status->value;
        }

        if ($data->priority) {
            $args[] = '--priority=' . $data->priority->value;
        }

        if ($data->assignee !== null) {
            $args[] = '--assignee=' . ($data->assignee ? $data->assignee->id : '');
        }

        $this->executor->execute($args);
    }

    public function close(TaskId $id, string $reason): void
    {
        $this->executor->execute([
            'close',
            $id->value,
            '--reason=' . $reason,
            '--json',
        ]);
    }
}
```

**Edge cases (defer to 20%)**:
- Batch operations: Use loops, bd doesn't have native batch
- Complex queries: Use bd list with filters, parse results
- Optimistic locking: bd doesn't support, use retry logic if needed

---

### ✅ Decision 4: Command Execution Safety (Critical)

**Feedback**: No escaping/validation plan for user input.

**Decision**: **Use Symfony Process array args (no shell), validate in DTOs**

**Rationale**:
- Symfony Process with array args = no shell interpretation
- DTOs validate input before reaching CLI
- 80% case: Trusted input from agents/CLI

**Implementation**:
```php
// Executor uses array args (no shell)
class SandboxCommandExecutor implements CanExecuteCommand
{
    public function execute(array $command, ?string $stdin = null): ExecResult
    {
        // Symfony Process/Sandbox uses array → no shell injection
        // command = ['/usr/local/bin/bd', 'create', '--title=Foo', '--json']
        // NOT: 'bd create --title="Foo" --json'

        $result = $this->sandbox->execute($command, $stdin);

        return new ExecResult(
            stdout: $result->stdout(),
            stderr: $result->stderr(),
            exitCode: $result->exitCode(),
            duration: $result->duration(),
            timedOut: $result->timedOut(),
        );
    }
}

// DTOs validate input
readonly class CreateTaskData
{
    public function __construct(
        public string $title,
        public TaskType $type,
        public Priority $priority,
        public ?string $description = null,
        public ?Agent $assignee = null,
    ) {
        // Validate title length
        if (strlen($title) > 500) {
            throw new \InvalidArgumentException('Title too long (max 500 chars)');
        }

        // No need to escape - Symfony Process handles it
        // Just ensure reasonable input
    }
}
```

**Why this is sufficient**:
1. Symfony Process with array args → no shell
2. DTOs validate reasonable input (length, format)
3. Sandbox provides additional isolation if needed
4. 80% case: Input from agents/CLI, not untrusted web users

**Edge cases (defer to 20%)**:
- Web form input: Add web-specific validation layer (not in domain)
- Malicious input from untrusted sources: Add rate limiting, auth checks (not here)
- Binary path validation: Config validation at boot time

---

### ✅ Decision 5: Testing Strategy (High Impact)

**Feedback**: Integration tests require real binaries, will fail in CI.

**Decision**: **Mock executor for unit tests, optional integration tests**

**Rationale**:
- Unit tests: Mock executor, test domain logic
- Integration tests: Optional, only if bd binary available
- 80% case: Unit tests cover business logic

**Implementation**:
```php
// Unit tests: Mock executor
class TaskRepositoryTest extends TestCase
{
    public function test_find_by_id_returns_task(): void
    {
        $executor = Mockery::mock(CanExecuteCommand::class);
        $executor->shouldReceive('execute')
            ->with(['bd', 'show', 'partnerspot-abc', '--json'], null)
            ->andReturn(new ExecResult(
                stdout: json_encode(['id' => 'partnerspot-abc', 'title' => 'Test']),
                stderr: '',
                exitCode: 0,
                duration: 0.05,
                timedOut: false,
            ));

        $client = new BdClient($executor);
        $repo = new BdTaskRepository($client, new TaskParser());

        $task = $repo->findById(TaskId::fromString('partnerspot-abc'));

        $this->assertNotNull($task);
    }
}

// Integration tests: Optional, skip if bd not available
class BdIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        if (!$this->hasBdBinary()) {
            $this->markTestSkipped('bd binary not available');
        }

        // Create temp dir for test
        $this->testDir = sys_get_temp_dir() . '/bd-test-' . uniqid();
        mkdir($this->testDir);
        exec("cd {$this->testDir} && bd init");
    }

    private function hasBdBinary(): bool
    {
        exec('which bd', $output, $exitCode);
        return $exitCode === 0;
    }
}
```

**Why this is sufficient**:
- Unit tests (90%+ coverage) don't need real bd
- Integration tests are optional verification
- CI can skip integration tests if bd unavailable
- 80% case: Mock executor tests cover all logic

**Edge cases (defer to 20%)**:
- Fixture-based repository: Complex, not needed
- Fake bd implementation: Over-engineering
- Docker-based tests: Adds complexity, skip for now

---

### ✅ Decision 6: Configuration (Medium Impact)

**Feedback**: Config ignores project environment variables.

**Decision**: **Env-first config with sensible defaults**

**Rationale**:
- Env vars override defaults (12-factor app)
- Project sets `BD_NO_DB`, `BD_NO_DAEMON` in hooks
- 80% case: Auto-detect binaries, respect env

**Implementation**:
```php
// config/beads.php
return [
    // Binary paths (auto-detect or env override)
    'bd_binary' => env('BD_BINARY', which_binary('bd') ?? '/usr/local/bin/bd'),
    'bv_binary' => env('BV_BINARY', which_binary('bv') ?? '/usr/local/bin/bv'),

    // Working directory
    'working_dir' => env('BD_WORKING_DIR', base_path()),

    // Executor settings
    'executor' => [
        'driver' => env('BD_DRIVER', 'host'),
        'timeout' => (int) env('BD_TIMEOUT', 30),
        'idle_timeout' => (int) env('BD_IDLE_TIMEOUT', 10),
    ],

    // bd environment (pass through from env)
    'environment' => [
        'BD_NO_DB' => env('BD_NO_DB', 'false'),
        'BD_NO_DAEMON' => env('BD_NO_DAEMON', 'false'),
    ],

    // Retry logic
    'retry' => [
        'max_attempts' => (int) env('BD_MAX_RETRIES', 3),
        'delay_ms' => 100,
    ],
];

// Helper function
function which_binary(string $name): ?string
{
    exec("which {$name}", $output, $exitCode);
    return $exitCode === 0 ? trim($output[0] ?? '') : null;
}

// In executor
class SandboxCommandExecutor
{
    private function createExecutionPolicy(): ExecutionPolicy
    {
        return ExecutionPolicy::in(config('beads.working_dir'))
            ->withTimeout(config('beads.executor.timeout'))
            ->withIdleTimeout(config('beads.executor.idle_timeout'))
            ->withEnv(config('beads.environment'), inherit: true);  // Pass bd env vars
    }
}
```

**Why this is sufficient**:
- Respects project env vars set by hooks
- Auto-detects binaries (DRY, no duplication)
- Sensible defaults for standalone usage
- 80% case: Just works out of the box

**Edge cases (defer to 20%)**:
- Multiple bd instances: Use explicit working_dir override
- Custom binary locations: Use env vars, no special logic needed
- Dynamic binary switching: Not a real requirement

---

### ⚠️ Decision 7: Execution Scope (Low Priority)

**Feedback**: Service provider doesn't constrain bd/bv to CLI/agent contexts.

**Decision**: **DEFER - Not a real problem**

**Rationale**:
- This is an authorization concern, not an API design concern
- Laravel already has auth middleware, gates, policies
- 80% case: Beads facade used in console commands and agent scripts
- Web usage is a future requirement (may never happen)

**If needed later** (20% case):
```php
// Add middleware/gate
Gate::define('use-beads', function (User $user) {
    return $user->hasRole('agent') || app()->runningInConsole();
});

// In controller
public function claimTask(string $id)
{
    $this->authorize('use-beads');

    Beads::find($id)->claim();
}
```

**Why defer**:
- No current web usage planned
- Authorization is orthogonal to API design
- Can add later if requirement emerges
- YAGNI principle applies

---

## Summary of Changes

### High Impact (Implement Now)

1. ✅ **TaskId validation**: Accept dynamic prefix (`{project}-{hash}`)
2. ✅ **Add Blocked status**: Match bd reality, simple state machine
3. ✅ **Direct CLI mapping**: Thin wrapper, predictable bd commands
4. ✅ **Safety via array args**: Symfony Process, DTO validation
5. ✅ **Mock-first testing**: Unit tests with mocks, optional integration
6. ✅ **Env-first config**: Respect project env vars, auto-detect binaries

### Low Impact (Defer)

7. ⚠️ **Execution scope**: Not a real problem, handle with standard Laravel auth if needed

---

## Principles Applied

### YAGNI (You Aren't Gonna Need It)

- ❌ No complex state machines
- ❌ No fake bd implementation
- ❌ No batch operation abstraction
- ❌ No execution scope restrictions (not needed yet)

### 80/20 Rule

Focus on:
- ✅ Common cases (agent/CLI usage)
- ✅ Simple patterns (direct CLI mapping)
- ✅ Testable code (mock executor)
- ✅ Safety basics (array args, DTO validation)

Defer:
- ⚠️ Edge cases (UUID IDs, complex workflows)
- ⚠️ Optimization (batch operations, caching)
- ⚠️ Authorization (web usage constraints)

### Pragmatism

- **Simple > Complex**: Direct bd CLI mapping > custom abstraction
- **Working > Perfect**: Accept any valid hash ID > strict schema
- **Testable > Complete**: Mock executor > fixture repository
- **Sufficient > Comprehensive**: Array args + validation > complex escaping

---

## Implementation Updates Required

Update these tasks to reflect decisions:

1. **`partnerspot-heow.2.1`** (Value Objects)
   - Update TaskId regex to accept dynamic prefix
   - Add prefix() and hash() helpers

2. **`partnerspot-heow.2.2`** (Enums)
   - Add `Blocked` case to TaskStatus
   - Add canTransitionTo() method

3. **`partnerspot-heow.2.3`** (Task Entity)
   - Update block() to set Blocked status
   - Add unblock() method

4. **`partnerspot-heow.2.10`** (BdClient)
   - Implement direct CLI command mapping
   - Use array args (no shell)

5. **`partnerspot-heow.2.15`** (Configuration)
   - Env-first config
   - Auto-detect binaries
   - Pass bd environment vars

6. **`partnerspot-heow.2.28-30`** (Testing)
   - Unit tests with mock executor (primary)
   - Optional integration tests (skip if no bd binary)

---

## Non-Changes (Feedback Rejected)

- **Fixture repository**: Over-engineering, mocks are sufficient
- **Custom abstraction**: bd CLI is the contract, keep thin wrapper
- **Execution scope**: Not a real problem, defer to standard auth

---

**Status**: Approved
**Next**: Update affected tasks in bd, proceed with implementation
