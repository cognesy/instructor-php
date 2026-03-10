---
title: Troubleshooting
description: 'Diagnose and resolve common setup, runtime, and configuration issues with the Sandbox package.'
---

## Introduction

This page covers the most common issues you may encounter when using the Sandbox package. Each entry describes the symptom, explains the root cause, and provides concrete solutions.

## Driver Binary Not Found

**Symptom:** A `RuntimeException` with the message "Failed to start docker" (or podman, firejail, bwrap) is thrown when calling `execute()`.

**Cause:** The driver cannot locate the required binary on the system. The `ProcRunner` wraps the `proc_open` failure and reports it with the driver name.

**Solutions:**

1. Verify the binary is installed and executable:
   ```bash
   which docker    # or podman, firejail, bwrap
   ```

2. Check what PHP sees as `PATH`. In web server or systemd contexts, the `PATH` is often more restrictive than your shell's:
   ```php
   echo getenv('PATH');
   ```

3. Set the binary path explicitly through an environment variable before your PHP process starts:
   ```bash
   export DOCKER_BIN=/usr/local/bin/docker
   export PODMAN_BIN=/usr/bin/podman
   export FIREJAIL_BIN=/usr/bin/firejail
   export BWRAP_BIN=/usr/bin/bwrap
   ```

4. Pass the binary path directly to the static factory:
   ```php
   $sandbox = Sandbox::docker($policy, dockerBin: '/usr/local/bin/docker');
   $sandbox = Sandbox::podman($policy, podmanBin: '/usr/bin/podman');
   $sandbox = Sandbox::firejail($policy, firejailBin: '/usr/bin/firejail');
   $sandbox = Sandbox::bubblewrap($policy, bubblewrapBin: '/usr/bin/bwrap');
   ```

The package searches the following directories in addition to `PATH`: `/usr/bin`, `/usr/local/bin`, `/opt/homebrew/bin`, `/opt/local/bin`, and `/snap/bin`. On Windows, `.exe` extensions are tried automatically.

## Invalid Driver Name

**Symptom:** An `InvalidArgumentException` is thrown listing the valid driver names.

**Cause:** A string passed to `Sandbox::fromPolicy($policy)->using()` does not match any known driver value.

**Solution:** Use the `SandboxDriver` enum to avoid typos:

```php
use Cognesy\Sandbox\Enums\SandboxDriver;

$sandbox = Sandbox::fromPolicy($policy)->using(SandboxDriver::Docker);
```

The valid string values are: `host`, `docker`, `podman`, `firejail`, `bubblewrap`. These match the `SandboxDriver` enum's backing values exactly.

## Command Times Out

**Symptom:** The `ExecResult` has `timedOut()` returning `true` and `exitCode()` returning `124`. The output may be incomplete.

**Cause:** The command exceeded either the wall-clock timeout or the idle timeout specified in the execution policy. The `TimeoutTracker` monitors both independently and terminates the process as soon as either limit is reached.

**Solutions:**

1. Increase the wall-clock timeout:
   ```php
   $policy = $policy->withTimeout(60); // 60 seconds
   ```

2. If the process produces output in bursts with long pauses, increase or disable the idle timeout:
   ```php
   $policy = $policy->withIdleTimeout(30);  // 30 seconds of no output
   $policy = $policy->withIdleTimeout(null); // disable idle timeout entirely
   ```

3. Use the streaming callback to monitor progress and identify where the command stalls:
   ```php
   $result = $sandbox->execute($argv, null, function (string $type, string $chunk) {
       echo "[" . date('H:i:s') . "] {$type}: {$chunk}";
   });
   ```

4. For container drivers, keep in mind that the timeout includes container startup time. If image pulling is needed on the first run, it may consume a significant portion of the budget. Pre-pull images to avoid this.

**Note:** The package sends `SIGTERM` first and waits briefly, then escalates to `SIGKILL` if the process does not exit. For container drivers, this terminates the entire process group (via `setsid`) to ensure no orphan processes remain.

## Truncated Output

**Symptom:** Output appears incomplete, and `truncatedStdout()` or `truncatedStderr()` returns `true`.

**Cause:** The command produced more output than the policy's output caps allow. The `StreamAggregator` retains only the most recent bytes up to the cap, discarding earlier content. This tail-preserving strategy ensures error messages and final status information are always captured.

**Solution:** Increase the output caps in the policy:

```php
$policy = $policy->withOutputCaps(
    stdoutBytes: 10 * 1024 * 1024, // 10 MB
    stderrBytes: 2 * 1024 * 1024,  // 2 MB
);
```

The default cap is 1 MB (1,048,576 bytes) for each stream. The minimum is 1024 bytes -- values below this are clamped upward.

If you need the complete output but want to keep the policy cap low for memory safety, use a streaming callback to write output to a file:

