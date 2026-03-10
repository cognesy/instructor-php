---
title: Getting Started
description: 'Install Agent-Ctrl, send your first prompt to a CLI-based code agent, and learn the basic configuration options available across all bridges.'
---

## Requirements

- PHP 8.2 or later
- At least one supported CLI-based code agent installed and authenticated:
  - **Claude Code** -- the `claude` binary ([Anthropic CLI](https://docs.anthropic.com/en/docs/claude-code))
  - **OpenAI Codex** -- the `codex` binary (`npm install -g @openai/codex`)
  - **OpenCode** -- the `opencode` binary (`curl -fsSL https://get.opencode.dev | bash`)

Each CLI must be available in the system `PATH` visible to your PHP process. Run the binary interactively at least once to complete any first-run authentication flows before using it through Agent-Ctrl.

## Installation

Install the package via Composer:

```bash
composer require cognesy/agent-ctrl
```

Agent-Ctrl is part of the Instructor-PHP monorepo. It depends on the `cognesy/sandbox` package for process execution and isolation, which is pulled in automatically.

## Your First Request

The simplest way to use Agent-Ctrl is to pick an agent, pass a prompt, and read the result:

```php
use Cognesy\AgentCtrl\AgentCtrl;

$response = AgentCtrl::codex()->execute('Summarize this repository.');

echo $response->text();
```

The `execute()` method runs the agent synchronously, waits for it to finish, and returns an `AgentResponse` object containing the text output, exit code, session ID, and any tool calls the agent made.

## Choosing a Different Agent

Each supported agent has its own factory method on the `AgentCtrl` facade:

```php
use Cognesy\AgentCtrl\AgentCtrl;

// Use Claude Code
$response = AgentCtrl::claudeCode()
    ->execute('List the main packages in this monorepo.');

// Use OpenCode
$response = AgentCtrl::openCode()
    ->execute('Explain the package layout.');
```

All three factory methods return a builder that supports the same core API (`withModel()`, `withTimeout()`, `inDirectory()`, `execute()`, `executeStreaming()`, etc.), so you can switch agents without restructuring your code.

## Selecting the Agent at Runtime

When the agent type is determined by configuration or user input rather than being hard-coded, use the `AgentType` enum with `AgentCtrl::make()`:

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Enum\AgentType;

// AgentType is a backed enum: 'claude-code', 'codex', 'opencode'
$agent = AgentType::from($config['agent']);

$response = AgentCtrl::make($agent)
    ->withModel($config['model'] ?? null)
    ->execute('Explain the package layout.');
```

The `AgentType` enum has three cases:

| Case | Value | Builder |
|------|-------|---------|
| `AgentType::ClaudeCode` | `'claude-code'` | `ClaudeCodeBridgeBuilder` |
| `AgentType::Codex` | `'codex'` | `CodexBridgeBuilder` |
| `AgentType::OpenCode` | `'opencode'` | `OpenCodeBridgeBuilder` |

## Common Configuration

Every builder -- regardless of the agent type -- supports the same set of core configuration methods. These methods are defined in the `AgentBridgeBuilder` interface and implemented by the `AbstractBridgeBuilder` base class.

### Model Selection

Specify which model the agent should use. The format depends on the agent: Claude Code uses Anthropic model names, Codex uses OpenAI model names, and OpenCode uses provider-prefixed IDs:

```php
// Claude Code
AgentCtrl::claudeCode()->withModel('claude-sonnet-4-5');

// Codex
AgentCtrl::codex()->withModel('o4-mini');

// OpenCode (provider/model format)
AgentCtrl::openCode()->withModel('anthropic/claude-sonnet-4-5');
```

### Execution Timeout

Set the maximum time (in seconds) the agent is allowed to run. The default is 120 seconds. If the timeout is exceeded, the process is killed and the response will have a non-zero exit code:

```php
$response = AgentCtrl::claudeCode()
    ->withTimeout(600) // 10 minutes
    ->execute('Perform a comprehensive codebase review.');
```

The minimum accepted timeout is 1 second. Values less than 1 are clamped to 1.

### Working Directory

Set the directory the agent should operate in. The bridge validates that the directory exists before changing into it and restores the original working directory after execution:

```php
$response = AgentCtrl::codex()
    ->inDirectory('/projects/my-app')
    ->execute('Review the current directory.');
```

> **Note:** Always use absolute paths. The bridge changes the PHP process's current working directory for the duration of the execution. If your PHP process handles concurrent requests (e.g., Swoole or RoadRunner), be aware that this affects the entire process.

### Sandbox Driver

By default, Agent-Ctrl runs the CLI binary directly on the host. You can switch to a containerized sandbox driver for additional process isolation:

```php
use Cognesy\Sandbox\Enums\SandboxDriver;

$response = AgentCtrl::claudeCode()
    ->withSandboxDriver(SandboxDriver::Docker)
    ->execute('Analyze this codebase.');
```

Available sandbox drivers:

| Driver | Description |
|--------|-------------|
| `SandboxDriver::Host` | Run directly on the host (default) |
| `SandboxDriver::Docker` | Run inside a Docker container |
| `SandboxDriver::Podman` | Run inside a Podman container |
| `SandboxDriver::Firejail` | Run inside a Firejail sandbox (Linux) |
| `SandboxDriver::Bubblewrap` | Run inside a Bubblewrap sandbox (Linux) |

When using a containerized driver (Docker or Podman), the binary preflight check is skipped -- the binary is expected to be available inside the container image.

## Checking the Result

The `execute()` method returns an `AgentResponse`. Always check `isSuccess()` before using the text output, because a completed execution with a non-zero exit code does not throw an exception:

```php
$response = AgentCtrl::codex()
    ->withTimeout(300)
    ->inDirectory(__DIR__)
    ->execute('Review the current directory.');

if ($response->isSuccess()) {
    echo $response->text();
} else {
    echo "Agent failed with exit code: {$response->exitCode}";
}
```

See the [Response Object](/agent-ctrl/response-object) documentation for the full set of properties and methods available on `AgentResponse`.

## Next Steps

- [Streaming](/agent-ctrl/streaming) -- Receive real-time updates while the agent is working
- [Session Management](/agent-ctrl/session-management) -- Continue or resume agent sessions
- [Agent Options](/agent-ctrl/agent-options) -- Explore shared and agent-specific configuration
- [Claude Code Bridge](/agent-ctrl/claude-code-bridge), [Codex Bridge](/agent-ctrl/codex-bridge), [OpenCode Bridge](/agent-ctrl/opencode-bridge) -- Deep dives into each agent's unique capabilities
