---
title: Session Management
description: 'Continue the most recent agent session or resume a specific session by ID to maintain conversational context across multiple executions.'
---

## Introduction

CLI-based code agents maintain internal session state that includes the conversation history, file context, and previous tool results. Agent-Ctrl exposes this session mechanism through two builder methods -- `continueSession()` and `resumeSession()` -- that work consistently across all three supported agents.

Session continuity is valuable when a task spans multiple steps. Rather than starting fresh each time and re-establishing context, you can continue an existing session so the agent remembers what was discussed and what actions were taken previously.

## How Sessions Work

Each agent manages sessions differently under the hood, but Agent-Ctrl normalizes the experience:

- **Claude Code** stores sessions internally and exposes a `session_id` in its JSON stream output. Agent-Ctrl extracts this ID from the stream and makes it available via `AgentResponse::sessionId()`.

- **Codex** uses thread-based conversations and returns a thread ID in its response. Agent-Ctrl normalizes this into an `AgentSessionId`.

- **OpenCode** maintains named sessions with their own session ID format. Agent-Ctrl extracts and normalizes these as well.

Regardless of the agent, the flow is the same: execute a prompt, capture the session ID from the response, and pass it to a subsequent execution.

## Continuing the Most Recent Session

The simplest form of session continuity is `continueSession()`, which tells the agent to pick up where the last session left off. You do not need to know or store the session ID:

```php
use Cognesy\AgentCtrl\AgentCtrl;

// First execution starts a new session
$response = AgentCtrl::claudeCode()
    ->execute('Create an implementation plan for the payment module.');

echo $response->text();

// Second execution continues the most recent session
$response = AgentCtrl::claudeCode()
    ->continueSession()
    ->execute('Now implement the first item in the plan.');

echo $response->text();
```

This approach works well for sequential, script-like workflows where each step builds on the previous one and there is no need to branch or revisit earlier sessions.

## Resuming a Specific Session

When you need to resume a particular session -- for example, after a delay, from a different process, or to branch from a specific point -- use `resumeSession()` with the session ID:

```php
use Cognesy\AgentCtrl\AgentCtrl;

// First execution: capture the session ID
$first = AgentCtrl::claudeCode()
    ->execute('Create a detailed plan for refactoring the UserService.');

$sessionId = $first->sessionId();

// Store $sessionId somewhere (database, cache, file, etc.)
// ...

// Later: resume the exact same session
if ($sessionId !== null) {
    $second = AgentCtrl::claudeCode()
        ->resumeSession((string) $sessionId)
        ->execute('Implement step 2 from the plan.');
}
```

The `resumeSession()` method accepts a plain string. The `AgentSessionId` value object returned by `sessionId()` implements `__toString()`, so you can cast it directly.

## Reading the Session ID

`AgentResponse::sessionId()` returns an `AgentSessionId` value object or `null`. A `null` value means the agent did not expose a session identifier in its output -- this can happen if the agent's CLI version does not support sessions or if the execution failed before session data was emitted.

```php
$response = AgentCtrl::codex()->execute('Explain the test structure.');

$sessionId = $response->sessionId();

if ($sessionId !== null) {
    echo "Session ID: {$sessionId}\n";      // Uses __toString()
    echo "Session ID: " . (string) $sessionId . "\n"; // Explicit cast
} else {
    echo "No session ID available.\n";
}
```

The `AgentSessionId` is an opaque value object (extending `OpaqueExternalId`) that wraps the raw string identifier. It provides type safety and prevents accidental mixing of session IDs with other string values.

## Session Management by Agent

Each agent's builder exposes the same two methods, but the underlying behavior varies:

### Claude Code

```php
// Continue most recent session
AgentCtrl::claudeCode()
    ->continueSession()
    ->execute('Continue the previous task.');

// Resume specific session
AgentCtrl::claudeCode()
    ->resumeSession('abc-123-def')
    ->execute('Pick up from where we left off.');
```

Claude Code passes `--continue` or `--resume <session_id>` to the `claude` CLI. Session IDs are extracted from the `session_id` field in the JSON stream output.

### Codex

```php
// Continue most recent session
AgentCtrl::codex()
    ->continueSession()
    ->execute('Continue the previous task.');

// Resume specific session (uses Codex thread ID)
AgentCtrl::codex()
    ->resumeSession('thread_abc123')
    ->execute('Pick up from where we left off.');
```

Codex maps session management to its thread system. The session ID corresponds to the Codex thread ID.

### OpenCode

```php
// Continue most recent session
AgentCtrl::openCode()
    ->continueSession()
    ->execute('Continue the previous task.');

// Resume specific session
AgentCtrl::openCode()
    ->resumeSession('session-xyz-789')
    ->execute('Pick up from where we left off.');
```

OpenCode maintains its own session format with support for session titles and sharing.

## Important Considerations

**Session IDs are agent-specific.** Do not attempt to resume a Claude Code session with the Codex bridge, or vice versa. Each agent's session format is incompatible with the others.

**Session availability is not guaranteed.** Some agent versions, configurations, or error scenarios may not produce a session ID. Always check for `null` before storing or reusing a session ID.

**Sessions persist on the agent's side.** Agent-Ctrl does not store or manage session state -- it only passes session identifiers to the CLI. The actual session data (conversation history, file context, etc.) is managed by the agent's own storage system.

**`continueSession()` and `resumeSession()` are mutually exclusive in intent.** If you call both on the same builder, the behavior depends on the agent's CLI -- typically, the explicit session ID from `resumeSession()` takes precedence. For clarity, use only one per execution.

## Multi-Step Workflow Example

```php
use Cognesy\AgentCtrl\AgentCtrl;

// Step 1: Create a plan
$plan = AgentCtrl::claudeCode()
    ->withTimeout(300)
    ->inDirectory('/projects/my-app')
    ->execute('Create a 3-step plan for adding rate limiting to the API.');

$sessionId = $plan->sessionId();
echo "Plan:\n" . $plan->text() . "\n";

if ($sessionId === null) {
    echo "No session available -- cannot continue.\n";
    exit(1);
}

// Step 2: Implement step 1
$step1 = AgentCtrl::claudeCode()
    ->withTimeout(300)
    ->inDirectory('/projects/my-app')
    ->resumeSession((string) $sessionId)
    ->execute('Implement step 1 from the plan.');

echo "\nStep 1 result:\n" . $step1->text() . "\n";

// Step 3: Implement step 2 (still using the same session)
$step2 = AgentCtrl::claudeCode()
    ->withTimeout(300)
    ->inDirectory('/projects/my-app')
    ->resumeSession((string) $sessionId)
    ->execute('Implement step 2 from the plan.');

echo "\nStep 2 result:\n" . $step2->text() . "\n";
```
