---
title: 'Session Runtime'
docname: 'session_runtime'
order: 16
id: 'session-runtime'
---

## Introduction

Agents are stateless by default -- an `AgentLoop` takes an `AgentState`, runs to completion, and returns the updated state. There is no built-in persistence between requests. The `SessionRuntime` layer adds that persistence, turning an agent into a long-lived conversation that survives across HTTP requests, CLI invocations, or background jobs.

A session wraps an `AgentDefinition` (what the agent is) and an `AgentState` (what the agent has done) together with lifecycle metadata like status, version, and timestamps. The runtime manages loading, executing actions, and saving sessions through a transactional pipeline with optimistic locking and event emission.

This is the foundation for building multi-turn chat applications, resumable workflows, and any scenario where agent state must persist beyond a single process.

## Core Types

The session system is built around a small set of types, each with a focused responsibility:

| Type | Purpose |
|---|---|
| `AgentSession` | Combines session info, agent definition, and agent state into one persistent unit |
| `AgentSessionInfo` | Header data: session ID, agent name, status, version, timestamps, parent session ID |
| `SessionId` | Value object wrapping a UUID string. Use `SessionId::generate()` to create new IDs. |
| `SessionStatus` | Enum: `Active`, `Suspended`, `Completed`, `Failed`, `Deleted` |
| `SessionRepository` | Thin wrapper over a `CanStoreSessions` implementation |
| `SessionRuntime` | The main orchestrator: load, execute, save with hooks and events |
| `SessionFactory` | Creates fresh `AgentSession` instances from an `AgentDefinition` |

## The Runtime Contract

The `CanManageAgentSessions` interface defines the public API that `SessionRuntime` implements:

```php
interface CanManageAgentSessions
{
    public function listSessions(): SessionInfoList;
    public function getSessionInfo(SessionId $sessionId): AgentSessionInfo;
    public function getSession(SessionId $sessionId): AgentSession;
    public function execute(SessionId $sessionId, CanExecuteSessionAction $action): AgentSession;
}
```

The read methods (`listSessions`, `getSessionInfo`, `getSession`) load data but do not persist any changes. The `execute()` method is the write path -- it loads the session, runs an action, saves the result, and returns the updated session.

## Quick Start

The following example creates a session, sends a message, and retrieves the result:

```php
use Cognesy\Agents\Capability\AgentCapabilityRegistry;
use Cognesy\Agents\Capability\Bash\UseBash;
use Cognesy\Agents\Session\Actions\SendMessage;
use Cognesy\Agents\Session\SessionFactory;
use Cognesy\Agents\Session\SessionRepository;
use Cognesy\Agents\Session\SessionRuntime;
use Cognesy\Agents\Session\Store\InMemorySessionStore;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionLoopFactory;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;
use Cognesy\Events\Dispatchers\EventDispatcher;

// 1. Define the agent
$definition = new AgentDefinition(
    name: 'assistant',
    description: 'A helpful general assistant',
    systemPrompt: 'You are a helpful assistant. Be concise and accurate.',
);

// 2. Set up the infrastructure
$stateFactory = new DefinitionStateFactory();
$sessionFactory = new SessionFactory($stateFactory);
$repo = new SessionRepository(new InMemorySessionStore());
$events = new EventDispatcher('session-runtime');
$runtime = new SessionRuntime($repo, $events);

// 3. Create a session
$session = $repo->create($sessionFactory->create($definition));

// 4. Set up the loop factory
$capabilities = new AgentCapabilityRegistry();
$capabilities->register('use_bash', new UseBash());
$loopFactory = new DefinitionLoopFactory($capabilities, events: $events);

// 5. Send a message
$updated = $runtime->execute(
    $session->sessionId(),
    new SendMessage('What is 2 + 2?', $loopFactory),
);

// 6. The session now contains the agent's response
$state = $updated->state();
```

## The Execute Pipeline

When you call `$runtime->execute($sessionId, $action)`, the following pipeline runs:

