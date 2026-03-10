---
title: Overview
description: 'Agent-Ctrl provides a unified, fluent PHP API for orchestrating CLI-based code agents such as Claude Code, OpenAI Codex, and OpenCode.'
---

## Introduction

Agent-Ctrl is a PHP package that gives your application a single entry point for running CLI-based code agents. Instead of writing separate integration code for each agent's command-line interface, JSON output format, and streaming protocol, you configure one fluent builder and receive one normalized response -- regardless of which agent performed the work.

The package ships as part of the Instructor-PHP monorepo and can be installed standalone via Composer:

```bash
composer require cognesy/agent-ctrl
```

## Entry Point

All interactions start with the `AgentCtrl` facade. It exposes dedicated factory methods for each supported agent, as well as a generic `make()` method that accepts an `AgentType` enum for runtime switching:

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Enum\AgentType;

// Dedicated factory methods
AgentCtrl::claudeCode();   // Returns ClaudeCodeBridgeBuilder
AgentCtrl::codex();        // Returns CodexBridgeBuilder
AgentCtrl::openCode();     // Returns OpenCodeBridgeBuilder

// Runtime selection via enum
AgentCtrl::make(AgentType::from('codex'));
```

Each factory method returns a bridge builder -- a fluent configuration object that lets you set the model, timeout, working directory, streaming callbacks, and agent-specific options before calling `execute()` or `executeStreaming()`.

## Execution Flow

Every agent interaction follows the same three-step lifecycle:

1. **Configure** -- Use the builder's fluent methods to set the model, working directory, timeout, sandbox driver, streaming callbacks, and any agent-specific options (system prompts, permission modes, sandbox modes, etc.).

2. **Execute** -- Call `execute()` for a blocking request that returns the final result, or `executeStreaming()` when you need real-time text and tool activity delivered through callbacks while the agent is still running.

3. **Read the response** -- Both execution methods return an `AgentResponse` object with a normalized shape: the agent's text output, exit code, session ID, token usage (when available), cost (when available), tool calls, parse diagnostics, and the raw bridge-specific response for advanced inspection.

```php
use Cognesy\AgentCtrl\AgentCtrl;

$response = AgentCtrl::claudeCode()
    ->withModel('claude-sonnet-4-5')
    ->withTimeout(300)
    ->inDirectory('/projects/my-app')
    ->execute('Summarize the architecture of this project.');

if ($response->isSuccess()) {
    echo $response->text();
}
```

## Core Capabilities

**Unified API across agents.** Switch between Claude Code, Codex, and OpenCode without changing your application's control flow or response handling. The same `execute()` call and `AgentResponse` shape work with every bridge.

**Real-time streaming.** Register `onText()`, `onToolUse()`, `onComplete()`, and `onError()` callbacks to receive incremental updates while the agent works. Streaming and final-result access are not mutually exclusive -- `executeStreaming()` returns the complete `AgentResponse` when the agent finishes.

**Session continuity.** Continue the most recent session or resume a specific session by ID. Session identifiers are extracted from each agent's native output format and normalized into `AgentSessionId` value objects.

**Configurable execution environment.** Set the working directory, execution timeout, and sandbox driver (Host, Docker, Podman, Firejail, or Bubblewrap). The sandbox integration runs through the Instructor-PHP `Sandbox` package, providing consistent process isolation across all agents.

**Normalized tool call tracking.** Every tool invocation -- whether it is a Claude Code tool use, a Codex command execution or file change, or an OpenCode tool call -- is normalized into a `ToolCall` DTO with a tool name, input parameters, optional output, call ID, and error flag.

**Observable execution pipeline.** The builder emits granular events at every stage of the execution lifecycle: request building, command spec creation, sandbox initialization, process start/completion, stream chunk processing, response parsing, and data extraction. Connect the built-in `AgentCtrlConsoleLogger` via `wiretap()` for color-coded console output during development.

**Binary preflight checks.** Before every execution, `CliBinaryGuard` verifies that the required CLI binary (`claude`, `codex`, or `opencode`) is available in the system `PATH`. If the binary is missing, a clear exception is thrown immediately -- before any prompt is sent.

## Supported Agents

### Claude Code

Anthropic's `claude` CLI. A strong default choice for general coding workflows, tool-heavy tasks, and scenarios where you want fine-grained control over the agent's system prompt and permission behavior. Supports system prompt replacement and appending, permission modes (default, plan, accept-edits, bypass), turn limits, additional directory access, session management, and verbose streaming output.

### OpenAI Codex

OpenAI's `codex` CLI. Best suited when you want Codex-specific sandbox controls (read-only, workspace-write, or full-access modes), full-auto or dangerous-bypass approval settings, image input support, and Codex thread-based session management. Returns token usage data when available.

### OpenCode

The `opencode` CLI. Best suited when you want flexible model selection using provider-prefixed model IDs (e.g., `anthropic/claude-sonnet-4-5`), named agent selection, file attachments, session sharing, and session titles. Returns both token usage and cost data when available.

## Documentation

- [Getting Started](/agent-ctrl/getting-started) -- Installation, first request, and basic configuration
- [Streaming](/agent-ctrl/streaming) -- Real-time text, tool activity, completion, and error callbacks
- [Session Management](/agent-ctrl/session-management) -- Continuing and resuming agent sessions
- [Agent Options](/agent-ctrl/agent-options) -- Shared and provider-specific builder configuration
- [Response Object](/agent-ctrl/response-object) -- Reading text, session, usage, cost, and tool data from AgentResponse
- [Troubleshooting](/agent-ctrl/troubleshooting) -- Diagnosing binary, directory, timeout, streaming, and parse issues
- [Claude Code Bridge](/agent-ctrl/claude-code-bridge) -- System prompts, permissions, turns, and Claude Code-specific features
- [Codex Bridge](/agent-ctrl/codex-bridge) -- Sandbox modes, auto-approval, images, and Codex-specific features
- [OpenCode Bridge](/agent-ctrl/opencode-bridge) -- Model flexibility, agents, files, sharing, and OpenCode-specific features
