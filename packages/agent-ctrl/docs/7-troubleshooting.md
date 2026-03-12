---
title: Troubleshooting
description: 'Diagnose and resolve common setup, execution, streaming, and parsing problems when using Agent-Ctrl with CLI-based code agents.'
---

## CLI Binary Not Found

Agent-Ctrl uses `CliBinaryGuard` to verify that the required CLI binary (`claude`, `codex`, `opencode`, `pi`, or `gemini`) is available before every execution. If the binary cannot be found, a `RuntimeException` is thrown immediately -- before any prompt is sent to the agent.

The error message identifies the missing binary and provides installation guidance:

```
Claude Code CLI executable `claude` was not found in PATH. Install Claude Code CLI and ensure `claude` is available in PATH.
```

### Resolution Steps

1. **Verify installation.** Run the CLI binary directly in your terminal to confirm it is installed:
   ```bash
   claude --version
   codex --version
   opencode --version
   pi --version
   gemini --version
   ```

2. **Complete authentication.** Most agents require interactive authentication on first use. Run the CLI interactively at least once to complete any setup flows before using it through Agent-Ctrl.

3. **Check PATH visibility.** The binary must be in the system `PATH` visible to your PHP process. This is not always the same as your shell's `PATH`. If you installed the CLI in a non-standard location (e.g., via `nvm` or a custom prefix), ensure that location is included in the `PATH` used by your web server, supervisor, or CLI runner.

   You can verify the PATH available to PHP:
   ```php
   echo getenv('PATH');
   ```

4. **Consider the sandbox driver.** The binary preflight check behaves differently depending on the sandbox driver:
   - **Host, Firejail, Bubblewrap** -- The binary must be available on the host system. The guard checks the host `PATH`.
   - **Docker, Podman** -- The guard skips the preflight check entirely, because the binary is expected to be inside the container image.

## Working Directory Problems

When you call `inDirectory()`, the bridge validates that the directory exists before changing into it. If it does not exist, an `InvalidArgumentException` is thrown with the path:

```php
// This will throw if /nonexistent/path does not exist
$response = AgentCtrl::claudeCode()
    ->inDirectory('/nonexistent/path')
    ->execute('List files.');
```

### Common Causes

- **Typos or relative paths.** Always use absolute paths. Relative paths resolve against the PHP process's current working directory, which may differ from what you expect.
- **Deleted or unmounted directories.** The directory may have been removed or unmounted between the time you configured the builder and the time execution begins.
- **Permission issues.** The PHP process may not have permission to access the directory. Check file permissions with `ls -la` and ensure the user running PHP has read (and possibly write) access.

### Concurrency Warning

`inDirectory()` changes the PHP process's current working directory using `chdir()` for the duration of the execution and restores it afterward. If your PHP process serves multiple requests concurrently (e.g., with Swoole, RoadRunner, or a threaded server), working directory changes affect the entire process. In concurrent environments, ensure that each request either uses absolute paths exclusively or coordinates directory changes carefully.

## Non-Zero Exit Codes

Agent-Ctrl treats exit codes as data, not errors. A completed execution with a non-zero exit code does **not** throw an exception. It is your responsibility to check the result:

```php
$response = AgentCtrl::codex()->execute('Perform a complex refactoring.');

if (!$response->isSuccess()) {
    echo "Agent failed with exit code: {$response->exitCode}\n";
    echo "Partial output: " . $response->text() . "\n";
}
```

### Common Exit Codes

| Exit Code | Typical Cause |
|-----------|---------------|
| **0** | Successful completion |
| **1** | General error -- invalid configuration, agent-side failure, or unhandled exception within the agent |
| **2** | Invalid arguments passed to the CLI binary |
| **124** | Process killed due to timeout (SIGTERM) |
| **137** | Process killed due to timeout (SIGKILL, after SIGTERM grace period) |

