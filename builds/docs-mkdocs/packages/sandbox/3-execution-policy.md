---
title: 'Execution Policy'
description: 'Configure timeout, memory, file-system paths, environment variables, network access, and output limits with an immutable policy object.'
---

## Introduction

The `ExecutionPolicy` class is the central configuration object for every sandbox execution. It controls how long a command may run, how much memory it may consume, which files it can access, which environment variables are available, whether network access is permitted, and how much output is retained.

The policy is **immutable**: every `with*()` method returns a new `ExecutionPolicy` instance, leaving the original unchanged. This makes policies safe to share across services, store in configuration, and compose through method chaining without any risk of side effects.

```php
use Cognesy\Sandbox\Config\ExecutionPolicy;

$base = ExecutionPolicy::in('/tmp')->withTimeout(10);

// $base is unchanged -- $extended is a new instance
$extended = $base->withMemory('256M')->withNetwork(true);
// @doctest id="cdb4"
```

## Creating a Policy

### From a Directory

The most common way to create a policy is with the `in()` static factory, which sets the base working directory:

```php
$policy = ExecutionPolicy::in('/var/sandbox');
// @doctest id="1e24"
```

The base directory must exist and be writable. For container drivers (Docker, Podman, Firejail, Bubblewrap), a unique temporary subdirectory is created inside this path for each execution and automatically cleaned up afterward.

### Default Policy

When you do not need a specific directory, use `default()`, which sets the base directory to `/tmp`:

```php
$policy = ExecutionPolicy::default();
// @doctest id="a24c"
```

### Default Values

A freshly created policy uses the following defaults:

| Setting | Default | Description |
|---|---|---|
| `baseDir` | `/tmp` (or the value passed to `in()`) | Working directory / temp parent |
| `timeoutSeconds` | `5` | Maximum wall-clock duration |
| `idleTimeoutSeconds` | `null` (disabled) | Maximum time without output |
| `memoryLimit` | `128M` | Memory cap (container drivers) |
| `readablePaths` | `[]` | Extra paths mounted as read-only |
| `writablePaths` | `[]` | Extra paths mounted as read-write |
| `env` | `[]` | Explicit environment variables |
| `inheritEnv` | `false` | Whether to inherit host environment |
| `networkEnabled` | `false` | Whether network access is allowed |
| `stdoutLimitBytes` | `1048576` (1 MB) | Maximum retained stdout |
| `stderrLimitBytes` | `1048576` (1 MB) | Maximum retained stderr |

## Timeout Configuration

### Wall-Clock Timeout

The wall-clock timeout defines the maximum number of seconds a command may run before it is forcefully terminated. The minimum allowed value is 1 second.

```php
$policy = $policy->withTimeout(30); // 30 seconds
// @doctest id="abd9"
```

When a timeout occurs, the resulting `ExecResult` will have `timedOut()` returning `true` and an exit code of `124` (matching the GNU `timeout` convention).

### Idle Timeout

The idle timeout terminates a command if it produces no output for the specified number of seconds. This is useful for detecting stuck processes that are technically still running but have stopped making progress.

```php
$policy = $policy->withIdleTimeout(10); // Kill after 10 seconds of silence
// @doctest id="8b72"
```

To disable the idle timeout (the default), pass `null`:

```php
$policy = $policy->withIdleTimeout(null);
// @doctest id="55d3"
```

The idle timeout is tracked independently of the wall-clock timeout. A command is terminated as soon as either limit is reached, whichever comes first. The `TimeoutTracker` internally records the reason (`TimeoutReason::WALL` or `TimeoutReason::IDLE`) for diagnostic purposes.

## Memory Limit

The memory limit controls the maximum amount of RAM a sandboxed process may use. It is enforced by container drivers (Docker, Podman) via `--memory` flags. The host driver does not enforce memory limits at the OS level.

```php
$policy = $policy->withMemory('256M');
// @doctest id="209b"
```

Accepted formats include numeric values with `K`, `M`, or `G` suffixes (e.g., `512K`, `256M`, `1G`). The value is normalized internally to megabytes and clamped to a maximum of 1 GB. An `InvalidArgumentException` is thrown for invalid formats or if `-1` (unbounded) is passed.

```php
// All of these are valid
$policy->withMemory('512M');  // 512 megabytes
$policy->withMemory('1G');    // Clamped to 1024M
$policy->withMemory('65536K'); // Normalized to 64M
// @doctest id="2d83"
```

## File-System Access

Container and sandbox drivers restrict file-system access by default. Only the working directory is writable. To grant access to additional paths, use the `withReadablePaths()` and `withWritablePaths()` methods.

### Readable Paths

Mount host paths as read-only inside the sandbox:

```php
$policy = $policy->withReadablePaths('/data/config', '/etc/app');
// @doctest id="c1f5"
```

In container drivers, these are mounted at sequential container paths: `/mnt/ro0`, `/mnt/ro1`, and so on. Your command should reference these container paths, not the host paths.