1. **Load** -- The session is loaded from the repository. If not found, `SessionNotFoundException` is thrown.
2. **AfterLoad hook** -- The session controller's `onStage(AfterLoad, ...)` is called, allowing pre-processing.
3. **SessionLoaded event** -- Emitted for observability.
4. **Action execution** -- `$action->executeOn($session)` runs the action and returns the next session state.
5. **AfterAction hook** -- The session controller processes the post-action state.
6. **BeforeSave hook** -- Last chance to modify the session before persistence (e.g., auto-suspend).
7. **SessionActionExecuted event** -- Emitted with before/after status and version.
8. **Save** -- The session is saved with optimistic version checking.
9. **AfterSave hook** -- Post-persistence processing.
10. **SessionSaved event** -- Emitted to confirm successful persistence.

If loading fails, `SessionLoadFailed` is emitted. If saving fails (e.g., version conflict), `SessionSaveFailed` is emitted. In both cases, the original exception is rethrown after the event.

## Built-in Actions

Actions implement the `CanExecuteSessionAction` interface, which defines a single method:

```php
interface CanExecuteSessionAction
{
    public function executeOn(AgentSession $session): AgentSession;
}
```

Each action receives the current session and returns a new session with the desired changes applied.

### SendMessage

The primary action for agent interaction. Appends a user message to the session's state, instantiates an `AgentLoop` from the session's definition, runs the loop to completion, and stores the resulting state.

```php
use Cognesy\Agents\Session\Actions\SendMessage;

$runtime->execute($sessionId, new SendMessage(
    message: 'Explain how dependency injection works.',
    loopFactory: $loopFactory,
));
```

The `message` parameter accepts either a plain `string` or a `Message` object. The `loopFactory` must implement `CanInstantiateAgentLoop` -- typically a `DefinitionLoopFactory`.

### SuspendSession and ResumeSession

Pause and resume a session. Suspended sessions are preserved but not actively processing.

```php
use Cognesy\Agents\Session\Actions\SuspendSession;
use Cognesy\Agents\Session\Actions\ResumeSession;

// Pause the session
$runtime->execute($sessionId, new SuspendSession());

// Resume it later
$runtime->execute($sessionId, new ResumeSession());
```

`SuspendSession` sets the status to `Suspended`. `ResumeSession` sets it back to `Active`.

### ClearSession

Resets the session's agent state while preserving the session identity and definition. The state is prepared for the next execution via `forNextExecution()`.

```php
use Cognesy\Agents\Session\Actions\ClearSession;

$runtime->execute($sessionId, new ClearSession());
```

### ForkSession

Creates a new session that inherits the state and definition of the source session. The forked session gets a fresh `SessionId` and its parent is set to the source session's ID.

```php
use Cognesy\Agents\Session\Actions\ForkSession;

// Fork returns a new session (not persisted automatically)
$source = $runtime->getSession($sessionId);
$forked = (new ForkSession())->executeOn($source);
$forked = $repo->create($forked);

// The forked session has a parent reference
echo $forked->info()->parentId(); // original session ID
```

Note that `ForkSession` is typically used outside the runtime's `execute()` pipeline because it creates a new session rather than modifying the existing one.

### ChangeSystemPrompt

Updates the system prompt in the session's agent state.

```php
use Cognesy\Agents\Session\Actions\ChangeSystemPrompt;

$runtime->execute($sessionId, new ChangeSystemPrompt(
    'You are concise and direct. Respond in bullet points.'
));
```

### ChangeModel

Swaps the LLM configuration for future executions within the session.

```php
use Cognesy\Agents\Session\Actions\ChangeModel;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

$runtime->execute($sessionId, new ChangeModel(
    LLMConfig::fromArray(['driver' => 'openai', 'model' => 'gpt-4o'])
));
```

### WriteMetadata

Stores a key-value pair in the session's metadata. Useful for tracking external references, workflow state, or custom tags.

```php
use Cognesy\Agents\Session\Actions\WriteMetadata;

$runtime->execute($sessionId, new WriteMetadata('ticket_id', 'OPS-142'));
$runtime->execute($sessionId, new WriteMetadata('priority', 'high'));
```

### UpdateTask

Updates the task description associated with the session.

```php
use Cognesy\Agents\Session\Actions\UpdateTask;

$runtime->execute($sessionId, new UpdateTask('Refactor the authentication module'));
```

## Versioning and Optimistic Locking

