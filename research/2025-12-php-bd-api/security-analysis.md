# Security Analysis: Sandbox vs. Input Validation

## Executive Summary

This document analyzes whether sandboxing (using instructor-php's Sandbox) is necessary for executing bd/bv commands from PHP, or if input validation and command whitelisting are sufficient.

**Recommendation**: Start with input validation; add sandboxing only if specific threats materialize.

## Threat Model

### Assumptions

1. **Trusted binaries**: bd and bv are compiled Go/Rust binaries we control
2. **Known interface**: CLI arguments are well-defined and documented
3. **No user code**: We're not executing user-provided scripts or code
4. **Web user context**: PHP runs as www-data or similar non-privileged user
5. **Validated inputs**: All user inputs are validated before passing to CLI

### Attack Vectors

| Attack Vector | Without Sandbox | With Sandbox | Assessment |
|--------------|----------------|--------------|------------|
| **Command Injection** | Mitigated by whitelisting + escaping | Extra layer | Input validation sufficient |
| **Argument Injection** | Mitigated by escaping | Extra layer | Input validation sufficient |
| **Resource Exhaustion** | Timeout + memory limits | Stronger limits | Process limits sufficient |
| **File System Access** | bd already needs .beads/ access | Could restrict | Would break bd functionality |
| **Network Access** | bd/bv don't use network | Unnecessary | Not applicable |
| **Privilege Escalation** | bd runs as web user | No benefit | Not applicable |
| **Binary Replacement** | File system permissions | No benefit | Outside scope |
| **Output Exploitation** | JSON parsing vulnerabilities | No benefit | Standard PHP json_decode |

## Input Validation Strategy

### Command Whitelisting

```php
private const ALLOWED_COMMANDS = [
    'list', 'show', 'create', 'update', 'close', 'delete',
    'ready', 'blocked', 'stats', 'dep', 'comments', 'sync', 'export',
];

private function validateCommand(string $command): void {
    if (!in_array($command, self::ALLOWED_COMMANDS, true)) {
        throw new InvalidBdCommandException($command);
    }
}
```

**Protection**: Only known-safe commands can be executed.

### Argument Validation

```php
class CreateIssueRequest {
    public function __construct(
        public string $title,
        public string $type = 'task',
        public int $priority = 2,
    ) {
        // Validate type
        if (!in_array($this->type, ['task', 'bug', 'feature', 'epic'], true)) {
            throw new InvalidArgumentException("Invalid type: {$this->type}");
        }

        // Validate priority range
        if ($this->priority < 0 || $this->priority > 4) {
            throw new InvalidArgumentException("Priority must be 0-4");
        }

        // Validate title length and content
        if (strlen($this->title) > 500) {
            throw new InvalidArgumentException("Title too long");
        }

        // No shell metacharacters in title
        if (preg_match('/[;&|`$()]/', $this->title)) {
            throw new InvalidArgumentException("Invalid characters in title");
        }
    }
}
```

**Protection**: User inputs are validated against expected formats before CLI execution.

### Argument Escaping

```php
private function buildCommand(string $command, array $args): array {
    $this->validateCommand($command);

    $cmd = [$this->bdBinary, $command];

    foreach ($args as $key => $value) {
        if (is_int($key)) {
            // Positional argument
            $cmd[] = escapeshellarg((string)$value);
        } else {
            // Named argument (--key=value)
            $cmd[] = '--' . escapeshellarg($key) . '=' . escapeshellarg((string)$value);
        }
    }

    return $cmd;
}
```

**Protection**: All arguments are shell-escaped before execution.

### No Shell Execution

```php
// ✅ CORRECT: Direct exec, no shell
$command = ['/usr/local/bin/bd', 'list', '--json'];
proc_open($command, $descriptorspec, $pipes);