The text output from a failed execution may still contain useful information -- partial results, error messages from the agent, or diagnostic output. Always inspect `text()` even when `isSuccess()` returns `false`.

## Timeout Issues

The default timeout is 120 seconds. Complex tasks, large codebases, or agents that perform many tool calls may need significantly more time:

```php
$response = AgentCtrl::claudeCode()
    ->withTimeout(600) // 10 minutes
    ->execute('Perform a comprehensive codebase review.');
```

When an execution times out:
1. The sandbox executor sends a termination signal to the process.
2. The response contains whatever output was produced before the timeout.
3. The exit code will be non-zero (typically 124 or 137).
4. `isSuccess()` will return `false`.

### Guideline for Timeout Values

| Task Type | Suggested Timeout |
|-----------|-------------------|
| Simple questions or summaries | 60-120 seconds |
| Code review of a single file | 120-300 seconds |
| Multi-file refactoring | 300-600 seconds |
| Full codebase analysis | 600-900 seconds |
| Complex multi-step tasks | 600+ seconds |

Setting timeouts below 30 seconds is generally not recommended. Agents need time to start up, read context, plan, and execute tools before producing output.

## Streaming vs. Process Errors

There are two distinct categories of errors during execution, and they are handled differently.

### Stream Errors (Operational)

These are error events emitted by the agent during normal streaming. They represent issues the agent encountered while working -- a tool failure, a rate limit, an API error, or a malformed request. They are delivered through the `onError()` callback:

```php
$response = AgentCtrl::openCode()
    ->onError(function (string $message, ?string $code): void {
        error_log("Stream error [{$code}]: {$message}");
    })
    ->executeStreaming('Process this task.');
```

Stream errors do **not** prevent the execution from completing. The agent may recover and continue working after emitting an error event. The final `AgentResponse` is still returned normally.

### Process Errors (Fatal)

These are PHP exceptions thrown when something goes fundamentally wrong -- the binary is missing, the working directory does not exist, the process cannot be started, or the sandbox executor encounters a fatal error. These exceptions propagate normally and must be caught with try/catch:

```php
try {
    $response = AgentCtrl::claudeCode()->execute('Do something.');
} catch (\RuntimeException $e) {
    // Binary not found, process failed to start, etc.
    echo "Process error: " . $e->getMessage();
} catch (\InvalidArgumentException $e) {
    // Working directory does not exist
    echo "Configuration error: " . $e->getMessage();
}
```

The key distinction: stream errors are **data** (delivered via callbacks), while process errors are **exceptions** (thrown and propagated through the call stack).

## Parse Failures

Agent-Ctrl parses each agent's JSON Lines output to extract text, tool calls, session IDs, and metadata. If a line contains malformed JSON, the behavior depends on the parser's fail-fast setting:

### Fail-Fast Mode (Default)

When fail-fast is enabled, a `JsonParsingException` is thrown immediately upon encountering malformed JSON. This is the default behavior and ensures that corrupt data does not silently corrupt your results:

```php
use Cognesy\Utils\Json\JsonParsingException;

try {
    $response = AgentCtrl::claudeCode()->execute('Do something.');
} catch (JsonParsingException $e) {
    echo "Malformed JSON in agent output: " . $e->getMessage();
}
```

### Tolerant Mode

When fail-fast is disabled (configured at the bridge level), malformed lines are silently skipped and counted. After execution, you can inspect the parse diagnostics:

```php
if ($response->parseFailures() > 0) {
    echo "Skipped {$response->parseFailures()} malformed JSON lines.\n";
    foreach ($response->parseFailureSamples() as $sample) {
        echo "  Sample: {$sample}\n";
    }
}
```

### Common Causes of Parse Failures

- **CLI version mismatch.** The agent's CLI tool was updated and its output format changed. Update Agent-Ctrl to the latest version.
- **Debug output mixed into the stream.** The agent's CLI is emitting debug messages, warnings, or progress indicators alongside its JSON output. Check the CLI's verbose/quiet settings.
- **Corrupted process output.** The process was interrupted mid-line, producing incomplete JSON. This can happen during timeouts or system resource exhaustion.

