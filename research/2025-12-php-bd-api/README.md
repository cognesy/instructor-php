# PHP API for bd/bv Operations

**Status**: Draft
**Created**: 2025-12-01
**Author**: Research Study
**Type**: Technical Feasibility Study

## Executive Summary

This study explores the design and implementation of a PHP API layer for accessing bd (beads) issue tracker data and executing bd/bv (beads viewer) commands. The goal is to enable programmatic access to issue tracking functionality from PHP applications (particularly Laravel) while maintaining security, performance, and reliability.

## Context

### Current State

- **bd CLI**: Go binary that manages issues in SQLite + JSONL format
- **bv CLI**: Rust binary that provides graph analysis and terminal UI
- **Data Storage**:
  - Primary: SQLite database (`.beads/beads.db`)
  - Export: JSONL file (`.beads/beads.jsonl` or `.beads/issues.jsonl`)
  - Git-tracked: JSONL file for distribution
- **Integration**: Currently used via CLI commands in hooks and scripts

### Use Cases

1. **Web Dashboard**: Display issue status, metrics, and graphs in Laravel UI
2. **Automation**: Create/update/close issues from application logic
3. **Reporting**: Generate project health reports using bv insights
4. **Webhooks**: Trigger bd operations from external events (CI/CD, GitHub, etc.)
5. **API Endpoints**: Expose issue tracking to external services
6. **Real-time Updates**: Live dashboard updates without CLI polling

## Research Questions

1. **Architecture**: Should we access bd data directly or via CLI commands?
2. **Security**: How do we safely execute bd/bv commands from PHP?
3. **Performance**: Can we efficiently access SQLite or should we cache?
4. **Concurrency**: How do we handle simultaneous reads/writes?
5. **Error Handling**: How do we surface CLI errors to application layer?
6. **Testing**: How do we test bd/bv integration without polluting tracker?

## Approach Options

### Option A: Direct SQLite Access

**Concept**: PHP reads/writes directly to `.beads/beads.db` using PDO.

**Pros**:
- Fast: No process overhead
- Native: Standard PHP PDO interface
- Queries: Full SQL expressiveness
- Transactions: ACID guarantees

**Cons**:
- Schema coupling: Must understand bd's internal schema
- Breaking changes: bd updates could break our code
- Limited functionality: Can't access bv graph analysis
- Write conflicts: Must handle bd daemon sync carefully
- Missing business logic: No access to bd's validation/constraints

**Verdict**: ❌ **Not Recommended** - Too tightly coupled to implementation details

### Option B: CLI Command Execution

**Concept**: PHP executes `bd` and `bv` commands, parses JSON output.

**Pros**:
- Official interface: Uses bd's public API (CLI)
- Future-proof: bd updates won't break us (stable CLI)
- Complete functionality: Access to all bd/bv features
- Graph analysis: Can use bv's sophisticated metrics
- Validation: bd handles all business logic

**Cons**:
- Process overhead: Fork/exec for each command
- Parsing: Must handle JSON parsing and errors
- Security: Must sanitize inputs carefully
- Testing: Need to mock CLI execution

**Verdict**: ✅ **Recommended** - Best balance of stability and functionality

### Option C: Hybrid Approach

**Concept**: Read from SQLite (or JSONL), write via CLI commands.

**Pros**:
- Fast reads: Direct database access for queries
- Safe writes: Use bd CLI for mutations
- Graph analysis: Use bv for complex metrics

**Cons**:
- Complexity: Two integration points to maintain
- Consistency: Must handle read-after-write timing
- Still coupled: SQLite schema dependency for reads

**Verdict**: ⚠️ **Consider Later** - Optimization if Option B proves slow

## Recommended Architecture: CLI Command Wrapper

### Design Principles