// ❌ WRONG: Shell execution (dangerous)
shell_exec("bd list --json");
exec("bd list --json");
system("bd list --json");
```

**Protection**: Use `proc_open()` with array of arguments (no shell interpretation).

## Sandbox Analysis

### What Instructor-PHP Sandbox Provides

From the code review:

```php
// Available sandbox drivers
- HostSandbox: Basic timeout/workdir isolation
- DockerSandbox: Container isolation
- PodmanSandbox: Container isolation (rootless)
- FirejailSandbox: Linux namespace isolation
- BubblewrapSandbox: Linux namespace isolation
```

### Sandbox Benefits

1. **Resource Limits**: CPU, memory, I/O caps (stronger than PHP's)
2. **Filesystem Restrictions**: Read-only mounts, limited paths
3. **Network Restrictions**: No network access (not needed for bd/bv)
4. **PID Isolation**: Can't see/affect other processes
5. **IPC Isolation**: Can't communicate with other processes

### Sandbox Costs

1. **Complexity**: Additional dependency and configuration
2. **Performance**: Container/namespace overhead (50-200ms)
3. **Portability**: Requires Docker/Podman/Firejail/Bubblewrap installed
4. **Development**: Harder to debug sandboxed processes
5. **Maintenance**: Another moving part to maintain

### Does Sandbox Help for bd/bv?

| Sandbox Feature | Value for bd/bv | Rationale |
|----------------|-----------------|-----------|
| Filesystem isolation | ❌ Low | bd needs access to `.beads/` directory |
| Network isolation | ❌ None | bd/bv don't use network |
| Resource limits | ⚠️ Medium | Already handled by timeout/memory limits |
| PID isolation | ❌ Low | bd/bv don't interact with other processes |
| IPC isolation | ❌ Low | bd/bv don't use IPC |
| Privilege isolation | ❌ None | bd already runs as non-privileged user |

**Verdict**: Sandbox provides minimal additional security for bd/bv use case.

## Risk Assessment

### High Risk Scenarios (Need Mitigation)

1. **Command Injection**
   - **Threat**: User input executed as shell command
   - **Mitigation**: ✅ Command whitelisting + argument escaping
   - **Sandbox helps**: Yes, but validation already sufficient

2. **Resource Exhaustion**
   - **Threat**: bd command hangs or consumes excessive resources
   - **Mitigation**: ✅ Timeout + memory limits in process executor
   - **Sandbox helps**: Slightly (stronger limits), but not critical

3. **Output Exploitation**
   - **Threat**: Malicious JSON in bd output exploits json_decode
   - **Mitigation**: ✅ Use json_decode with proper error handling
   - **Sandbox helps**: No

### Medium Risk Scenarios (Monitor)

1. **Concurrent Write Conflicts**
   - **Threat**: Multiple PHP processes write to bd simultaneously
   - **Mitigation**: ⚠️ Retry logic with exponential backoff
   - **Sandbox helps**: No

2. **bd Binary Replacement**
   - **Threat**: Attacker replaces bd binary with malicious version
   - **Mitigation**: ⚠️ File system permissions, checksum validation
   - **Sandbox helps**: No (binary already compromised)

### Low Risk Scenarios (Accept)

1. **Information Disclosure**
   - **Threat**: bd output leaks sensitive information
   - **Mitigation**: ✅ Authorization checks before displaying
   - **Sandbox helps**: No

2. **Denial of Service**
   - **Threat**: Excessive bd calls overwhelm system
   - **Mitigation**: ⚠️ Rate limiting, queuing
   - **Sandbox helps**: Slightly (per-command limits)

## When to Use Sandbox

Sandboxing becomes valuable if:

1. **User-provided code execution**: Running user scripts or plugins with bd
   ```php
   // Example: User-provided bd hooks
   bd create --title="..." --hook="./user-script.sh"
   ```

2. **Untrusted input sources**: Processing issues from untrusted external APIs
   ```php
   // Example: Import from untrusted JSONL
   bd import < untrusted-issues.jsonl
   ```

3. **Multi-tenant environments**: Different customers sharing infrastructure
   ```php
   // Example: SaaS with customer-specific bd instances
   $beads = new Beads($executor, "/tenants/{$customerId}/.beads");
   ```

4. **Paranoid security requirements**: Government, healthcare, finance
   ```php
   // Example: HIPAA/PCI compliance mandates defense-in-depth
   $beads = new Beads(
       new SandboxedExecutor(driver: 'firejail'),
       $workingDir
   );
   ```

5. **Compliance requirements**: Audit requirement for process isolation

## Recommended Implementation

### Phase 1: Input Validation (Now)

```php
class BdClient {
    private const ALLOWED_COMMANDS = ['list', 'show', 'create', ...];

    private function execute(array $args): CommandResult {
        // 1. Whitelist command
        $this->validateCommand($args[0]);

        // 2. Escape all arguments
        $command = array_map('escapeshellarg', $args);

        // 3. Execute without shell
        $result = $this->executor->execute(
            array_merge([$this->bdBinary], $command),
            ['timeout' => 30, 'cwd' => $this->workingDir]
        );

        // 4. Handle errors
        if (!$result->isSuccess()) {
            throw new BdCommandException($result->stderr);
        }

        return $result;
    }

    private function validateCommand(string $command): void {
        if (!in_array($command, self::ALLOWED_COMMANDS, true)) {
            throw new InvalidBdCommandException($command);
        }
    }
}
```

**Security Level**: High for trusted binary execution
**Performance**: Fast (no sandbox overhead)
**Complexity**: Low

### Phase 2: Enhanced Process Limits (If Needed)

```php
class SymfonyProcessExecutor {
    public function execute(array $command, array $options = []): CommandResult {
        $process = new Process($command);
        $process->setTimeout($options['timeout'] ?? 30);
        $process->setWorkingDirectory($options['cwd']);

        // Enhanced resource limits
        $process->setEnv([
            'RLIMIT_CPU' => 30,      // CPU seconds
            'RLIMIT_AS' => 512*1024, // Memory (KB)
            'RLIMIT_NOFILE' => 256,  // Open files
        ]);

        $process->run();

        return new CommandResult(
            $process->getExitCode(),
            $process->getOutput(),
            $process->getErrorOutput(),
            $process->getDuration()
        );
    }
}
```

**Security Level**: High with stronger resource controls
**Performance**: Fast (minimal overhead)
**Complexity**: Low

### Phase 3: Sandbox Integration (Only if Required)

```php
use Cognesy\Utils\Sandbox\Sandbox;
use Cognesy\Utils\Sandbox\Config\ExecutionPolicy;

