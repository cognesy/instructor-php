# Symfony Session Persistence

`packages/symfony` owns the framework-facing persistence seam for native `Cognesy\Agents` sessions.

The boundary is:

- `Cognesy\Agents\Session\Contracts\CanStoreSessions` remains the storage contract
- `Cognesy\Agents\Session\SessionRepository` and `Cognesy\Agents\Session\Contracts\CanManageAgentSessions` remain the runtime-facing services
- `packages/symfony` owns which built-in adapter is selected by config and where persisted state lives by default

Applications can still replace `CanStoreSessions` with their own service if they want Doctrine, Redis, or another backend later.

## Config Model

The package now defines an explicit `instructor.sessions` subtree:

```yaml
instructor:
  sessions:
    store: file # memory | file
    file:
      directory: '%kernel.cache_dir%/instructor/agent-sessions'
```

Supported drivers:

- `memory`: process-local and ephemeral, useful for tests and simple CLI flows
- `file`: JSON-backed persisted sessions with per-session file locking and optimistic version checks

Compatibility aliases accepted by the config tree:

- `driver` -> `store`
- `session_store` -> `store`
- top-level `directory` -> `file.directory`

## Why The First Persisted Adapter Is File-Based

Symfony needs a persistence baseline that is:

- zero-dependency
- portable across CLI, HTTP, and Messenger workers
- explicit about where state lives
- compatible with resumable workflows immediately

The file-backed store already exists in `packages/agents`, includes lock files plus version checks, and avoids forcing Doctrine-specific schema or bundle assumptions into the first supported Symfony path.

That makes the current ownership split clean:

- `packages/agents` keeps the reusable session model and storage contract
- `packages/symfony` selects and wires the supported framework default

## Storage Conventions

With `store: file`, the package persists one JSON payload per session:

- session payload: `<session-id>.json`
- lock file: `<session-id>.lock`

The default directory is:

```text
%kernel.cache_dir%/instructor/agent-sessions
```

Override it when you need durable storage outside the cache dir or shared storage across multiple worker boots.

## Resume Flow

The package-owned Messenger entrypoint already composes with persisted sessions:

```php
<?php

use Cognesy\Instructor\Symfony\Delivery\Messenger\ExecuteNativeAgentPromptMessage;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ResumeAgentController
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {}

    public function __invoke(string $sessionId): void
    {
        $this->bus->dispatch(new ExecuteNativeAgentPromptMessage(
            definition: 'support-agent',
            prompt: 'Continue the previous task',
            sessionId: $sessionId,
        ));
    }
}
```

If the selected store is persistent, the handler can load the prior session state and continue it in the worker.

## Conflict Semantics

Persisted session writes use optimistic concurrency semantics.

That means:

- create requires version `0`
- each successful save increments the stored version
- stale writes fail with `SessionConflictException` instead of silently winning

This keeps queued and resumable workflows predictable when more than one process can touch the same session.