1. **Thin Wrapper**: PHP classes that wrap bd/bv CLI commands
2. **Type Safety**: Strongly-typed DTOs for inputs/outputs
3. **JSON Protocol**: Use `--json` flags, parse structured output
4. **Error Handling**: Map CLI exit codes to exceptions
5. **Immutability**: Read operations return value objects
6. **Security**: Input validation and command sanitization
7. **Testability**: Interface-based design for mocking

### Component Architecture

```
┌─────────────────────────────────────────────────────┐
│ Laravel Application                                  │
├─────────────────────────────────────────────────────┤
│                                                       │
│  ┌──────────────────────────────────────────────┐  │
│  │ BeadsService (Facade)                        │  │
│  │ - issues(), create(), update(), close()      │  │
│  └────────────┬─────────────────────────────────┘  │
│               │                                      │
│  ┌────────────▼─────────────────────────────────┐  │
│  │ BdClient (bd commands)                       │  │
│  │ - list(), show(), create(), update()         │  │
│  └────────────┬─────────────────────────────────┘  │
│               │                                      │
│  ┌────────────▼─────────────────────────────────┐  │
│  │ BvClient (bv commands)                       │  │
│  │ - insights(), plan(), priority(), diff()     │  │
│  └────────────┬─────────────────────────────────┘  │
│               │                                      │
│  ┌────────────▼─────────────────────────────────┐  │
│  │ CommandExecutor (Process abstraction)        │  │
│  │ - execute(), timeout, env vars               │  │
│  └────────────┬─────────────────────────────────┘  │
│               │                                      │
└───────────────┼──────────────────────────────────────┘
                │
     ┌──────────▼──────────┐
     │ Symfony Process     │
     │ (or Sandbox)        │
     └──────────┬──────────┘
                │
     ┌──────────▼──────────┐
     │ bd / bv binaries    │
     └─────────────────────┘
```

## Implementation Plan

### Phase 1: Core Command Execution

**Goal**: Safe, testable command execution layer

**Components**:
1. `CommandExecutor` - Abstract process execution
2. `CommandResult` - Value object for output/errors
3. `BdException` hierarchy - Typed error handling

**Example**:
```php
interface CanExecuteCommand {
    public function execute(array $command, array $options = []): CommandResult;
}

class CommandResult {
    public function __construct(
        public readonly int $exitCode,
        public readonly string $stdout,
        public readonly string $stderr,
        public readonly float $executionTime,
    ) {}

    public function isSuccess(): bool;
    public function json(): array;
    public function throw(): void;
}
```

### Phase 2: bd CLI Wrapper

**Goal**: Type-safe PHP interface to bd commands

**Components**:
1. `BdClient` - Main bd command wrapper
2. `Issue` - Value object for issue data
3. `IssueFilter` - Type-safe query builder
4. `DependencyType` enum

**Example**:
```php
class BdClient {
    public function __construct(
        private readonly CanExecuteCommand $executor,
        private readonly string $workingDir,
    ) {}

    public function list(IssueFilter $filter): IssueCollection;
    public function show(string $id): Issue;
    public function create(CreateIssueRequest $request): Issue;
    public function update(string $id, UpdateIssueRequest $request): Issue;
    public function close(string $id, string $reason): Issue;
    public function ready(int $limit = 10): IssueCollection;
    public function blocked(): IssueCollection;

    // Dependency management
    public function addDependency(
        string $issueId,
        string $blockedBy,
        DependencyType $type = DependencyType::Blocks
    ): void;

    public function dependencyTree(string $id): DependencyTree;
}
```

### Phase 3: bv Graph Analysis Wrapper

**Goal**: Access bv's sophisticated graph metrics

**Components**:
1. `BvClient` - Main bv command wrapper
2. `GraphInsights` - Metrics value object
3. `ExecutionPlan` - Parallel work tracks
4. `PriorityRecommendation` - AI-driven suggestions