class SandboxedExecutor implements CanExecuteCommand {
    public function execute(array $command, array $options = []): CommandResult {
        $policy = ExecutionPolicy::create()
            ->withTimeoutSeconds(30)
            ->withIdleTimeoutSeconds(10)
            ->withWorkDir($options['cwd'])
            ->withReadOnlyPaths(['/usr', '/lib', '/etc'])
            ->withReadWritePaths([$options['cwd'] . '/.beads'])
            ->withStdoutLimit(1024 * 1024) // 1MB
            ->withStderrLimit(1024 * 1024);

        $sandbox = Sandbox::firejail($policy);

        // Execute in sandbox
        $result = $sandbox->execute($command);

        return new CommandResult(
            $result->exitCode(),
            $result->stdout(),
            $result->stderr(),
            $result->executionTime()
        );
    }
}

// Usage (configurable)
$executor = config('beads.use_sandbox')
    ? new SandboxedExecutor()
    : new SymfonyProcessExecutor();

$beads = new Beads($executor, base_path());
```

**Security Level**: Very High (defense-in-depth)
**Performance**: Medium (100-200ms overhead)
**Complexity**: Medium (requires sandbox binary)

## Configuration Strategy

Allow sandbox to be enabled via configuration:

```php
// config/beads.php
return [
    'bd_binary' => env('BD_BINARY', '/usr/local/bin/bd'),
    'bv_binary' => env('BV_BINARY', '/usr/local/bin/bv'),
    'working_dir' => base_path(),
    'timeout' => env('BD_TIMEOUT', 30),

    // Sandbox configuration
    'use_sandbox' => env('BD_USE_SANDBOX', false),
    'sandbox_driver' => env('BD_SANDBOX_DRIVER', 'firejail'), // firejail|docker|podman|bubblewrap
    'sandbox_timeout' => env('BD_SANDBOX_TIMEOUT', 30),
    'sandbox_memory_limit' => env('BD_SANDBOX_MEMORY_LIMIT', 512), // MB
];
```

## Testing Recommendations

### Security Tests

```php
class SecurityTest extends TestCase {
    public function test_rejects_invalid_commands(): void {
        $client = new BdClient(new SymfonyProcessExecutor(), '/tmp');

        $this->expectException(InvalidBdCommandException::class);
        $client->execute(['rm', '-rf', '/']); // Should be rejected
    }

    public function test_escapes_shell_metacharacters(): void {
        $client = new BdClient(new SymfonyProcessExecutor(), '/tmp');

        $issue = $client->create(new CreateIssueRequest(
            title: 'Test; rm -rf /',
            type: 'task',
        ));

        // Should create issue with literal semicolon, not execute command
        $this->assertStringContains(';', $issue->title);
    }

    public function test_respects_timeout(): void {
        $client = new BdClient(new SymfonyProcessExecutor(), '/tmp');

        $this->expectException(BdTimeoutException::class);

        // Simulate long-running command
        $client->execute(['sleep', '60'], ['timeout' => 1]);
    }

    public function test_validates_input_types(): void {
        $this->expectException(InvalidArgumentException::class);

        new CreateIssueRequest(
            title: 'Test',
            type: 'invalid_type', // Should fail
        );
    }

    public function test_validates_input_lengths(): void {
        $this->expectException(InvalidArgumentException::class);

        new CreateIssueRequest(
            title: str_repeat('A', 1000), // Too long
        );
    }
}
```

## Conclusion

**For bd/bv PHP API**:
- ✅ **Input validation is sufficient** for current threat model
- ✅ **Command whitelisting** prevents injection attacks
- ✅ **Argument escaping** prevents shell exploitation
- ✅ **Process timeouts** prevent resource exhaustion
- ❌ **Sandbox not needed** for trusted binary execution
- ⚠️ **Monitor** for threats that would justify sandboxing
- ⚠️ **Consider sandbox** if requirements change (user scripts, multi-tenant, compliance)

**Decision Matrix**:

| Factor | No Sandbox | With Sandbox |
|--------|-----------|--------------|
| **Security** | High | Very High |
| **Performance** | Excellent | Good |
| **Complexity** | Low | Medium |
| **Dependencies** | None | Firejail/Docker/etc |
| **Portability** | High | Medium |
| **Debug ease** | Easy | Harder |
| **Recommendation** | ✅ Start here | Add if needed |

Start with input validation. Revisit if threat model changes.
