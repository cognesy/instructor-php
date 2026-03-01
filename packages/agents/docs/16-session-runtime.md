---
title: 'Session Runtime'
docname: 'session_runtime'
order: 16
id: 'session-runtime'
---

## Overview

`SessionRuntime` manages persisted agent sessions.
It loads a session, applies an action, saves, and returns the persisted result.

Use it when you need multi-request workflows, resumable conversations, or cross-process execution.

## Core Types

- `AgentSession` - aggregate: session header + `AgentDefinition` + `AgentState`
- `SessionId` - session identifier value object
- `SessionRepository` - repository over a store
- `CanStoreSessions` - persistence contract
- `CanExecuteSessionAction` - action contract
- `CanManageAgentSessions` - runtime contract

## Runtime Contract

```php
interface CanManageAgentSessions
{
    public function listSessions(): SessionInfoList;
    public function getSessionInfo(SessionId $sessionId): AgentSessionInfo;
    public function getSession(SessionId $sessionId): AgentSession;
    public function execute(SessionId $sessionId, CanExecuteSessionAction $action): AgentSession;
}
```

Read methods do not persist mutations.
`execute()` is the write path.

## Quick Start

```php
use Cognesy\Agents\Session\Actions\SendMessage;
use Cognesy\Agents\Session\SessionFactory;
use Cognesy\Agents\Session\SessionRepository;
use Cognesy\Agents\Session\SessionRuntime;
use Cognesy\Agents\Session\Store\InMemorySessionStore;
use Cognesy\Agents\Template\Factory\DefinitionLoopFactory;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;
use Cognesy\Events\Dispatchers\EventDispatcher;

$factory = new SessionFactory(new DefinitionStateFactory());
$repo = new SessionRepository(new InMemorySessionStore());
$events = new EventDispatcher('session-runtime');
$runtime = new SessionRuntime($repo, $events);

$session = $repo->create($factory->create($definition));

$loopFactory = new DefinitionLoopFactory($capabilities, $tools, $events);
$updated = $runtime->execute(
    $session->sessionId(),
    new SendMessage('Hello', $loopFactory),
);
```

## Session Management APIs

### Load one session

```php
$session = $runtime->getSession($sessionId);
$info = $runtime->getSessionInfo($sessionId);
```

### List all sessions

```php
$list = $runtime->listSessions();
foreach ($list->all() as $header) {
    echo $header->sessionId()->value . "\n";
}
```

### Execute an action

```php
$next = $runtime->execute($sessionId, $action);
```

Always use the returned session version for the next write.

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
- `ForkSession` (create a new branch session object)

## Management Recipes

### Suspend or resume a session

```php
use Cognesy\Agents\Session\Actions\ResumeSession;
use Cognesy\Agents\Session\Actions\SuspendSession;

$runtime->execute($sessionId, new SuspendSession());
$runtime->execute($sessionId, new ResumeSession());
```

### Clear conversation state

```php
use Cognesy\Agents\Session\Actions\ClearSession;

$runtime->execute($sessionId, new ClearSession());
```

### Fork to a new session branch

```php
use Cognesy\Agents\Session\Actions\ForkSession;

$source = $runtime->getSession($sessionId);
$forked = (new ForkSession())->executeOn($source);
$forked = $repo->create($forked);
```

`SessionRuntime::execute()` persists updates to the loaded session ID.
For a new branch ID, create the fork and persist it via repository `create()`.

### Update prompt or metadata

```php
use Cognesy\Agents\Session\Actions\ChangeSystemPrompt;
use Cognesy\Agents\Session\Actions\ChangeModel;
use Cognesy\Agents\Session\Actions\WriteMetadata;

$runtime->execute($sessionId, new ChangeSystemPrompt('You are concise and direct.'));
$runtime->execute($sessionId, new ChangeModel($llmConfig));
$runtime->execute($sessionId, new WriteMetadata('ticket_id', 'OPS-142'));
```

## Versioning and Conflicts

Stores use optimistic locking.

- `create()` requires version `0` and persists as version `1`
- `save()` requires incoming version == stored version
- persisted result is returned with incremented version

Errors:

- `SessionNotFoundException`
- `SessionConflictException`
- `InvalidSessionFileException` (file store)

## Session Lifecycle vs Execution Lifecycle

Session lifecycle (`AgentSession`): `active`, `suspended`, `completed`, `failed`, `deleted`.

Execution lifecycle (`AgentState`): one run at a time, resettable via `forNextExecution()`.

`AgentSession::withState()` does not derive session status from execution status.
Session status transitions are explicit.

## Session Hooks

Use `SessionHookStack` to intercept runtime stages:

- `AfterLoad`
- `AfterAction`
- `BeforeSave`
- `AfterSave`

```php
use Cognesy\Agents\Session\Contracts\CanControlAgentSession;
use Cognesy\Agents\Session\Data\AgentSession;
use Cognesy\Agents\Session\Enums\AgentSessionStage;
use Cognesy\Agents\Session\SessionHookStack;

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

## Events

`SessionRuntime` emits:

- `SessionLoaded`
- `SessionActionExecuted`
- `SessionSaved`
- `SessionLoadFailed`
- `SessionSaveFailed`

Attach listeners (or `wiretap()`) on the injected event handler to observe session activity.

## Related

- [Agent Templates](14-agent-templates.md)
- [Subagents](15-subagents.md)
- [Testing Agents](10-testing.md)