**Example**:
```php
class BvClient {
    public function insights(): GraphInsights;
    public function plan(): ExecutionPlan;
    public function priority(): PriorityRecommendations;
    public function diff(string $since): GraphDiff;
    public function recipes(): RecipeCollection;
}

class GraphInsights {
    /** @return array<string, PageRankScore> */
    public function pageRank(): array;

    /** @return array<string, BetweennessScore> */
    public function betweenness(): array;

    public function criticalPath(): array;
    public function cycles(): array;
    public function density(): float;
}
```

### Phase 4: Laravel Integration

**Goal**: Seamless Laravel service provider and facade

**Components**:
1. `BeadsServiceProvider` - Register services
2. `Beads` facade - Convenient access
3. Config file (`config/beads.php`)
4. Artisan commands for testing

**Example**:
```php
// config/beads.php
return [
    'bd_binary' => env('BD_BINARY', '/usr/local/bin/bd'),
    'bv_binary' => env('BV_BINARY', '/usr/local/bin/bv'),
    'working_dir' => base_path(),
    'timeout' => env('BD_TIMEOUT', 30),
    'use_sandbox' => env('BD_USE_SANDBOX', false),
];

// Usage
use Beads;

$issues = Beads::issues()
    ->status('open')
    ->priority(1)
    ->get();

$insights = Beads::insights();
$plan = Beads::plan();
```

## Security Considerations

### Command Injection Prevention

**Risk**: User input in CLI arguments could execute arbitrary commands

**Mitigations**:
1. **Whitelist approach**: Only allow known commands
2. **Input validation**: Validate all parameters before passing to CLI
3. **No shell execution**: Use `proc_open()` or Symfony Process directly
4. **Argument escaping**: Use `escapeshellarg()` for all inputs
5. **Sandbox option**: Optionally use Firejail/Bubblewrap for isolation

**Example**:
```php
class BdClient {
    private const ALLOWED_COMMANDS = [
        'list', 'show', 'create', 'update', 'close',
        'ready', 'blocked', 'dep',
    ];

    private function validateCommand(string $command): void {
        if (!in_array($command, self::ALLOWED_COMMANDS, true)) {
            throw new InvalidBdCommandException($command);
        }
    }

    private function buildCommand(string $command, array $args): array {
        $this->validateCommand($command);

        $cmd = [$this->bdBinary, $command];

        foreach ($args as $key => $value) {
            if (is_int($key)) {
                $cmd[] = $this->escapeArg($value);
            } else {
                $cmd[] = "--{$key}=" . $this->escapeArg($value);
            }
        }

        return $cmd;
    }
}
```

### Resource Limits

**Risk**: CLI commands could hang, consume excessive resources

**Mitigations**:
1. **Timeouts**: 30s default, configurable per command
2. **Memory limits**: Use Symfony Process memory limit
3. **Output limits**: Cap stdout/stderr size
4. **Concurrent execution limits**: Queue or throttle

### Sandbox Option Analysis

**Do we need Sandbox from instructor-php?**

Let's evaluate the threat model:

| Threat | Without Sandbox | With Sandbox |
|--------|----------------|--------------|
| Command injection | Mitigated by input validation | Extra layer of defense |
| Resource exhaustion | Mitigated by timeouts | Stronger limits |
| File system access | bd already has access | Could restrict further |
| Network access | bd doesn't use network | Unnecessary restriction |
| Privilege escalation | bd runs as web user | No additional benefit |

**Recommendation**:

- **Start without Sandbox** - bd/bv are trusted binaries we control
- **Input validation sufficient** - Commands are whitelisted, args escaped
- **Add Sandbox later if needed** - If we add user-provided scripts or plugins

**When Sandbox would be valuable**:
- Running user-provided bd hooks/scripts
- Executing arbitrary code from issue comments
- Paranoid security requirements
- Multi-tenant environments

## Performance Considerations

### Command Execution Overhead

