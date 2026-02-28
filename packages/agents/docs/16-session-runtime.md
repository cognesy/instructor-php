---
title: 'Session Runtime'
docname: 'session_runtime'
order: 16
id: 'session-runtime'
---
## Overview

`SessionRuntime` is a thin application service for persisted agent sessions.

Its contract is intentionally small:
- load session
- apply action (`executeOn(AgentSession): AgentSession`)
- save session

It does not implement retries, fallbacks, queueing, or idempotency policy.
Those belong to adapters/infrastructure.

## Core Types

- `AgentSession` - immutable aggregate: session info + `AgentDefinition` + `AgentState`
- `SessionId` - typed identifier
- `SessionRepository` - repository over a session store
- `CanStoreSessions` - persistence contract
- `CanExecuteSessionAction` - action contract (`executeOn(AgentSession): AgentSession`)
- `CanRunSessionRuntime` - runtime contract

## Runtime Contract

```php
use Cognesy\Agents\Session\Contracts\CanManageAgentSessions;

interface CanRunSessionRuntime
{
    public function execute(SessionId $sessionId, CanExecuteSessionAction $action): AgentSession;
    public function getSession(SessionId $sessionId): AgentSession;
    public function getSessionInfo(SessionId $sessionId): AgentSessionInfo;
    public function listSessions(): SessionInfoList;
}
```

Read methods (`getSession`, `getSessionInfo`, `listSessions`) do not mutate persisted version.

## Store Contract and Versioning

Stores use optimistic version checks.

- `create(AgentSession): AgentSession` stores version `1`
- `save(AgentSession): AgentSession` requires incoming version to match stored version
- successful `save()` returns a reconstituted session with incremented version

Persistence errors are exceptions:
- `SessionNotFoundException`
- `SessionConflictException`
- `InvalidSessionFileException` (file store)

## Minimal Setup

```php
use Cognesy\Agents\Session\SessionFactory;
use Cognesy\Agents\Session\SessionRepository;
use Cognesy\Agents\Session\SessionRuntime;
use Cognesy\Agents\Session\Store\InMemorySessionStore;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;
use Cognesy\Events\Dispatchers\EventDispatcher;

$factory = new SessionFactory(new DefinitionStateFactory());
$repo = new SessionRepository(new InMemorySessionStore());
$runtime = new SessionRuntime($repo, new EventDispatcher('session-runtime'));
```

## Typical Flow

```php
use Cognesy\Agents\Session\Actions\SendMessage;use Cognesy\Agents\Template\Factory\DefinitionLoopFactory;

// 1) create and persist session
$session = $repo->create($factory->create($definition));

// 2) wake up and execute one action
$loopFactory = new DefinitionLoopFactory($capabilities, $tools, $events);
$updated = $runtime->execute($session->sessionId(), new SendMessage('Hello', $loopFactory));

// 3) use returned persisted version for next call
$nextVersion = $updated->version();
```

## Built-in Actions

Lifecycle:
- `ResumeSession`
- `SuspendSession`
- `ClearSession`

Configuration/state:
- `ChangeModel`
- `ChangeSystemPrompt`
- `WriteMetadata`
- `UpdateTask`

Execution/branching:
- `SendMessage`
- `ForkSession`

All actions are immutable and return `AgentSession`.

## Session Hooks

`SessionRuntime` can optionally intercept lifecycle stages via `CanControlAgentSession`.

Stages:
- `after_load`
- `after_action`
- `before_save`
- `after_save`

Use `SessionHookStack` to compose multiple interceptors with priority ordering:

```php
use Cognesy\Agents\Session\Contracts\CanControlAgentSession;
use Cognesy\Agents\Session\Data\AgentSession;
use Cognesy\Agents\Session\Enums\AgentSessionStage;
use Cognesy\Agents\Session\SessionHookStack;
use Cognesy\Agents\Session\SessionRuntime;

$hook = new class implements CanControlAgentSession {
    public function onStage(AgentSessionStage $stage, AgentSession $session): AgentSession {
        return match ($stage) {
            AgentSessionStage::BeforeSave => $session->suspended(),
            default => $session,
        };
    }
};

$hooks = SessionHookStack::empty()->with($hook, priority: 100);
$runtime = new SessionRuntime($repo, $events, $hooks);
```

## Session Lifecycle vs Execution Lifecycle

Session lifecycle is cross-run (`active`, `suspended`, `completed`, `failed`, `deleted`).

Execution lifecycle is per-run and resettable (`AgentState::forNextExecution()`).

`AgentSession::withState()` does not derive session status from execution status. Session lifecycle transitions are explicit session-level decisions.

## Events

`SessionRuntime` emits events for observability:
- `SessionLoaded`
- `SessionActionExecuted`
- `SessionSaved`
- `SessionLoadFailed`
- `SessionSaveFailed`

Use `wiretap()` on the injected event handler to record session runtime behavior.

## Concurrency and Ordering

`SessionRuntime` provides deterministic conflict signaling, not conflict resolution.

- If two workers update the same session concurrently, one save may throw `SessionConflictException`.
- Runtime does not retry automatically.
- Caller/adapter owns retry policy and ordering strategy (for example, per-session queueing in external infrastructure).

## Related

- [AgentBuilder & Capabilities](13-agent-builder.md)
- [Agent Templates](14-agent-templates.md)
