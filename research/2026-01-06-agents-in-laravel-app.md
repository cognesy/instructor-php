# Agents in Laravel: Application Work

Goal: the minimum a Laravel app must build to run agents using this library.

---

## 0) Install / Update Library

This project ships as a **single package**: `cognesy/instructor-php` (all-in-one).

Install:
```
composer require cognesy/instructor-php
```

Update:
```
composer update cognesy/instructor-php
```

Optional Laravel helpers:
```
composer require cognesy/instructor-laravel
```

### Common Namespaces (Current)

Use these namespaces in your Laravel app:

```
Cognesy\Addons\AgentBuilder\AgentBuilder
Cognesy\Addons\Agent\Core\Data\AgentState
Cognesy\Addons\Agent\Contracts\AgentContract
Cognesy\Addons\AgentBuilder\Contracts\AgentFactory
Cognesy\Addons\AgentBuilder\Support\AbstractAgent
Cognesy\Addons\Agent\Core\Data\AgentDescriptor
Cognesy\Addons\Agent\Core\Collections\NameList
Cognesy\Events\EventBus
```

---

## 1) Database Schema

Create the minimal tables:

- `agent_executions`
  - id (uuid), user_id
  - agent_type, status
  - input, output (json)
  - state_snapshot (json)
  - step_count, token_usage
  - metadata
  - timestamps

Optional:
- `agent_signals` (pause/cancel/resume/input)
- `agent_logs` (structured step/tool logs)

---

## 2) Eloquent Models

Create models for:

- `AgentExecution`
- Optional: `AgentSignal`, `AgentLog`

Add JSON casts for input/output/state_snapshot/metadata.

---

## 3) Queue Job

`ExecuteAgentJob` (ShouldQueue):

- Load `AgentExecution`
- Skip if cancelled
- Call `AgentExecutionService`
- Mark failed on exception
- `tries = 1`

---

## 4) AgentExecutionService

Minimal responsibilities:

- Resolve agent via an `AgentFactory` implementation (deterministic, no serialization)
- Initialize `AgentState` (new or from snapshot)
- Run iterator loop
- Update step_count, token_usage
- Persist output on completion
- Handle pause/cancel signals (if enabled)

---

## 5) Agent Definitions (Per Type)

Define deterministic agent classes that implement `AgentContract` and register them:

- `code-assistant`: UseBash + UseFileTools + UseTaskPlanning
- `research`: UseFileTools only

Keep tools minimal per agent to reduce context.

Minimal registry flow:

- Job payload includes `agent_name` + `agent_config` (array)
- Worker resolves agent via an `AgentFactory` implementation
- `AgentContract->build()` or `->iterator()` executes the run
- Optional: attach logging / observability via `wiretap()` / `onEvent()`

---

## 6) API Endpoints (Minimal)

If you need a thin HTTP layer:

- `POST /agents/start`
- `POST /agents/{id}/pause`
- `POST /agents/{id}/resume`
- `POST /agents/{id}/cancel`
- `GET /agents/{id}`

---

## 7) Worker Setup

Run a dedicated queue:

- `queue:work --queue=agents`
- In production use Horizon

---

## App Checklist

- [ ] Migrations for `agent_executions` (+ optional logs/signals)
- [ ] Models with JSON casts
- [ ] ExecuteAgentJob
- [ ] AgentExecutionService
- [ ] AgentContract implementations + registry setup
- [ ] Queue worker running