## Debugging with the Console Logger

Agent-Ctrl includes a built-in `AgentCtrlConsoleLogger` that displays detailed, color-coded execution telemetry. This is invaluable for understanding the execution flow, diagnosing performance bottlenecks, and identifying where problems occur.

### Basic Usage

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Broadcasting\AgentCtrlConsoleLogger;

$logger = new AgentCtrlConsoleLogger();

$response = AgentCtrl::claudeCode()
    ->wiretap($logger->wiretap())
    ->execute('Analyze this codebase.');
```

### Configuration Options

The logger accepts several constructor parameters to control what is displayed:

```php
$logger = new AgentCtrlConsoleLogger(
    useColors: true,          // Color-coded output (auto-detects terminal support)
    showTimestamps: true,     // Show HH:MM:SS.mmm timestamps
    showAgentType: true,      // Show [claude-code] / [codex] / [opencode] prefix
    showToolArgs: true,       // Show tool input arguments
    showStreaming: true,      // Show stream processing events
    showSandbox: true,        // Show sandbox setup events
    showPipeline: true,       // Show request/response pipeline events
    maxArgLength: 100,        // Truncate tool arguments to this length
);
```

### Event Categories

The logger groups events into categories with color-coded labels:

| Label | Color | Events |
|-------|-------|--------|
| `EXEC` | Cyan | Execution started |
| `DONE` | Green | Execution completed (with exit code, tool count, cost, tokens) |
| `FAIL` | Red | Error occurred |
| `TOOL` | Yellow | Tool used (with name and arguments) |
| `TEXT` | Gray | Text received (with length) |
| `PROC` | Cyan | Process started / completed |
| `SBOX` | Blue | Sandbox initialized / policy configured / ready |
| `STRM` | Gray | Stream processing started / completed |
| `REQT` | Gray | Request built |
| `CMD` | Gray | Command spec created |
| `RESP` | Gray | Response parsing started / data extracted / completed |

### Example Output

```
14:23:01.456 [claude-code] [EXEC] Execution started [model=claude-sonnet-4-5, prompt=Analyze this codebase...]
14:23:01.458 [claude-code] [PROC] Process started [commands=12]
14:23:03.210 [claude-code] [TOOL] Read {path=/src/UserService.php}
14:23:04.890 [claude-code] [TOOL] Bash {command=php -l src/UserService.php}
14:23:06.123 [claude-code] [TEXT] Text received [length=1432]
14:23:06.125 [claude-code] [DONE] Execution completed [exit=0, tools=5, tokens=0]
```

## Common Pitfalls

**Using `continueSession()` with the wrong agent.** Session IDs are agent-specific. Session IDs are agent-specific and incompatible across bridges. Always use the same agent type when continuing or resuming a session.

**Forgetting to check `isSuccess()`.** A completed execution with a non-zero exit code does not throw an exception. Always verify the result before using the text output as authoritative.

**Setting very short timeouts.** Agents need time to start up, read context, and execute tools. Timeouts under 30 seconds may cause premature termination for anything beyond trivial prompts.

**Relative paths in `inDirectory()`.** Always use absolute paths. Relative paths resolve against the PHP process's current working directory, which may differ from your expectations depending on how the process was started.

**Running in concurrent PHP environments.** The `inDirectory()` method uses `chdir()`, which affects the entire PHP process. In Swoole, RoadRunner, or other concurrent PHP environments, this can cause race conditions between requests. Use absolute paths throughout and avoid `inDirectory()` if possible, or ensure proper isolation.

**Ignoring parse failures.** If `parseFailures()` returns a non-zero value, some of the agent's output was not processed. This may mean missing tool calls, incomplete text, or lost metadata. Investigate the `parseFailureSamples()` to determine the cause.