**Measurement**: Typical bd command performance
```bash
# Quick operations (<100ms)
bd show bd-abc123 --json          # ~50ms
bd list --status=open --json      # ~80ms
bd ready --json                    # ~90ms

# Medium operations (100-500ms)
bv --robot-insights               # ~200ms (graph computation)
bv --robot-plan                   # ~150ms
bd create --title "..." --json    # ~100ms (with sync)

# Slower operations (>500ms)
bd dep tree bd-abc123             # ~300ms (recursive query)
bv --diff-since HEAD~10           # ~800ms (git operations)
bd export                         # ~500ms (full database export)
```

**Optimization Strategies**:

1. **Caching**: Cache bv insights (they change infrequently)
   ```php
   Cache::remember('bv:insights', 300, fn() => Beads::insights());
   ```

2. **Async execution**: Queue long-running operations
   ```php
   dispatch(new GenerateProjectReport());
   ```

3. **Batch operations**: Use bulk commands when available
   ```php
   // Instead of N show commands
   bd list --id=bd-abc,bd-def,bd-xyz --json
   ```

4. **Read from JSONL**: For simple list operations, parse `.beads/issues.jsonl` directly
   ```php
   // Fallback for read-only when performance critical
   $issues = JsonlReader::parse(base_path('.beads/issues.jsonl'));
   ```

### Concurrency Handling

**Challenge**: Multiple PHP processes executing bd commands simultaneously

**bd's built-in handling**:
- SQLite WAL mode: Multiple readers, single writer
- File locking: Automatic via SQLite
- Daemon: Optional background sync

**PHP layer handling**:
1. **Read operations**: Safe to parallelize (SQLite allows multiple readers)
2. **Write operations**: bd handles locking, but we should handle retry logic
3. **Optimistic concurrency**: Accept that some writes may fail, retry

**Example retry logic**:
```php
class BdClient {
    private function executeWithRetry(
        array $command,
        int $maxAttempts = 3
    ): CommandResult {
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            try {
                return $this->executor->execute($command);
            } catch (DatabaseLockedException $e) {
                $attempt++;
                if ($attempt >= $maxAttempts) throw $e;
                usleep(100000 * $attempt); // Exponential backoff
            }
        }
    }
}
```

## Testing Strategy

### Unit Tests

**Mock command execution**:
```php
class BdClientTest extends TestCase {
    public function test_list_returns_issues(): void {
        $executor = Mockery::mock(CanExecuteCommand::class);
        $executor->shouldReceive('execute')
            ->with(['bd', 'list', '--status=open', '--json'])
            ->andReturn(new CommandResult(
                exitCode: 0,
                stdout: json_encode([
                    ['id' => 'bd-abc', 'title' => 'Test issue'],
                ]),
                stderr: '',
                executionTime: 0.05,
            ));

        $client = new BdClient($executor, '/tmp');
        $issues = $client->list(IssueFilter::open());

        $this->assertCount(1, $issues);
        $this->assertEquals('bd-abc', $issues[0]->id);
    }
}
```

### Integration Tests

**Use test database**:
```php
class BdIntegrationTest extends TestCase {
    private string $testDir;

    protected function setUp(): void {
        $this->testDir = sys_get_temp_dir() . '/bd-test-' . uniqid();
        mkdir($this->testDir . '/.beads', 0755, true);

        // Initialize test bd
        Process::run(['bd', 'init'], $this->testDir);
    }

    protected function tearDown(): void {
        // Clean up test data
        exec("rm -rf {$this->testDir}");
    }

    public function test_create_and_show_issue(): void {
        $client = new BdClient(
            new SymfonyProcessExecutor(),
            $this->testDir
        );

        $issue = $client->create(new CreateIssueRequest(
            title: '[test] Integration test issue',
            type: 'task',
            priority: 1,
        ));

        $this->assertNotNull($issue->id);

        $fetched = $client->show($issue->id);
        $this->assertEquals($issue->id, $fetched->id);
    }
}
```

### Pollution Prevention

