---
title: 'Session Runtime'
docname: 'session_runtime'
order: 16
id: 'session-runtime'
---
## Overview

`SessionRuntime` is a thin application service for session actions.

Flow is explicit and deterministic:
- load session from repository
- execute action via `executeOn(AgentSession)`
- save persisted session

It does not implement retries, fallbacks, queueing, or idempotency policies.
Those concerns belong to adapters/infrastructure.

## Core Contracts

- `CanExecuteSessionAction`: `executeOn(AgentSession): AgentSession`
- `CanRunSessionRuntime`:
  - `execute(SessionId, CanExecuteSessionAction): AgentSession`
  - `getSession(SessionId): AgentSession`
  - `getSessionInfo(SessionId): AgentSessionInfo`
  - `listSessions(): SessionInfoList`

## Error Model

Session persistence uses exceptions for errors:
- `SessionNotFoundException`
- `SessionConflictException`
- `InvalidSessionFileException`

Store API returns persisted session instances:
- `create(AgentSession): AgentSession`
- `save(AgentSession): AgentSession`

## Built-in Session Actions

Initial actions include:
- `ResumeSession`, `SuspendSession`, `ClearSession`
- `ChangeModel`, `ChangeBudget`, `ChangeSystemPrompt`
- `WriteMetadata`, `UpdateTask`
- `SendMessage`, `ForkSession`

All actions are immutable and return `AgentSession`.