Sessions use optimistic locking to prevent concurrent modifications from silently overwriting each other. Every session has a monotonically increasing version number.

### Version Lifecycle

- **Create** -- The session must have version `0`. It is persisted as version `1`.
- **Save** -- The incoming session's version must match the stored version. The persisted session is returned with version incremented by `1`.
- **Read** -- Loading a session returns it with the stored version, which must be used for the next write.

### Conflict Handling

If two processes load the same session (both see version `5`), the first to save succeeds and advances the version to `6`. The second process's save fails because it still has version `5`, which no longer matches the stored version `6`. This triggers a `SessionConflictException`.

```php
use Cognesy\Agents\Session\Exceptions\SessionConflictException;

try {
    $runtime->execute($sessionId, $action);
} catch (SessionConflictException $e) {
    // Reload and retry, or inform the user
    $fresh = $runtime->getSession($sessionId);
}
```

### Exception Types

| Exception | Condition |
|---|---|
| `SessionNotFoundException` | The session ID does not exist in the store |
| `SessionConflictException` | Version mismatch during save, or attempting to create an existing session |
| `InvalidSessionFileException` | File-based store encountered a corrupt or unreadable file |

## Persistence Stores

The session system ships with two `CanStoreSessions` implementations.

### InMemorySessionStore

Stores sessions in a PHP array. Useful for testing, prototyping, and single-process applications.

```php
use Cognesy\Agents\Session\Store\InMemorySessionStore;

$store = new InMemorySessionStore();
$repo = new SessionRepository($store);
```

Sessions are lost when the process ends. All version checks and conflict detection still work correctly.

### FileSessionStore

Stores each session as a JSON file on disk. Supports concurrent access through file locking (`flock`).

```php
use Cognesy\Agents\Session\Store\FileSessionStore;

$store = new FileSessionStore('/var/data/sessions');
$repo = new SessionRepository($store);
```

The store creates the directory if it does not exist. Each session is stored as `{session_id}.json` with atomic writes (write to `.tmp`, then rename). Lock files (`{session_id}.lock`) are used for mutual exclusion during create and save operations.

### Custom Stores

Implement the `CanStoreSessions` interface to integrate with any persistence backend:

```php
use Cognesy\Agents\Session\Contracts\CanStoreSessions;
use Cognesy\Agents\Session\Collections\SessionInfoList;
use Cognesy\Agents\Session\Data\AgentSession;
use Cognesy\Agents\Session\Data\SessionId;

class RedisSessionStore implements CanStoreSessions
{
    public function create(AgentSession $session): AgentSession { /* ... */ }
    public function save(AgentSession $session): AgentSession { /* ... */ }
    public function load(SessionId $sessionId): ?AgentSession { /* ... */ }
    public function exists(SessionId $sessionId): bool { /* ... */ }
    public function delete(SessionId $sessionId): void { /* ... */ }
    public function listHeaders(): SessionInfoList { /* ... */ }
}
```

Your implementation must enforce the version semantics: `create()` requires version `0`, and `save()` must match the stored version. Use `AgentSession::reconstitute()` to set the next version and timestamp before persisting.

## Session Lifecycle vs Execution Lifecycle

The session system has two distinct lifecycle models that operate independently.

### Session Lifecycle

The session lifecycle tracks the overall status of the agent conversation across multiple requests. Status transitions are explicit -- they only happen when an action explicitly changes the status.

```
Active -> Suspended -> Active -> Completed
                              -> Failed
                              -> Deleted
```

The `AgentSession::withState()` method updates the agent state without changing the session status. This is intentional: the session status represents a cross-run concern (is this conversation still active?), while the execution status represents a per-run concern (did this particular run succeed?).

### Execution Lifecycle

Each call to `SendMessage` creates a new execution within the session. The `AgentState` tracks execution status (`Pending`, `InProgress`, `Completed`, `Stopped`, `Failed`) independently of the session status. Between executions, the state is reset via `forNextExecution()`.

A session can be `Active` while its last execution was `Failed` -- the session is still open for new messages, even though the most recent run encountered an error.

## Session Controllers

Session controllers intercept the runtime pipeline at four stages, allowing you to modify the session at each point. This is how you implement cross-cutting session concerns like auto-suspend, validation, or audit logging.