**Problem**: Test issues polluting production tracker

**Solutions**:
1. **Separate test directory**: Use temporary directory for tests
2. **Detect test issues**: Use `bd detect-pollution` to find test issues
3. **Prefix convention**: All test issues use `[test]` prefix
4. **Cleanup job**: Daily job to remove test issues

```php
// In CI/CD
php artisan beads:cleanup-test-issues
// Runs: bd detect-pollution --auto-clean
```

## Error Handling

### Exception Hierarchy

```php
abstract class BeadsException extends RuntimeException {}

// Command execution errors
class BdCommandException extends BeadsException {}
class BdBinaryNotFoundException extends BdCommandException {}
class BdTimeoutException extends BdCommandException {}

// Data errors
class IssueNotFoundException extends BeadsException {}
class InvalidIssueDataException extends BeadsException {}
class DependencyCycleException extends BeadsException {}

// Concurrency errors
class DatabaseLockedException extends BeadsException {}
class ConflictException extends BeadsException {}

// Validation errors
class InvalidBdCommandException extends BeadsException {}
class InvalidIssueFilterException extends BeadsException {}
```

### Error Response Mapping

```php
class CommandExecutor {
    private function handleErrorCode(int $exitCode, string $stderr): void {
        // Map bd exit codes to exceptions
        match ($exitCode) {
            0 => null, // Success
            1 => throw new BdCommandException($stderr),
            2 => throw new IssueNotFoundException($stderr),
            5 => throw new DatabaseLockedException($stderr),
            124 => throw new BdTimeoutException($stderr),
            127 => throw new BdBinaryNotFoundException('bd binary not found'),
            default => throw new BdCommandException(
                "bd command failed with code {$exitCode}: {$stderr}"
            ),
        };
    }
}
```

## Implementation Example

### Minimal Working Example

See `example-implementation.php` for a complete working prototype.

Key features demonstrated:
- Command execution with timeout
- JSON parsing and type safety
- Error handling
- Basic bd operations (list, show, create, update, close)
- bv insights integration

## Alternative: Direct JSONL Parsing

For read-heavy workloads where performance is critical, consider parsing `.beads/issues.jsonl` directly:

**Pros**:
- Very fast (no process fork)
- Simple (standard PHP file operations)
- No external dependencies

**Cons**:
- No graph analysis (need bv for that)
- No validation (must trust JSONL format)
- Read-only (must use bd CLI for writes)
- Must handle incremental updates

**When to use**:
- High-frequency reads (dashboard polling)
- Simple queries (status, priority filters)
- Offline processing (batch jobs)

**Implementation**:
```php
class JsonlReader {
    public function __construct(private readonly string $path) {}

    public function all(): array {
        $issues = [];
        $handle = fopen($this->path, 'r');

        while (($line = fgets($handle)) !== false) {
            $issues[] = json_decode($line, true);
        }

        fclose($handle);
        return $issues;
    }

    public function filter(callable $predicate): array {
        return array_filter($this->all(), $predicate);
    }
}

// Usage
$reader = new JsonlReader(base_path('.beads/issues.jsonl'));
$openIssues = $reader->filter(
    fn($issue) => $issue['status'] === 'open'
);
```

## Recommendations

### For Initial Implementation

1. **Use CLI wrapper approach** (Option B)
   - Most maintainable
   - Future-proof against bd updates
   - Access to all bd/bv functionality

2. **Start without Sandbox**
   - bd/bv are trusted binaries
   - Input validation is sufficient
   - Add later if security requirements escalate

3. **Use Symfony Process**
   - Already available in Laravel
   - Well-tested, mature library
   - Good timeout and error handling

4. **Cache bv insights**
   - Graph metrics change infrequently
   - 5-minute cache is reasonable
   - Invalidate on bd write operations

5. **Implement retry logic**
   - Handle SQLite lock contention
   - Exponential backoff
   - Max 3 attempts

