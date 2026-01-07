# Agent App Integration: Data Model + Execution Flow

**Date**: 2026-01-06

This document defines the **data model** and the **integration model** for the new agent-based assistant experience, replacing `work_context_sessions` for assistant usage. It also clarifies the lifecycle, messaging flow, eventing, and snapshot storage.

---

## Goals

- Provide **session-based agent interaction** that resembles task threads (micro-mailboxes), not long chats.
- Separate **business-visible messages** from **technical execution telemetry**.
- Support **async agent execution**, **real-time updates**, and **state resumption**.
- Store **agent state snapshots as JSON** for inspection and debugging.
- Allow **context attachment** to domain objects without limiting agent scope.

---

## Core Concepts

- **Agent Session**: A user-visible task thread (often a single user request + agent updates + final response). Can be attached to a domain object.
- **Agent Session Messages**: The UI stream of user/assistant/info updates (foldable status messages, notifications, and final responses).
- **Agent Execution**: A single technical execution of an agent. A session can create many executions.
- **Agent State Snapshot**: JSON representation of `AgentState` at specific points in time.

---

## Data Model (Tables)

### 1) `agent_sessions`

Represents a user-visible thread for a task or discussion. It may map to a specific screen or domain object, but does not constrain agent actions.

**Key fields**
- `id` (uuid, pk)
- `user_id` (fk)
- `related_type`, `related_id` (nullable morph) — domain object context
- `initial_context_url` (nullable string) — URL where session was started
- `title` (nullable string)
- `status` (enum: `active`, `archived`, `closed`)
- `metadata` (json)
- `last_execution_id` (nullable uuid, fk to `agent_executions`)
- `last_snapshot_id` (nullable uuid, fk to `agent_state_snapshots`)
- timestamps

**Notes**
- `related_*` and `initial_context_url` are **informational only**.
- `last_*` fields provide quick access to current execution/snapshot.

---

### 2) `agent_session_messages`

User-facing message stream for a session. Contains user requests, assistant replies, and info/notification updates.

**Key fields**
- `id` (uuid, pk)
- `session_id` (uuid, fk)
- `role` (enum: `user`, `assistant`, `info`, `notification`)
- `content` (text)
- `agent_execution_id` (nullable uuid, fk)
- `related_type`, `related_id` (nullable morph) — captured per user message
- `context_url` (nullable string) — captured per user message
- `metadata` (json)
- `created_at` (datetime(6))
- `sequence` (optional integer) — for strict ordering during concurrent updates

**Notes**
- **Info/notification** messages are foldable/hidden in UI.
- If a message comes from the agent, it **should** link to `agent_execution_id`.
- Per-message `related_*` and `context_url` allow follow-up requests from different screens.

---

### 3) `agent_executions`

Stores the technical execution lifecycle. One record per execution. Usually references a parent session.

**Key fields**
- `id` (uuid, pk)
- `session_id` (nullable uuid, fk)
- `user_id` (nullable fk)
- `agent_type` (string)
- `agent_config` (json)
- `status` (enum: `pending`, `running`, `paused`, `completed`, `failed`, `cancelled`)
- `input` (json)
- `output` (json)
- `last_snapshot_id` (nullable uuid, fk to `agent_state_snapshots`)
- `step_count` (int)
- `token_usage` (int or json, see below)
- `metadata` (json)
- `error_message` (nullable text)
- `started_at`, `completed_at` (timestamps)
- timestamps

**Notes**
- `token_usage`: keep as int (total) **or** migrate to `{input, output, total}` if required.
- `session_id` is nullable to allow special/background runs.

---

### 4) `agent_state_snapshots`

Stores JSON snapshots of `AgentState`. Supports debug mode, step-level snapshots, and subagents.

**Key fields**
- `id` (uuid, pk)
- `agent_execution_id` (uuid, fk)
- `parent_snapshot_id` (nullable uuid, fk) — for subagent lineage
- `agent_id` (nullable string) — for subagents if needed
- `agent_name` (nullable string)
- `step_number` (nullable int)
- `is_final` (bool)
- `debug_mode` (bool)
- `state_json` (json)
- `created_at` (datetime(6))

