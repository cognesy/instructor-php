---
title: Troubleshooting
description: 'Common setup and runtime issues.'
---

## Driver Binary Not Found

If Docker/Podman/Firejail/Bubblewrap binaries are missing, command startup fails.

Check:

- binary is installed
- binary is on `PATH`
- optional override env vars are set correctly (`DOCKER_BIN`, `PODMAN_BIN`, `FIREJAIL_BIN`, `BWRAP_BIN`)

## Invalid Driver Name

`Sandbox::with(...)->using('unknown')` throws `InvalidArgumentException`.

Use `SandboxDriver` enum when possible.

## Timeout or Truncated Output

If result is incomplete:

- increase `withTimeout(...)` / `withIdleTimeout(...)`
- increase `withOutputCaps(...)`
- inspect `timedOut()`, `truncatedStdout()`, `truncatedStderr()`

## File Access Issues

If command cannot read/write paths:

- set correct `baseDir`
- add required mounts via `withReadablePaths(...)` / `withWritablePaths(...)`