```php
$logFile = fopen('/tmp/full-output.log', 'w');

$result = $sandbox->execute($argv, null, function (string $type, string $chunk) use ($logFile) {
    fwrite($logFile, $chunk);
});

fclose($logFile);
// $result->stdout() may be truncated, but /tmp/full-output.log has everything
```

## Working Directory Errors

**Symptom:** A `RuntimeException` with the message "Base directory is invalid or not writable" is thrown.

**Cause:** The `baseDir` specified in the policy does not exist, is not a directory, or is not writable by the PHP process. The `Workdir::create()` method validates this before attempting to create a temporary subdirectory.

**Solutions:**

1. Verify the directory exists and has correct permissions:
   ```bash
   ls -ld /path/to/base/dir
   ```

2. Create the directory if it does not exist:
   ```bash
   mkdir -p /path/to/base/dir
   chmod 755 /path/to/base/dir
   ```

3. Use a known-writable location:
   ```php
   $policy = ExecutionPolicy::in('/tmp');
   ```

For container drivers (Docker, Podman, Firejail, Bubblewrap), a unique temporary subdirectory is created inside the base directory for each execution using cryptographically random names (24 hex characters). The directory is set to mode `0700` and cleaned up in a `finally` block, ensuring removal even when the command fails or throws an exception.

## File Access Denied in Sandbox

**Symptom:** The sandboxed command cannot read or write files at expected paths.

**Cause:** Container and sandbox drivers restrict file-system access by default. Only the working directory and explicitly mounted paths are accessible.

**Solutions:**

1. For files the command needs to read, add them as readable paths:
   ```php
   $policy = $policy->withReadablePaths('/data/input', '/etc/config');
   ```

2. For files the command needs to write, add them as writable paths:
   ```php
   $policy = $policy->withWritablePaths('/data/output');
   ```

3. Remember that `withReadablePaths()` and `withWritablePaths()` **replace** the current list. Pass all paths in a single call:
   ```php
   // Correct
   $policy = $policy->withReadablePaths('/data/a', '/data/b');

   // Wrong -- only '/data/b' is mounted
   $policy = $policy->withReadablePaths('/data/a');
   $policy = $policy->withReadablePaths('/data/b');
   ```

4. Paths containing symlinks, `..` components, or colons are silently skipped for security. Use `realpath()` to resolve paths before passing them to the policy:
   ```php
   $resolved = realpath('/data/symlinked-dir');
   $policy = $policy->withReadablePaths($resolved);
   ```

5. For Docker and Podman, paths are mounted at `/mnt/ro0`, `/mnt/ro1`, ... (readable) and `/mnt/rw0`, `/mnt/rw1`, ... (writable). Your command must reference these container paths, not the host paths. For Bubblewrap, paths are mounted at their original host locations.

## Docker / Podman Permission Errors

**Symptom:** The command fails with "permission denied" errors inside the container.

**Cause:** Container drivers run commands as the `nobody` user (UID 65534, GID 65534) with a read-only root filesystem and all capabilities dropped. This prevents writing to most locations inside the container.

**Solutions:**

1. The working directory (`/work`) is mounted as writable. Write output files there.

2. A writable tmpfs is mounted at `/tmp` inside the container (64 MB, with `noexec`, `nodev`, `nosuid` flags). Use it for temporary files -- but note that executables cannot be run from `/tmp` due to `noexec`.

3. For additional writable locations, add them through `withWritablePaths()` in the policy.

4. If the container image requires root to set up (e.g., installing packages), build a custom image with a Dockerfile that performs setup as root and then switches to user 65534.

## Podman on WSL2

**Symptom:** Podman commands fail with cgroup-related errors under WSL2.

**Cause:** WSL2's default cgroup configuration is not fully compatible with Podman's expectations for resource limits.

**What the driver does automatically:**

The `PodmanSandbox` detects WSL2 environments by checking `/proc/version` for "WSL2" or "microsoft" strings, and by checking `/proc/self/cgroup` for the root cgroup indicator. When WSL2 is detected:

- The `--cgroup-manager=cgroupfs` flag is added as a global Podman flag.
- Memory (`--memory`) and CPU (`--cpus`) resource limits are skipped entirely.

All other security hardening (read-only root, dropped capabilities, nobody user, pids limit, etc.) remains fully active.

If you still encounter issues, verify that:
- Your WSL2 distribution has cgroup v2 mounted.
- Podman is configured for rootless operation.
- The Podman binary is accessible (check with `PODMAN_BIN` or `which podman`).

## Network Connectivity Issues

**Symptom:** The sandboxed command cannot reach external services (DNS resolution fails, connections time out).

**Cause:** Network access is disabled by default in the execution policy.

**Solution:** Enable network access explicitly:

```php
$policy = $policy->withNetwork(true);
```

**How network isolation is implemented per driver:**