### The CanControlAgentSession Interface

```php
interface CanControlAgentSession
{
    public function onStage(AgentSessionStage $stage, AgentSession $session): AgentSession;
}
```

The `AgentSessionStage` enum defines the four interception points:

| Stage | When | Typical Use |
|---|---|---|
| `AfterLoad` | After loading from the store | Validation, enrichment |
| `AfterAction` | After the action has executed | Post-processing, derived state |
| `BeforeSave` | Before persisting to the store | Auto-suspend, status derivation |
| `AfterSave` | After successful persistence | Notifications, audit logging |

### Using SessionHookStack

The `SessionHookStack` composes multiple controllers into a priority-ordered pipeline:

```php
use Cognesy\Agents\Session\Contracts\CanControlAgentSession;
use Cognesy\Agents\Session\Data\AgentSession;
use Cognesy\Agents\Session\Enums\AgentSessionStage;
use Cognesy\Agents\Session\SessionHookStack;
use Cognesy\Agents\Session\SessionRuntime;

// Auto-suspend after every action
$autoSuspend = new class implements CanControlAgentSession {
    public function onStage(AgentSessionStage $stage, AgentSession $session): AgentSession {
        return match ($stage) {
            AgentSessionStage::BeforeSave => $session->suspended(),
            default => $session,
        };
    }
};

$hooks = SessionHookStack::empty()->with($autoSuspend, priority: 100);
$runtime = new SessionRuntime($repo, $events, $hooks);
```

Higher priority hooks run first. The `SessionHookStack` itself implements `CanControlAgentSession`, so you can also pass a single controller directly to the runtime constructor.

If no controller is provided, the runtime uses `PassThroughSessionController`, which returns the session unchanged at every stage.

## Events

The `SessionRuntime` emits events at key points in the pipeline. All events are dispatched through the `CanHandleEvents` instance passed to the runtime constructor.

| Event | When | Key Data |
|---|---|---|
| `SessionLoaded` | After successfully loading a session | `sessionId`, `version`, `status` |
| `SessionActionExecuted` | After an action completes (before save) | `sessionId`, `action` class name, before/after version and status |
| `SessionSaved` | After successful persistence | `sessionId`, `version`, `status` |
| `SessionLoadFailed` | When loading throws an exception | `sessionId`, `error`, `errorType` |
| `SessionSaveFailed` | When saving throws an exception | `sessionId`, `error`, `errorType` |

You can listen for these events to build dashboards, audit logs, or monitoring alerts:

```php
use Cognesy\Agents\Session\Events\SessionActionExecuted;
use Cognesy\Agents\Session\Events\SessionSaveFailed;

$events->addListener(SessionActionExecuted::class, function (SessionActionExecuted $e) {
    logger()->info("Session {$e->sessionId}: {$e->action} executed, "
        . "version {$e->beforeVersion} -> {$e->afterVersion}");
});

$events->addListener(SessionSaveFailed::class, function (SessionSaveFailed $e) {
    logger()->error("Session {$e->sessionId}: save failed - {$e->error}");
});
```

## Writing Custom Actions

To create a custom action, implement the `CanExecuteSessionAction` interface:

```php
use Cognesy\Agents\Session\Contracts\CanExecuteSessionAction;
use Cognesy\Agents\Session\Data\AgentSession;

final readonly class ArchiveSession implements CanExecuteSessionAction
{
    public function __construct(
        private string $archiveReason,
    ) {}

    public function executeOn(AgentSession $session): AgentSession
    {
        // Store the reason in metadata, then mark as completed
        $state = $session->state()->withMetadata(
            $session->state()->metadata()->withValue('archive_reason', $this->archiveReason)
        );

        return $session->withState($state)->completed();
    }
}

// Usage
$runtime->execute($sessionId, new ArchiveSession('Ticket resolved'));
```

Actions should be pure transformations on the session. Side effects (external API calls, notifications) are better handled through session controllers or event listeners.

## Related

- [AgentBuilder & Capabilities](13-agent-builder.md)
- [Agent Templates](14-agent-templates.md)
- [Subagents](15-subagents.md)