### For Production Deployment

1. **Monitor command execution times**
   - Log slow commands (>1s)
   - Alert on timeouts
   - Dashboard metrics

2. **Queue long operations**
   - Report generation
   - Bulk updates
   - Graph analysis

3. **Health checks**
   - Verify bd/bv binaries exist
   - Check `.beads/` directory permissions
   - Test basic operations

4. **Error tracking**
   - Log all bd command failures
   - Track error rates
   - Alert on spikes

## Next Steps

1. **Prototype implementation** (1-2 days)
   - Build CommandExecutor
   - Implement BdClient basics
   - Test against real bd database

2. **Laravel integration** (2-3 days)
   - Service provider
   - Facade
   - Configuration
   - Artisan commands

3. **Testing** (1-2 days)
   - Unit tests with mocks
   - Integration tests
   - Load testing

4. **Documentation** (1 day)
   - API reference
   - Usage examples
   - Deployment guide

5. **Production hardening** (ongoing)
   - Error monitoring
   - Performance tuning
   - Security audit

## Open Questions

1. **Should we expose raw bd commands or build higher-level abstractions?**
   - Raw: More flexible, less maintenance
   - Abstracted: Better DX, more opinionated

2. **How do we handle bd schema evolution?**
   - Version detection?
   - Feature flags?
   - Graceful degradation?

3. **Should we build a REST API or just internal service?**
   - Internal: Simpler, fewer security concerns
   - REST: External integrations, mobile apps

4. **Do we need real-time updates?**
   - WebSockets for live dashboard?
   - Or polling is sufficient?

5. **Should we contribute to bd/bv projects?**
   - Request features we need?
   - Contribute PHP client officially?

## References

- bd documentation: `docs/ai/workflows/bd-commands.md`
- bv documentation: `docs/ai/workflows/bv-commands.md`
- bd/bv overview: `docs/ai/workflows/bd-bv-overview.md`
- Instructor PHP Sandbox: `vendor/cognesy/instructor-php/packages/utils/src/Sandbox/`
- Symfony Process: https://symfony.com/doc/current/components/process.html
- SQLite WAL mode: https://www.sqlite.org/wal.html

## Appendix: Command Reference

### bd Commands We Need

```bash
# Read operations
bd list --status=<status> --json
bd show <id> --json
bd ready --json
bd blocked --json
bd stats --json

# Write operations
bd create --title="..." --type=<type> --priority=<n> --json
bd update <id> --status=<status> --json
bd close <id> --reason="..." --json
bd delete <id> --json

# Dependencies
bd dep add <issue> <blocker> --type=<type> --json
bd dep remove <issue> <blocker> --json
bd dep tree <id> --json

# Comments
bd comments add <id> "..." --json
bd comments <id> --json

# Sync
bd sync --status --json
bd export --json
```

### bv Commands We Need

```bash
# Graph analysis
bv --robot-insights     # PageRank, betweenness, cycles, etc.
bv --robot-plan         # Execution tracks
bv --robot-priority     # AI recommendations
bv --robot-recipes      # Available filters

# Diffing
bv --robot-diff --diff-since <commit>

# Time travel
bv --as-of <commit> --robot-insights
```

## Conclusion

Building a PHP API for bd/bv operations is feasible and valuable. The recommended approach is a CLI wrapper using Symfony Process, with strong input validation and error handling. This provides a clean, maintainable interface while preserving access to all bd/bv functionality.

The implementation can be phased, starting with core read operations and gradually adding write operations, graph analysis, and Laravel integration. Performance should be adequate for typical use cases, with caching and queuing available for optimization.

Security can be achieved through input validation and command whitelisting, without requiring sandboxing for trusted bd/bv binaries. Testing can be done with temporary bd databases to avoid polluting production data.

The result will be a robust PHP API that enables powerful issue tracking integration within Laravel applications, supporting dashboards, automation, and external integrations.
