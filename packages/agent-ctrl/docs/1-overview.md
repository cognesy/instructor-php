---
title: Overview
description: 'Run Claude Code, Codex, or OpenCode through one fluent API.'
---

`agent-ctrl` gives you one entry point for CLI-based code agents:

- `AgentCtrl::claudeCode()`
- `AgentCtrl::codex()`
- `AgentCtrl::openCode()`
- `AgentCtrl::make(AgentType)`

Each bridge uses the same execution flow:

1. Configure the builder.
2. Call `execute()` or `executeStreaming()`.
3. Read the normalized `AgentResponse`.

## Core Capabilities

- Switch between supported agents without changing your application flow
- Stream text and tool activity while the CLI is running
- Continue or resume agent sessions
- Configure model, timeout, working directory, and sandbox behavior
- Read one normalized response shape across all providers

## Supported Agents

### Claude Code

Good default for general coding workflows and tool-heavy tasks.

### Codex

Good when you want Codex-specific sandbox controls and image input support.

### OpenCode

Good when you want model flexibility and OpenCode session sharing features.

## Docs

- [Getting Started](2-getting-started.md)
- [Streaming](3-streaming.md)
- [Session Management](4-session-management.md)
- [Agent Options](5-agent-options.md)
- [Response Object](6-response-object.md)
- [Troubleshooting](7-troubleshooting.md)
- [Claude Code](8-claude-code-bridge.md)
- [Codex](9-codex-bridge.md)
- [OpenCode](10-opencode-bridge.md)