| Driver | Mechanism | Notes |
|---|---|---|
| Host | None | `withNetwork()` is a policy declaration only |
| Docker | `--network=none` | Full network stack isolation |
| Podman | `--network=none` | Full network stack isolation |
| Firejail | `--net=none` | Linux network namespace |
| Bubblewrap | `--unshare-net` | Linux network namespace |

For the host driver, there is no OS-level network enforcement. If you need actual network isolation on the host, use a container or sandbox driver.

## Environment Variables Not Available

**Symptom:** The sandboxed command does not see expected environment variables.

**Cause:** By default, the host environment is **not** inherited. Additionally, security-sensitive variables are always stripped by `EnvUtils`, even when inheritance is enabled.

**Solutions:**

1. Pass specific variables explicitly:
   ```php
   $policy = $policy->withEnv(['APP_ENV' => 'production', 'DB_HOST' => 'localhost']);
   ```

2. Enable environment inheritance with your overrides on top:
   ```php
   $policy = $policy->withEnv(['APP_ENV' => 'test'], inherit: true);
   ```

3. Be aware that the following variable patterns are **always blocked** and cannot be overridden:

   | Category | Patterns |
   |---|---|
   | Dynamic linker | `LD_PRELOAD`, `LD_LIBRARY_PATH`, `LD_AUDIT` |
   | macOS linker | `DYLD_INSERT_LIBRARIES`, `DYLD_LIBRARY_PATH`, `DYLD_FRAMEWORK_PATH` |
   | PHP config | `PHP_INI_SCAN_DIR`, `PHPRC` |
   | AWS credentials | `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_SESSION_TOKEN` |
   | Google Cloud | `GOOGLE_APPLICATION_CREDENTIALS`, `GCP_*` |
   | Azure | `AZURE_CLIENT_ID`, `AZURE_CLIENT_SECRET` |
   | Ruby | `GEM_HOME`, `GEM_PATH`, `RUBY*` |
   | Node.js | `NODE_OPTIONS`, `NPM_*` |
   | Python | `PYTHON*`, `PIP_*` |

   Pattern matching uses `fnmatch()`, so `AWS_*` matches any variable starting with `AWS_`.

## MockSandbox Throws "No Response" Error

**Symptom:** A `RuntimeException` with the message "MockSandbox has no response for command: ..." is thrown.

**Cause:** The command key (argv joined with spaces) does not match any registered response, and no default response was provided.

**Solutions:**

1. Verify the command key matches exactly. The key is formed by joining the argv array with spaces:
   ```php
   // ['php', '-r', 'echo 1;'] becomes 'php -r echo 1;'
   ```

2. Provide a default response for unmatched commands:
   ```php
   $sandbox = MockSandbox::fromResponses(
       responses: [...],
       defaultResponse: new ExecResult(stdout: '', stderr: '', exitCode: 0, duration: 0.0),
   );
   ```

3. If a command is called multiple times, ensure enough responses are enqueued. Each call consumes one response from the queue. Use `enqueue()` to add more:
   ```php
   $sandbox->enqueue('php -v', new ExecResult(
       stdout: 'PHP 8.3.0', stderr: '', exitCode: 0, duration: 0.01,
   ));
   ```

4. Check `$sandbox->commands()` after a test failure to see exactly which commands were called and in what order.

## Memory Limit Format Errors

**Symptom:** An `InvalidArgumentException` with the message "Invalid memory limit format" or "Unbounded memory limit (-1) is not allowed" is thrown when creating or modifying a policy.

**Cause:** The `withMemory()` method validates the format strictly. The value must be a positive integer optionally followed by `K`, `M`, or `G`. The value `-1` (commonly used in PHP to mean "unlimited") is explicitly rejected.

**Solutions:**

1. Use a valid format:
   ```php
   $policy = $policy->withMemory('256M');  // Valid
   $policy = $policy->withMemory('1G');    // Valid (clamped to 1024M)
   $policy = $policy->withMemory('512K');  // Valid
   ```

2. Avoid passing raw byte counts without a suffix. The value `134217728` (128 MB in bytes) would be interpreted as 134,217,728 bytes without a unit, which is valid but may not produce the result you expect. Prefer using `M` or `G` suffixes for clarity.

3. The maximum memory limit is clamped to 1 GB. Any value above this is silently reduced to `1024M`.

## Process Group Termination

**Symptom:** After a timeout, child processes spawned by the sandboxed command continue running.

**Cause:** The command spawned child processes that were not in the same process group.

**How the package handles this:** All container drivers use `setsid` (when available on the system) to run the command in a new session group. On timeout, `SIGTERM` is sent to the entire process group (`kill -15 -$PID`), followed by a brief wait and then `SIGKILL` (`kill -9 -$PID`) if the process is still running. The host driver relies on Symfony Process's built-in termination logic.

If orphan processes persist, ensure that:
- `setsid` is available on your system (check `/usr/bin/setsid` or `/bin/setsid`).
- The container driver is being used instead of the host driver for better isolation.
- Your command does not detach processes into separate sessions.