### Writable Paths

Mount host paths as read-write inside the sandbox:

```php
$policy = $policy->withWritablePaths('/data/output', '/var/cache');
// @doctest id="4cbb"
```

In container drivers, these are mounted at `/mnt/rw0`, `/mnt/rw1`, etc.

### Important Notes

- Both methods **replace** the current list of paths. Pass all paths in a single call:
  ```php
  // Correct -- both paths are included
  $policy = $policy->withReadablePaths('/data/a', '/data/b');

  // Incorrect -- only '/data/b' survives
  $policy = $policy->withReadablePaths('/data/a');
  $policy = $policy->withReadablePaths('/data/b');
  ```
- Paths containing symlinks, `..` components, or colons are silently skipped for safety. Use `realpath()` to resolve symlinks before passing paths to the policy.

## Environment Variables

By default, the sandboxed process receives no environment variables from the host. You can pass specific variables and optionally inherit the host environment.

### Explicit Variables

Pass an associative array of key-value pairs:

```php
$policy = $policy->withEnv([
    'APP_ENV' => 'testing',
    'LOG_LEVEL' => 'debug',
]);
// @doctest id="79fc"
```

### Inheriting the Host Environment

To start with the host's environment and then overlay your own variables, pass `inherit: true`:

```php
$policy = $policy->withEnv(
    ['APP_ENV' => 'staging'],
    inherit: true,
);
// @doctest id="b5fb"
```

You can also toggle inheritance independently:

```php
$policy = $policy->inheritEnvironment(true);
// @doctest id="e001"
```

### Blocked Variables

Certain security-sensitive environment variables are **always** stripped, regardless of inheritance settings. This is a hard-coded safety measure that cannot be overridden. The blocked patterns include:

- **Dynamic linker:** `LD_PRELOAD`, `LD_LIBRARY_PATH`, `LD_AUDIT`, `DYLD_INSERT_LIBRARIES`, `DYLD_LIBRARY_PATH`, `DYLD_FRAMEWORK_PATH`
- **PHP configuration:** `PHP_INI_SCAN_DIR`, `PHPRC`
- **Cloud credentials:** `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_SESSION_TOKEN`, `GOOGLE_APPLICATION_CREDENTIALS`, `GCP_*`, `AZURE_CLIENT_ID`, `AZURE_CLIENT_SECRET`
- **Language tooling:** `GEM_HOME`, `GEM_PATH`, `RUBY*`, `NODE_OPTIONS`, `NPM_*`, `PYTHON*`, `PIP_*`

## Network Access

Network access is disabled by default. Enable it when your command needs to reach external services:

```php
$policy = $policy->withNetwork(true);
// @doctest id="d6b6"
```

For container drivers, this controls the `--network` flag (`none` vs. default bridge). For the host driver, this setting serves as a policy declaration only -- no OS-level network restriction is enforced. For Firejail, the `--net=none` flag is used. For Bubblewrap, the `--unshare-net` flag isolates the network namespace.

## Output Caps

Output caps limit how much stdout and stderr data is retained in memory. When a stream exceeds its cap, only the most recent bytes (up to the cap size) are kept, and the corresponding `truncatedStdout()` or `truncatedStderr()` flag is set on the result.

```php
$policy = $policy->withOutputCaps(
    stdoutBytes: 5 * 1024 * 1024,  // 5 MB for stdout
    stderrBytes: 1 * 1024 * 1024,  // 1 MB for stderr
);
// @doctest id="8a67"
```

The minimum cap is 1024 bytes. Values below this threshold are automatically clamped upward. The default cap is 1 MB (1,048,576 bytes) for each stream.

Output caps protect your application from memory exhaustion when running commands that produce large amounts of output. The streaming callback (if provided) still receives all chunks in real time, even when the retained buffer is truncated.

## The `with()` Method

For advanced use cases, you can set multiple policy properties in a single call using the general-purpose `with()` method. Any parameter you omit retains its current value:

```php
$policy = $policy->with(
    timeoutSeconds: 60,
    memoryLimit: '512M',
    networkEnabled: true,
    inheritEnv: true,
);
// @doctest id="c45b"
```

This is the same mechanism that all `with*()` convenience methods use internally.

## Accessing Policy Values

Every policy setting has a corresponding accessor method:

```php
$policy->baseDir();           // string
$policy->timeoutSeconds();    // int
$policy->idleTimeoutSeconds(); // ?int
$policy->memoryLimit();       // string (e.g., "128M")
$policy->readablePaths();     // list<string>
$policy->writablePaths();     // list<string>
$policy->env();               // array<string, string>
$policy->inheritEnv();        // bool
$policy->networkEnabled();    // bool
$policy->stdoutLimitBytes();  // int
$policy->stderrLimitBytes();  // int
// @doctest id="ac6a"
```

These accessors are useful when building custom drivers or when your application logic needs to inspect the policy (for example, to display timeout values in a UI).
