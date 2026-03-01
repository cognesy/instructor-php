---
title: Overview
description: 'Sandboxed command execution with pluggable drivers and immutable policy.'
---

`packages/sandbox` runs commands with controlled limits and isolation.

Core pieces:

- `Sandbox`: entry point and driver selection
- `ExecutionPolicy`: immutable runtime policy (`with*()` returns new instance)
- `CanExecuteCommand`: common contract for all drivers
- `ExecResult`: normalized execution output and status

Supported drivers:

- `host`
- `docker`
- `podman`
- `firejail`
- `bubblewrap`

## Docs

- [Getting Started](2-getting-started.md)
- [Execution Policy](3-execution-policy.md)
- [Drivers](4-drivers.md)
- [Streaming and Results](5-streaming-and-results.md)
- [Testing](6-testing.md)
- [Troubleshooting](7-troubleshooting.md)