**Notes**
- Use `AgentState::toArray()` and `AgentState::fromArray()`.
- `parent_snapshot_id` supports subagent chains without mixing states.

---

## Integration Model

### Where does context come from?

**Recommendation**: capture from both frontend and backend.

- **Frontend** sends: `related_type`, `related_id`, `context_url` when user submits a message.
- **Backend** validates/normalizes:
  - resolve `related_type` to allowed models
  - confirm the user is authorized for `related_id`
  - rewrite `context_url` if needed (canonical route)

This provides accurate UI context while keeping server-side guarantees.

---

## Interaction / Execution Flow

### 1) Sidebar assistant + session browser

- Right sidebar shows the **current `agent_session`**.
- **New Session** button creates a fresh session and clears the UI stream.
- **History** opens the Assistants module (session browser).

### 2) User sends first message (no active session)

**Expected behavior**
- Frontend sends message + context (`related_*`, `context_url`, `initial_context_url`).
- Backend creates:
  - `agent_sessions` (new)
  - `agent_session_messages` (role=user)
  - `agent_executions` (status=pending, queued)
- Backend returns `session_id` and immediate UI message.

### 3) “Execution queued” info message

- As part of the same API request (blocking), backend creates:
  - `agent_session_messages` (role=info, content="Execution queued")

### 4) Worker picks up job

- Worker sets execution to `running`.
- A **new info message** is added:
  - `agent_session_messages` (role=info, content="Execution started")
- **Broadcast** via Laravel Echo to all subscribed clients.

### 5) Step/tool updates

- For each agent step or tool call:
  - A message is added (`role=info` or `notification`).
  - Associated with `agent_execution_id`.
  - Optional snapshot written to `agent_state_snapshots` (debug mode or step checkpoint).
- Each update is broadcast in real time.

### 6) Final response

- When execution completes:
  - Add `agent_session_messages` (role=assistant, content=final response).
  - Update `agent_executions.output` and `completed_at`.
  - Persist final snapshot (`is_final = true`).
  - Broadcast completion.

### 7) User input tool

- If agent invokes `user_input` tool:
  - Create `agent_session_messages` (role=assistant or notification) requesting input.
  - Optionally set session/execution status to `paused`.
  - Frontend prompts user for input.

---

## Event & Broadcast Model

### Suggested event types (internal)

- `execution.queued`
- `execution.started`
- `step.started`
- `tool.called`
- `tool.completed`
- `step.completed`
- `execution.paused`
- `execution.completed`
- `execution.failed`
- `execution.cancelled`

### Broadcast payload (minimum)

```json
{
  "session_id": "uuid",
  "message_id": "uuid",
  "execution_id": "uuid",
  "event_type": "step.completed",
  "content": "Completed step 1 of 3",
  "metadata": {
    "step_number": 1,
    "tool": "read_file"
  },
  "timestamp": "2026-01-06T12:34:56.123456Z"
}
```

### Channel naming

- `private-agent.session.{sessionId}`

---

## API Responsibilities

### Session creation
- `POST /agent/sessions` → create session manually (optional).
- `POST /agent/sessions/{id}/messages` → send message (creates execution if needed).

### Execution lifecycle
- Queue job: `ExecuteAgentJob`
- Service: `AgentExecutionService`

### Message flow
- All UI messages live in `agent_session_messages` and are broadcast on creation.

---

## Snapshot Strategy

### When to store snapshots

- **Always** store final snapshot (`is_final = true`).
- **Optional**: store per-step snapshots for debug mode.
- **Optional**: store snapshots on `pause` to allow resume.

### Subagents

- Each subagent can write a snapshot with `parent_snapshot_id` pointing to the parent.
- If subagent has its own execution, set `agent_execution_id` to its run and optionally link to parent via metadata.

---

## Migration Notes (from WorkContext)

- `work_context_sessions` is removed for assistant usage.
- Replace assistant history UI queries with `agent_sessions` + `agent_session_messages`.
- If WorkContext is still used elsewhere, keep those features isolated.

---

## Decisions

1) We should keep `token_usage` integer total + add JSON breakdown for details.
2) We want to rely on exact server timing for ordering messages, not sequence numbers.
3) Subagents should be recorded as separate agent executions.
